<?php

namespace App\Http\Controllers\Admin\Zk;

use RuntimeException;

/**
 * Minimal pure-PHP ZKTeco client over UDP (port 4370): connect (with comm key), device info,
 * attendance log, disconnect. No sockets extension needed (stream_socket_client).
 *
 * Packet: command, checksum, session id, reply id (4 × uint16 LE) + data — the same framing as
 * the open-source zklib / pyzk clients. Large replies come as PREPARE_DATA + DATA chunks.
 *
 *   $zk = new ZkClient('192.168.1.201', 4370, 0, 4);
 *   $zk->connect(); $logs = $zk->attendance(); $zk->disconnect();
 *   // $logs = [['user_id' => '12', 'time' => '2026-11-04 09:02:11', 'status' => 1, 'punch' => 0], ...]
 */
class ZkClient
{
    private const USHRT_MAX = 65535;

    private const CMD_CONNECT        = 1000;
    private const CMD_EXIT           = 1001;
    private const CMD_ENABLEDEVICE   = 1002;
    private const CMD_DISABLEDEVICE  = 1003;
    private const CMD_AUTH           = 1102;
    private const CMD_GET_VERSION    = 1100;
    private const CMD_OPTIONS_RRQ    = 11;
    private const CMD_ATTLOG_RRQ     = 13;
    private const CMD_GET_FREE_SIZES = 50;
    private const CMD_PREPARE_DATA   = 1500;
    private const CMD_DATA           = 1501;
    private const CMD_FREE_DATA      = 1502;
    private const CMD_DATA_WRRQ      = 1503;
    private const CMD_READ_BUFFER    = 1504;
    private const CMD_ACK_OK         = 2000;
    private const CMD_ACK_ERROR      = 2001;
    private const CMD_ACK_DATA       = 2002;
    private const CMD_ACK_UNAUTH     = 2005;

    private const MAX_CHUNK = 16 * 1024;
    private const MAX_BUFFER = 64 * 1024 * 1024;   // sanity cap on a device-reported data size (~1.6M records of 40 bytes)
    private const MAX_PACKET = 65536;              // largest UDP datagram we read in one go

    /** @var resource|null */
    private $socket = null;
    private int $sessionId = 0;
    private int $replyId = self::USHRT_MAX - 1;
    private int $response = 0;
    private string $data = '';
    private bool $connected = false;

    public function __construct(
        private string $ip,
        private int $port = 4370,
        private int $commKey = 0,
        private float $timeout = 4.0,
    ) {
    }

    public function __destruct()
    {
        if ($this->socket) {
            @fclose($this->socket);
        }
    }

    /** Opens the session; throws RuntimeException with a readable message when the device does not answer. */
    public function connect(): void
    {
        if (!filter_var($this->ip, FILTER_VALIDATE_IP) && !preg_match('/^[a-z0-9.-]+$/i', $this->ip)) {
            throw new RuntimeException('The device address "' . $this->ip . '" is not a valid IP or host name.');
        }

        $errNo = 0;
        $errStr = '';
        $socket = @stream_socket_client('udp://' . $this->ip . ':' . $this->port, $errNo, $errStr, $this->timeout);
        if (!$socket) {
            throw new RuntimeException('Cannot open a connection to ' . $this->ip . ':' . $this->port . ($errStr ? ' (' . $errStr . ')' : '') . '.');
        }
        stream_set_blocking($socket, true);
        $this->socket = $socket;
        $this->sessionId = 0;
        $this->replyId = self::USHRT_MAX - 1;

        $this->command(self::CMD_CONNECT);
        $this->sessionId = $this->lastHeader['session'];

        if ($this->response === self::CMD_ACK_UNAUTH) {
            $this->command(self::CMD_AUTH, self::commKey($this->commKey, $this->sessionId));
        }

        if ($this->response !== self::CMD_ACK_OK) {
            throw new RuntimeException($this->response === self::CMD_ACK_UNAUTH
                ? 'The device rejected the communication key (comm key / password).'
                : 'The device refused the connection (reply ' . $this->response . ').');
        }

        $this->connected = true;
    }

    public function disconnect(): void
    {
        if ($this->connected) {
            try {
                // Fire and forget: waiting for the reply would add another full timeout when the
                // device has just stopped answering (the usual reason we are disconnecting).
                $this->send(self::CMD_EXIT);
            } catch (RuntimeException) {
                // nothing more to do
            }
        }
        $this->connected = false;
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public function disableDevice(): void
    {
        $this->command(self::CMD_DISABLEDEVICE);
    }

    public function enableDevice(): void
    {
        $this->command(self::CMD_ENABLEDEVICE);
    }

    /** Firmware version string ('' when the device does not tell). */
    public function version(): string
    {
        $this->command(self::CMD_GET_VERSION, '', 1024);

        return $this->ok() ? trim(explode("\0", $this->data)[0]) : '';
    }

    /** An option value, e.g. '~SerialNumber', '~DeviceName'. */
    public function option(string $name): string
    {
        $this->command(self::CMD_OPTIONS_RRQ, $name . "\0", 1024);
        if (!$this->ok()) {
            return '';
        }
        $parts = explode('=', explode("\0", $this->data)[0], 2);

        return trim($parts[1] ?? '');
    }

    /** ['users' => n, 'records' => n, ...] from the free-sizes block (0s when unknown). */
    public function sizes(): array
    {
        $this->command(self::CMD_GET_FREE_SIZES, '', 1024);
        $out = ['users' => 0, 'fingers' => 0, 'records' => 0, 'capacity' => 0];
        if ($this->ok() && strlen($this->data) >= 80) {
            $f = array_values(unpack('l20', substr($this->data, 0, 80)));
            $out = ['users' => $f[4], 'fingers' => $f[6], 'records' => $f[8], 'capacity' => $f[14] ?? 0];
        }

        return $out;
    }

    /**
     * Every attendance record on the device:
     * [['user_id' => string, 'time' => 'Y-m-d H:i:s', 'status' => int, 'punch' => int], ...]
     */
    public function attendance(): array
    {
        $records = $this->sizes()['records'];
        if ($records <= 0) {
            return [];
        }

        $buffer = $this->readWithBuffer(self::CMD_ATTLOG_RRQ);
        if (strlen($buffer) < 4) {
            return [];
        }

        $total = unpack('V', substr($buffer, 0, 4))[1];
        $buffer = substr($buffer, 4);
        $recordSize = $records > 0 ? intdiv($total, $records) : 40;

        $out = [];
        if ($recordSize === 8) {
            // uid(2) status(1) time(4) punch(1); the internal uid stands in for the user id
            for ($i = 0; $i + 8 <= strlen($buffer); $i += 8) {
                $r = unpack('vuid/Cstatus/Vtime/Cpunch', substr($buffer, $i, 8));
                $out[] = ['user_id' => (string) $r['uid'], 'time' => self::decodeTime($r['time']), 'status' => $r['status'], 'punch' => $r['punch']];
            }
        } elseif ($recordSize === 16) {
            // user_id(4) time(4) status(1) punch(1) reserved(2) workcode(4)
            for ($i = 0; $i + 16 <= strlen($buffer); $i += 16) {
                $r = unpack('Vuser/Vtime/Cstatus/Cpunch', substr($buffer, $i, 10));
                $out[] = ['user_id' => (string) $r['user'], 'time' => self::decodeTime($r['time']), 'status' => $r['status'], 'punch' => $r['punch']];
            }
        } else {
            // uid(2) user_id(24) status(1) time(4) punch(1) space(8) = 40
            for ($i = 0; $i + 40 <= strlen($buffer); $i += 40) {
                $chunk = substr($buffer, $i, 40);
                $user = trim(explode("\0", substr($chunk, 2, 24))[0]);
                $r = unpack('Cstatus/Vtime/Cpunch', substr($chunk, 26, 6));
                $out[] = ['user_id' => $user !== '' ? $user : (string) unpack('v', $chunk)[1], 'time' => self::decodeTime($r['time']),
                    'status' => $r['status'], 'punch' => $r['punch']];
            }
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /*  Protocol                                                           */
    /* ------------------------------------------------------------------ */

    private array $lastHeader = ['command' => 0, 'checksum' => 0, 'session' => 0, 'reply' => 0];

    private function ok(): bool
    {
        return in_array($this->response, [self::CMD_ACK_OK, self::CMD_PREPARE_DATA, self::CMD_DATA, self::CMD_ACK_DATA], true);
    }

    /** Sends a command and reads its reply into $response / $data. */
    private function command(int $command, string $payload = '', int $responseSize = 8): void
    {
        $this->send($command, $payload);
        $packet = $this->receive($responseSize + 8);
        $this->parse($packet);
    }

    private function send(int $command, string $payload = ''): void
    {
        if (!$this->socket) {
            throw new RuntimeException('Not connected to the device.');
        }
        $packet = self::header($command, $payload, $this->sessionId, $this->replyId);
        if (@fwrite($this->socket, $packet) === false) {
            throw new RuntimeException('Cannot send to the device at ' . $this->ip . ':' . $this->port . '.');
        }
    }

    private function receive(int $size): string
    {
        $read = [$this->socket];
        $write = $except = null;
        $sec = (int) floor($this->timeout);
        $usec = (int) (($this->timeout - $sec) * 1e6);

        $ready = @stream_select($read, $write, $except, $sec, $usec);
        if ($ready === false || $ready === 0) {
            throw new RuntimeException('No reply from the device at ' . $this->ip . ':' . $this->port . ' within ' . rtrim(rtrim(number_format($this->timeout, 1), '0'), '.')
                . ' seconds. Check that it is switched on, on the same network, and that UDP port ' . $this->port . ' is open.');
        }

        // Read the whole datagram: a UDP read shorter than the datagram silently drops the rest.
        $packet = @stream_socket_recvfrom($this->socket, max($size + 64, self::MAX_PACKET));
        if ($packet === false || $packet === '') {
            throw new RuntimeException('The device at ' . $this->ip . ':' . $this->port . ' is not reachable (connection refused).');
        }

        return $packet;
    }

    private function parse(string $packet): void
    {
        if (strlen($packet) < 8) {
            throw new RuntimeException('The device sent an invalid reply.');
        }
        $h = unpack('vcommand/vchecksum/vsession/vreply', substr($packet, 0, 8));
        $this->lastHeader = $h;
        $this->response = $h['command'];
        $this->replyId = $h['reply'];
        $this->data = substr($packet, 8);
    }

    /** Asks the device to buffer a data set and reads it (direct DATA reply or chunked). */
    private function readWithBuffer(int $command, int $fct = 0, int $ext = 0): string
    {
        $payload = pack('c', 1) . pack('v', $command) . pack('V', $fct) . pack('V', $ext);
        $this->command(self::CMD_DATA_WRRQ, $payload, 1024);

        if (!$this->ok()) {
            throw new RuntimeException('The device does not support buffered reads (reply ' . $this->response . ').');
        }
        if ($this->response === self::CMD_DATA) {
            return $this->data;
        }

        // PREPARE/ACK reply: byte 0 = flag, bytes 1-4 = total size of the buffered data set
        if (strlen($this->data) < 5) {
            throw new RuntimeException('The device sent an incomplete reply while preparing the attendance log.');
        }
        $size = unpack('V', substr($this->data, 1, 4))[1];
        if ($size > self::MAX_BUFFER) {
            throw new RuntimeException('The device reported an attendance log of ' . $size . ' bytes, which is not plausible; sync stopped.');
        }
        $out = '';
        $start = 0;
        while ($start < $size) {
            $chunk = min(self::MAX_CHUNK, $size - $start);
            $out .= $this->readChunk($start, $chunk);
            $start += $chunk;
        }
        $this->command(self::CMD_FREE_DATA);

        return $out;
    }

    private function readChunk(int $start, int $size): string
    {
        for ($try = 0; $try < 3; $try++) {
            $this->command(self::CMD_READ_BUFFER, pack('V', $start) . pack('V', $size), 1024);

            if ($this->response === self::CMD_DATA) {
                return $this->data;
            }
            if ($this->response === self::CMD_PREPARE_DATA) {
                $out = '';
                while (true) {
                    $packet = $this->receive(1032);
                    if (strlen($packet) < 8) {
                        throw new RuntimeException('The device sent an invalid reply while sending the attendance log.');
                    }
                    $cmd = unpack('v', substr($packet, 0, 2))[1];
                    if ($cmd === self::CMD_DATA) {
                        $out .= substr($packet, 8);
                        continue;
                    }
                    break;   // ACK_OK ends the chunk
                }

                return $out;
            }
        }

        throw new RuntimeException('Could not read the attendance log from the device (chunk ' . $start . ').');
    }

    /* ------------------------------------------------------------------ */
    /*  Encoding helpers                                                   */
    /* ------------------------------------------------------------------ */

    public static function header(int $command, string $payload, int $sessionId, int $replyId): string
    {
        $buf = pack('v4', $command, 0, $sessionId, $replyId) . $payload;
        $checksum = self::checksum($buf);
        $replyId++;
        if ($replyId >= self::USHRT_MAX) {
            $replyId -= self::USHRT_MAX;
        }

        return pack('v4', $command, $checksum, $sessionId, $replyId) . $payload;
    }

    public static function checksum(string $buf): int
    {
        $bytes = array_values(unpack('C*', $buf));
        $len = count($bytes);
        $sum = 0;
        $i = 0;
        while ($len > 1) {
            $sum += $bytes[$i] | ($bytes[$i + 1] << 8);
            if ($sum > self::USHRT_MAX) {
                $sum -= self::USHRT_MAX;
            }
            $i += 2;
            $len -= 2;
        }
        if ($len) {
            $sum += $bytes[count($bytes) - 1];
        }
        while ($sum > self::USHRT_MAX) {
            $sum -= self::USHRT_MAX;
        }
        $sum = ~$sum;
        while ($sum < 0) {
            $sum += self::USHRT_MAX;
        }

        return $sum & 0xFFFF;
    }

    /** The scrambled comm key sent with CMD_AUTH. */
    public static function commKey(int $key, int $sessionId, int $ticks = 50): string
    {
        $k = 0;
        for ($i = 0; $i < 32; $i++) {
            $k = ($key & (1 << $i)) ? (($k << 1) | 1) : ($k << 1);
            $k &= 0xFFFFFFFF;
        }
        $k = ($k + $sessionId) & 0xFFFFFFFF;

        $b = array_values(unpack('C4', pack('V', $k)));
        $b = [$b[0] ^ ord('Z'), $b[1] ^ ord('K'), $b[2] ^ ord('S'), $b[3] ^ ord('O')];
        // swap the two 16-bit halves
        $b = [$b[2], $b[3], $b[0], $b[1]];
        $t = 0xFF & $ticks;

        return pack('C4', $b[0] ^ $t, $b[1] ^ $t, $t, $b[3] ^ $t);
    }

    /** Device time (seconds packed into Y/M/D/h/m/s since 2000) to 'Y-m-d H:i:s'. */
    public static function decodeTime(int $t): string
    {
        $second = $t % 60; $t = intdiv($t, 60);
        $minute = $t % 60; $t = intdiv($t, 60);
        $hour   = $t % 24; $t = intdiv($t, 24);
        $day    = $t % 31 + 1; $t = intdiv($t, 31);
        $month  = $t % 12 + 1; $t = intdiv($t, 12);
        $year   = $t + 2000;

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }
}
