<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\VoucherHelperTrait;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

/**
 * Attendance: daily sheet (manual), monthly report (PDF / Excel), punch import (CSV / Excel),
 * work shifts and the rules that turn a month's attendance into salary adjustments.
 * Devices (ZKTeco) live in AttendanceDeviceController; their punches are turned into days here
 * by rebuildFromPunches().
 *
 * Shared rules (public static, bottom of the file):
 *   settings(), shifts(), shiftFor(), context(), dayType(), evaluate(),
 *   rebuildFromPunches(), monthSummary(), salaryLines().
 *
 * A day's status:
 *   holiday -> Holiday, weekly off of the shift (or the company weekend) -> Weekly Off, approved leave -> Leave,
 *   no check-in -> Absent, in later than start + half_day_after -> Half Day (also when worked < half the shift),
 *   in later than start + grace -> Late, else Present. Overtime = out - shift end, once it reaches overtime_after.
 *   Work on a holiday / weekly off counts fully as overtime.
 */
class AttendanceController extends Controller
{
    use VoucherHelperTrait;

    public const STATUSES = ['Present', 'Late', 'Half Day', 'Absent', 'Leave', 'Holiday', 'Weekly Off'];
    public const CODES = ['Present' => 'P', 'Late' => 'L', 'Half Day' => 'HD', 'Absent' => 'A', 'Leave' => 'LV', 'Holiday' => 'H', 'Weekly Off' => 'W'];
    public const DAYS = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    public const REMARK_PREFIX = 'Attendance:';

    /* ------------------------------------------------------------------ */
    /*  Daily sheet                                                        */
    /* ------------------------------------------------------------------ */

    public function daily()
    {
        return view('admin.attendance.daily', [
            'departments' => $this->departments(),
            'statuses'    => self::STATUSES,
        ]);
    }

    public function dailyData(Request $request)
    {
        $date = $this->dateParam($request->query('date'));
        $deptId = $request->filled('department_id') ? (int) $request->query('department_id') : null;

        $employees = self::employeesQuery($date, $date, $deptId)->get();
        $ids = $employees->pluck('id')->all();
        $rows = DB::table('attendances')->where('date', $date)->whereIn('employee_id', $ids ?: [0])->get()->keyBy('employee_id');
        $ctx = self::context($date, $date, $ids);

        $data = $employees->map(function ($e) use ($rows, $ctx, $date) {
            $shift = self::shiftFor($e);
            $type = self::dayType($e, $shift, $date, $ctx);
            $r = $rows[$e->id] ?? null;

            return [
                'employee_id'  => $e->id,
                'code'         => $e->employee_code,
                'name'         => $e->name,
                'designation'  => $e->designation,
                'department'   => $e->department,
                'shift'        => $shift->name,
                'shift_start'  => substr($shift->start_time, 0, 5),
                'shift_end'    => substr($shift->end_time, 0, 5),
                'day_type'     => $type,
                'saved'        => (bool) $r,
                'check_in'     => $r && $r->check_in ? substr($r->check_in, 0, 5) : '',
                'check_out'    => $r && $r->check_out ? substr($r->check_out, 0, 5) : '',
                'status'       => $r->attendance_status ?? null,
                'override'     => $r && $r->status_override === 'Yes' ? $r->attendance_status : '',
                'late_minutes' => (int) ($r->late_minutes ?? 0),
                'ot_minutes'   => (int) ($r->overtime_minutes ?? 0),
                'source'       => $r->source ?? null,
                'remarks'      => $r->remarks ?? '',
            ];
        })->values();

        return response()->json([
            'status'   => true,
            'date'     => $date,
            'label'    => Carbon::parse($date)->format('l, d M Y'),
            'future'   => $date > today()->toDateString(),
            'holiday'  => $ctx['holidays'][$date]['title'] ?? null,
            'data'     => $data,
        ]);
    }

    public function saveDaily(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date'                 => ['required', 'date', 'before_or_equal:today'],
            'rows'                 => ['required', 'array', 'min:1'],
            'rows.*.employee_id'   => ['required', 'integer'],
            'rows.*.check_in'      => ['nullable', 'date_format:H:i'],
            'rows.*.check_out'     => ['nullable', 'date_format:H:i'],
            'rows.*.status'        => ['nullable', Rule::in(self::STATUSES)],
            'rows.*.remarks'       => ['nullable', 'string', 'max:255'],
        ], [
            'date.before_or_equal'       => 'Attendance cannot be saved for a future date.',
            'rows.required'              => 'There is nothing to save.',
            'rows.*.check_in.date_format'  => 'Check-in must be a time like 09:05.',
            'rows.*.check_out.date_format' => 'Check-out must be a time like 18:10.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $date = Carbon::parse($request->input('date'))->toDateString();
            $input = collect($request->input('rows'))->keyBy(fn ($r) => (int) $r['employee_id']);
            $employees = self::employeesQuery($date, $date, null, $input->keys()->all())->get()->keyBy('id');
            $ctx = self::context($date, $date, $employees->keys()->all());

            $saved = DB::transaction(function () use ($input, $employees, $ctx, $date) {
                $count = 0;
                foreach ($input as $empId => $r) {
                    $e = $employees[$empId] ?? null;
                    if (!$e) {
                        continue;
                    }
                    $shift = self::shiftFor($e);
                    $forced = ($r['status'] ?? '') ?: null;
                    $calc = self::evaluate($shift, $date, $r['check_in'] ?? null, $r['check_out'] ?? null, self::dayType($e, $shift, $date, $ctx), $forced);
                    self::store($e, $date, $calc + [
                        'check_in'        => ($r['check_in'] ?? null) ?: null,
                        'check_out'       => ($r['check_out'] ?? null) ?: null,
                        'status_override' => $forced ? 'Yes' : 'No',
                        'work_shift_id'   => $shift->id,
                        'source'          => 'Manual',
                        'remarks'         => $this->nullIfBlank($r['remarks'] ?? null),
                    ]);
                    $count++;
                }

                return $count;
            });

            return response()->json(['status' => true, 'message' => 'Attendance of ' . Carbon::parse($date)->format('d M Y') . ' saved for ' . $saved . ' employee(s).']);
        } catch (Throwable $e) {
            Log::error('Attendance save error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the attendance.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Monthly report                                                     */
    /* ------------------------------------------------------------------ */

    public function monthly()
    {
        return view('admin.attendance.monthly', [
            'departments' => $this->departments(),
            'employees'   => BranchController::scope(DB::table('employees'), 'branch_id')->where('deleted', 'No')->orderBy('name')->get(['id', 'name', 'employee_code']),
            'codes'       => self::CODES,
            'settings'    => self::settings(),
        ]);
    }

    public function monthlyData(Request $request)
    {
        [$month, $deptId, $empId] = $this->monthFilters($request);
        $summary = self::monthSummary($month, $deptId, $empId ? [$empId] : null);

        return response()->json(['status' => true] + $summary);
    }

    public function monthlyPdf(Request $request)
    {
        [$month, $deptId, $empId] = $this->monthFilters($request);
        $summary = self::monthSummary($month, $deptId, $empId ? [$empId] : null);

        $pdf = PDF::loadView('admin.attendance.monthly-pdf', $summary + [
            'department' => $deptId ? DB::table('departments')->where('id', $deptId)->value('name') : 'All departments',
            'codes'      => self::CODES,
        ])->setPaper('a4', 'landscape');

        $name = 'Attendance-' . $month->format('Y-m') . '.pdf';

        return $request->boolean('download') ? $pdf->download($name) : $pdf->stream($name);
    }

    public function monthlyExcel(Request $request)
    {
        [$month, $deptId, $empId] = $this->monthFilters($request);
        $s = self::monthSummary($month, $deptId, $empId ? [$empId] : null);
        $xl = fn ($k) => strtoupper(ltrim(SettingController::pdfColors()[$k], '#'));

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Attendance ' . $month->format('M Y'));
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $days = count($s['dates']);
        $countCols = ['Present', 'Late', 'Half Day', 'Absent', 'Leave', 'Holiday', 'Weekly Off', 'OT Hours'];
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $days + count($countCols));

        $company = DB::table('settings')->where('deleted', 'No')->value('company_name') ?: config('app.name');
        $sheet->setCellValue('A1', $company)->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'Monthly Attendance — ' . $month->format('F Y'))->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB($xl('accent'));
        $sheet->setCellValue('A3', 'P = Present, L = Late, HD = Half day, A = Absent, LV = Leave, H = Holiday, W = Weekly off, · = no attendance taken')->mergeCells("A3:{$lastCol}3");
        $sheet->getStyle('A3')->getFont()->setItalic(true)->getColor()->setRGB($xl('muted'));

        $row = 5;
        $heads = ['Code', 'Employee', 'Department'];
        foreach ($s['dates'] as $d) {
            $heads[] = (string) Carbon::parse($d['date'])->day;
        }
        $heads = array_merge($heads, $countCols);
        $sheet->fromArray($heads, null, "A{$row}");
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $xl('on_brand')]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $xl('brand')]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach ($s['employees'] as $e) {
            $row++;
            $line = [$e['code'], $e['name'], $e['department']];
            foreach ($e['days'] as $d) {
                $line[] = $d['code'];
            }
            $c = $e['counts'];
            $line = array_merge($line, [$c['present'], $c['late'], $c['half_day'], $c['absent'], $c['leave'], $c['holiday'], $c['weekly_off'], $c['ot_hours']]);
            $sheet->fromArray($line, null, "A{$row}");
            $sheet->setCellValueExplicit("A{$row}", (string) $e['code'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            foreach ($e['days'] as $i => $d) {
                $color = ['A' => 'danger', 'L' => 'warning', 'HD' => 'warning', 'LV' => 'accent', 'H' => 'muted', 'W' => 'muted'][$d['code']] ?? null;
                if ($color) {
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4 + $i);
                    $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true)->getColor()->setRGB($xl($color));
                }
            }
        }

        $sheet->getStyle("A5:{$lastCol}{$row}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $xl('border')]]]]);
        $sheet->getStyle("D6:{$lastCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getColumnDimension('C')->setWidth(16);
        for ($i = 4; $i < 4 + $days; $i++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setWidth(4.5);
        }
        $sheet->freezePane('D6');
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, 'Attendance-' . $month->format('Y-m') . '.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /* ------------------------------------------------------------------ */
    /*  Send to salary                                                     */
    /* ------------------------------------------------------------------ */

    public function salaryPreview(Request $request)
    {
        [$month, $deptId] = $this->monthFilters($request);
        $lines = self::salaryLines($month, $deptId);

        return response()->json([
            'status'   => true,
            'month'    => $month->format('F Y'),
            'settings' => self::settings(),
            'data'     => array_values($lines),
            'totals'   => [
                'absence'   => round(collect($lines)->where('processed', false)->sum('absence_amount'), 2),
                'late'      => round(collect($lines)->where('processed', false)->sum('late_amount'), 2),
                'overtime'  => round(collect($lines)->where('processed', false)->sum('ot_amount'), 2),
                'processed' => collect($lines)->where('processed', true)->count(),
            ],
        ]);
    }

    /** Replaces the attendance-generated salary adjustments of the month (employees not processed yet). */
    public function sendToSalary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month'         => ['required', 'date_format:Y-m'],
            'department_id' => ['nullable', 'integer'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            [$month, $deptId] = $this->monthFilters($request);

            $result = DB::transaction(function () use ($month, $deptId) {
                $lines = self::salaryLines($month, $deptId);
                $replaced = $added = $employees = 0;

                foreach ($lines as $l) {
                    if ($l['processed']) {
                        continue;
                    }
                    $replaced += DB::table('salary_adjustments')->where('employee_id', $l['employee_id'])->where('month', $month->toDateString())
                        ->where('deleted', 'No')->where('remarks', 'like', self::REMARK_PREFIX . '%')
                        ->update($this->deletedColumns());

                    $rows = [];
                    if ($l['absence_amount'] > 0) {
                        $rows[] = ['Deduction', 'Absence', $l['absence_days'], $l['absence_amount'],
                            self::REMARK_PREFIX . ' ' . self::num($l['absent']) . ' absent' . ($l['half_day'] ? ' + ' . $l['half_day'] . ' half day(s)' : '')
                            . ' × ' . number_format($l['daily_rate'], 2) . '/day'];
                    }
                    if ($l['late_amount'] > 0) {
                        $rows[] = ['Deduction', 'Late Fine', $l['late_days'], $l['late_amount'],
                            self::REMARK_PREFIX . ' ' . $l['late'] . ' late(s) = ' . self::num($l['late_days']) . ' day(s) × ' . number_format($l['daily_rate'], 2)];
                    }
                    if ($l['ot_amount'] > 0) {
                        $rows[] = ['Addition', 'Overtime', null, $l['ot_amount'],
                            self::REMARK_PREFIX . ' ' . self::num($l['ot_hours']) . ' OT hour(s) × ' . self::num($l['ot_multiplier']) . ' × ' . number_format($l['hourly_rate'], 2) . '/h'];
                    }

                    foreach ($rows as [$type, $head, $days, $amount, $remarks]) {
                        DB::table('salary_adjustments')->insert([
                            'employee_id' => $l['employee_id'], 'month' => $month->toDateString(), 'adj_type' => $type, 'head' => $head,
                            'days' => $days, 'amount' => $amount, 'remarks' => Str::limit($remarks, 250, ''),
                            'deleted' => 'No', 'status' => 'Active',
                            'created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $added++;
                    }
                    $employees += $rows ? 1 : 0;
                }

                return compact('replaced', 'added', 'employees');
            });

            return response()->json([
                'status'  => true,
                'message' => $result['added'] . ' salary adjustment(s) created for ' . $result['employees'] . ' employee(s) of ' . $month->format('F Y')
                    . ($result['replaced'] ? ' (' . $result['replaced'] . ' earlier attendance entr' . ($result['replaced'] == 1 ? 'y' : 'ies') . ' replaced)' : '') . '.',
            ] + $result);
        } catch (Throwable $e) {
            Log::error('Attendance to salary error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to send the attendance to the salary sheet.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Import (CSV / Excel)                                               */
    /* ------------------------------------------------------------------ */

    public function import()
    {
        return view('admin.attendance.import');
    }

    /** Stores the file and returns its first rows with a guessed column mapping. */
    public function importUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx,xls'],
        ], ['file.required' => 'Choose the CSV or Excel file.', 'file.mimes' => 'Upload a .csv, .txt, .xlsx or .xls file.']);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $ext = strtolower($request->file('file')->getClientOriginalExtension() ?: 'csv');
            $token = Str::random(32) . '.' . (in_array($ext, ['csv', 'txt', 'xlsx', 'xls'], true) ? $ext : 'csv');
            $request->file('file')->storeAs('attendance-imports', $token, 'local');

            $rows = self::readSheet(self::importPath($token));
            if (!$rows) {
                return response()->json(['status' => false, 'message' => 'The file has no rows.'], 422);
            }

            return response()->json([
                'status'  => true,
                'token'   => $token,
                'name'    => $request->file('file')->getClientOriginalName(),
                'total'   => count($rows),
                'columns' => count($rows[0]),
                'sample'  => array_slice($rows, 0, 8),
                'mapping' => self::guessMapping($rows),
            ]);
        } catch (Throwable $e) {
            Log::error('Attendance import upload error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'The file could not be read. Save it as CSV or .xlsx and try again.'], 422);
        }
    }

    public function importPreview(Request $request)
    {
        try {
            $parsed = $this->parseImport($request);

            return response()->json(['status' => true] + array_merge($parsed, ['punches' => array_slice($parsed['punches'], 0, 25)]));
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Attendance import preview error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to read the file with this column mapping.'], 422);
        }
    }

    public function importCommit(Request $request)
    {
        try {
            $parsed = $this->parseImport($request);
            abort_if(!$parsed['punches'], 422, 'No punch in the file matches an employee.');

            $result = DB::transaction(function () use ($parsed) {
                $new = 0;
                foreach (array_chunk($parsed['punches'], 500) as $chunk) {
                    $new += DB::table('attendance_punches')->insertOrIgnore(array_map(fn ($p) => [
                        'device_id' => 0, 'device_user_id' => 'emp:' . $p['employee_id'], 'employee_id' => $p['employee_id'],
                        'punched_at' => $p['time'], 'source' => 'Import', 'created_by' => Auth::id(), 'created_at' => now(),
                    ], $chunk));
                }
                $ids = array_values(array_unique(array_column($parsed['punches'], 'employee_id')));

                return ['new' => $new] + self::rebuildFromPunches($ids, $parsed['from'], $parsed['to']);
            });

            @unlink(self::importPath($request->input('token')));

            return response()->json([
                'status'  => true,
                'message' => count($parsed['punches']) . ' punch(es) read, ' . $result['new'] . ' new. ' . $result['days'] . ' attendance day(s) updated'
                    . ($result['skipped'] ? ', ' . $result['skipped'] . ' day(s) kept because they were entered by hand' : '') . '.',
            ]);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Attendance import error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to import the attendance.'], 500);
        }
    }

    /** Reads the stored file with the posted mapping into punches matched to employees. */
    private function parseImport(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'token'      => ['required', 'string', 'regex:/^[A-Za-z0-9]{32}\.(csv|txt|xlsx|xls)$/'],
            'header'     => ['nullable', 'boolean'],
            'id_col'     => ['required', 'integer', 'min:0'],
            'id_type'    => ['required', Rule::in(['code', 'device'])],
            'mode'       => ['required', Rule::in(['datetime', 'split'])],
            'dt_col'     => ['required_if:mode,datetime', 'nullable', 'integer', 'min:0'],
            'date_col'   => ['required_if:mode,split', 'nullable', 'integer', 'min:0'],
            'time_col'   => ['required_if:mode,split', 'nullable', 'integer', 'min:0'],
            'date_order' => ['required', Rule::in(['auto', 'DMY', 'MDY', 'YMD'])],
        ], [
            'token.required'       => 'Upload the file first.',
            'dt_col.required_if'   => 'Pick the date/time column.',
            'date_col.required_if' => 'Pick the date column.',
            'time_col.required_if' => 'Pick the time column.',
        ]);
        abort_if($validator->fails(), 422, $validator->errors()->first());

        $path = self::importPath($request->input('token'));
        abort_unless(is_file($path), 422, 'The uploaded file has expired. Upload it again.');

        $rows = self::readSheet($path);
        if ($request->boolean('header')) {
            array_shift($rows);
        }

        $order = $request->input('date_order');
        $idCol = (int) $request->input('id_col');

        // employee lookups: exact and without leading zeros (Excel drops them)
        $field = $request->input('id_type') === 'code' ? 'employee_code' : 'device_user_id';
        $lookup = [];
        foreach (BranchController::scope(DB::table('employees'), 'branch_id')->where('deleted', 'No')->whereNotNull($field)->get(['id', 'name', 'employee_code', $field . ' as ident']) as $e) {
            $lookup[(string) $e->ident] = $e;
            $lookup['~' . ltrim((string) $e->ident, '0')] ??= $e;
        }

        $punches = [];
        $unmatched = [];
        $invalid = [];
        foreach ($rows as $i => $row) {
            $line = $i + ($request->boolean('header') ? 2 : 1);
            $ident = trim((string) ($row[$idCol] ?? ''));
            if ($ident === '' && !array_filter($row, fn ($v) => trim((string) $v) !== '')) {
                continue;   // blank line
            }
            if (is_numeric($ident) && str_contains($ident, '.') && (float) $ident == (int) $ident) {
                $ident = (string) (int) $ident;   // "12.0" from a spreadsheet
            }

            $time = $request->input('mode') === 'datetime'
                ? self::parseDateTime($row[(int) $request->input('dt_col')] ?? null, $order)
                : self::combine(self::parseDateTime($row[(int) $request->input('date_col')] ?? null, $order, true), self::parseTime($row[(int) $request->input('time_col')] ?? null));

            if ($ident === '' || !$time) {
                if (count($invalid) < 10) {
                    $invalid[] = ['line' => $line, 'text' => implode(' | ', array_map(fn ($v) => (string) $v, $row)), 'reason' => $ident === '' ? 'No employee id' : 'Date/time not understood'];
                }
                continue;
            }

            $e = $lookup[$ident] ?? $lookup['~' . ltrim($ident, '0')] ?? null;
            if (!$e) {
                $unmatched[$ident] = ($unmatched[$ident] ?? 0) + 1;
                continue;
            }

            $punches[] = ['employee_id' => $e->id, 'code' => $e->employee_code, 'name' => $e->name, 'time' => $time];
        }

        usort($punches, fn ($a, $b) => [$a['employee_id'], $a['time']] <=> [$b['employee_id'], $b['time']]);
        $times = array_column($punches, 'time');

        return [
            'rows'       => count($rows),
            'punches'    => $punches,
            'count'      => count($punches),
            'employees'  => count(array_unique(array_column($punches, 'employee_id'))),
            'unmatched'  => collect($unmatched)->map(fn ($n, $id) => ['id' => (string) $id, 'rows' => $n])->values()->take(30)->all(),
            'unmatched_rows' => array_sum($unmatched),
            'invalid'    => $invalid,
            'from'       => $times ? substr(min($times), 0, 10) : null,
            'to'         => $times ? substr(max($times), 0, 10) : null,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Setup: shifts + rules (devices: AttendanceDeviceController)        */
    /* ------------------------------------------------------------------ */

    public function setup()
    {
        return view('admin.attendance.setup', [
            'settings' => self::settings(),
            'days'     => self::DAYS,
            'branches' => DB::table('branches')->where('deleted', 'No')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function shiftList()
    {
        $counts = DB::table('employees')->where('deleted', 'No')->whereNotNull('work_shift_id')->groupBy('work_shift_id')
            ->selectRaw('work_shift_id, COUNT(*) as n')->pluck('n', 'work_shift_id');
        $default = self::settings()->default_shift_id;

        return response()->json(['status' => true, 'data' => DB::table('work_shifts')->where('deleted', 'No')->orderBy('start_time')->get()
            ->map(function ($s) use ($counts, $default) {
                $s->weekly_offs = json_decode($s->weekly_offs ?: '[]', true) ?: [];
                $s->employees = (int) ($counts[$s->id] ?? 0);
                $s->is_default = (int) $s->id === (int) $default;
                $s->start = substr($s->start_time, 0, 5);
                $s->end = substr($s->end_time, 0, 5);

                return $s;
            })]);
    }

    public function saveShift(Request $request)
    {
        $id = $request->filled('id') ? (int) $request->input('id') : null;
        $validator = Validator::make($request->all(), [
            'name'                   => ['required', 'string', 'max:100', Rule::unique('work_shifts', 'name')->where('deleted', 'No')->ignore($id)],
            'start_time'             => ['required', 'date_format:H:i'],
            'end_time'               => ['required', 'date_format:H:i', 'different:start_time'],
            'grace_minutes'          => ['required', 'integer', 'min:0', 'max:240'],
            'half_day_after_minutes' => ['required', 'integer', 'min:0', 'max:720'],
            'overtime_after_minutes' => ['required', 'integer', 'min:0', 'max:720'],
            'weekly_offs'            => ['nullable', 'array', 'max:6'],
            'weekly_offs.*'          => [Rule::in(self::DAYS)],
            'status'                 => ['required', Rule::in(['Active', 'Inactive'])],
        ], ['end_time.different' => 'The shift must end at a different time than it starts.', 'weekly_offs.max' => 'Leave at least one working day.']);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $data = [
            'name'                   => trim($request->input('name')),
            'start_time'             => $request->input('start_time') . ':00',
            'end_time'               => $request->input('end_time') . ':00',
            'grace_minutes'          => (int) $request->input('grace_minutes'),
            'half_day_after_minutes' => (int) $request->input('half_day_after_minutes'),
            'overtime_after_minutes' => (int) $request->input('overtime_after_minutes'),
            'weekly_offs'            => json_encode(array_values(array_intersect(self::DAYS, (array) $request->input('weekly_offs', [])))),
            'status'                 => $request->input('status'),
        ];

        if ($id) {
            DB::table('work_shifts')->where('id', $id)->update($data + ['updated_by' => Auth::id(), 'updated_date' => now(), 'updated_at' => now()]);
        } else {
            $id = DB::table('work_shifts')->insertGetId($data + ['created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        if (!self::settings()->default_shift_id) {
            DB::table('attendance_settings')->update(['default_shift_id' => $id]);
        }
        self::$shiftCache = null;

        return response()->json(['status' => true, 'message' => 'Shift saved. It applies to attendance entered from now on.']);
    }

    public function deleteShift(int $id)
    {
        if ((int) self::settings()->default_shift_id === $id) {
            return response()->json(['status' => false, 'message' => 'This is the default shift. Choose another default first.'], 422);
        }
        if (DB::table('employees')->where('deleted', 'No')->where('work_shift_id', $id)->exists()) {
            return response()->json(['status' => false, 'message' => 'Some employees work this shift. Move them to another shift, or set it Inactive.'], 422);
        }
        DB::table('work_shifts')->where('id', $id)->update($this->deletedColumns());
        self::$shiftCache = null;

        return response()->json(['status' => true, 'message' => 'Shift removed.']);
    }

    public function saveSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'default_shift_id'    => ['required', 'integer', Rule::exists('work_shifts', 'id')->where('deleted', 'No')],
            'absence_deduction'   => ['required', Rule::in(['Yes', 'No'])],
            'lates_per_day'       => ['required', 'integer', 'min:0', 'max:31'],
            'overtime_pay'        => ['required', Rule::in(['Yes', 'No'])],
            'overtime_multiplier' => ['required', 'numeric', 'min:0', 'max:10'],
            'hours_per_day'       => ['required', 'integer', 'min:1', 'max:24'],
        ], ['default_shift_id.required' => 'Select the default shift.']);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        self::settings();
        DB::table('attendance_settings')->update($validator->validated() + ['updated_by' => Auth::id(), 'updated_at' => now()]);

        return response()->json(['status' => true, 'message' => 'Attendance rules saved.']);
    }

    /* ------------------------------------------------------------------ */
    /*  Shared rules                                                       */
    /* ------------------------------------------------------------------ */

    private static ?\Illuminate\Support\Collection $shiftCache = null;

    public static function settings(): object
    {
        $s = DB::table('attendance_settings')->first();
        if (!$s) {
            DB::table('attendance_settings')->insert([
                'default_shift_id' => DB::table('work_shifts')->where('deleted', 'No')->orderBy('id')->value('id'),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $s = DB::table('attendance_settings')->first();
        }

        return $s;
    }

    /** Shifts (incl. inactive ones still assigned) keyed by id, weekly_offs decoded. */
    public static function shifts()
    {
        return self::$shiftCache ??= DB::table('work_shifts')->where('deleted', 'No')->get()->keyBy('id')->map(function ($s) {
            $s->weekly_offs = json_decode($s->weekly_offs ?: '[]', true) ?: [];

            return $s;
        });
    }

    public static function defaultShift(): object
    {
        $shifts = self::shifts();

        return $shifts[self::settings()->default_shift_id] ?? $shifts->first() ?? (object) [
            'id' => null, 'name' => 'General', 'start_time' => '09:00:00', 'end_time' => '18:00:00',
            'grace_minutes' => 10, 'half_day_after_minutes' => 0, 'overtime_after_minutes' => 30, 'weekly_offs' => [],
        ];
    }

    public static function shiftFor(object $employee): object
    {
        $shifts = self::shifts();

        return !empty($employee->work_shift_id) && isset($shifts[$employee->work_shift_id]) ? $shifts[$employee->work_shift_id] : self::defaultShift();
    }

    /** Active employees employed at some point between two dates. */
    public static function employeesQuery(string $from, string $to, ?int $departmentId = null, ?array $ids = null, bool $branchOnly = true)
    {
        $query = DB::table('employees as e');

        return ($branchOnly ? BranchController::scope($query, 'e.branch_id') : $query)
            ->where('e.deleted', 'No')->where('e.status', 'Active')
            ->where(fn ($q) => $q->whereNull('e.joining_date')->orWhere('e.joining_date', '<=', $to))
            ->where(fn ($q) => $q->whereNull('e.resign_date')->orWhere('e.resign_date', '>=', $from))
            ->when($departmentId, fn ($q) => $q->where('e.department_id', $departmentId))
            ->when($ids !== null, fn ($q) => $q->whereIn('e.id', $ids ?: [0]))
            ->orderByRaw('e.department IS NULL')->orderBy('e.department')->orderBy('e.employee_code')
            ->select(['e.id', 'e.employee_code', 'e.name', 'e.designation', 'e.department', 'e.department_id', 'e.branch_id', 'e.email',
                'e.joining_date', 'e.resign_date', 'e.work_shift_id', 'e.device_user_id', 'e.basic_salary', 'e.gross_salary']);
    }

    /** Holidays and approved leave between two dates: ['holidays' => [date => ...], 'leave' => [employee][date] => 'Full'|'Half']. */
    public static function context(string $from, string $to, array $employeeIds): array
    {
        $leave = [];
        $rows = DB::table('leave_applications')->where('deleted', 'No')->where('leave_status', 'Approved')
            ->whereIn('employee_id', $employeeIds ?: [0])->where('from_date', '<=', $to)->where('to_date', '>=', $from)
            ->get(['employee_id', 'from_date', 'to_date', 'half_day']);
        foreach ($rows as $l) {
            foreach (CarbonPeriod::create(max($l->from_date, $from), min($l->to_date, $to)) as $d) {
                $leave[$l->employee_id][$d->toDateString()] = $l->half_day === 'Yes' ? 'Half' : 'Full';
            }
        }

        return ['holidays' => LeaveController::holidayMap($from, $to), 'leave' => $leave];
    }

    public static function isWeeklyOff(object $shift, string $date): bool
    {
        $d = Carbon::parse($date);

        return $shift->weekly_offs
            ? in_array($d->format('l'), $shift->weekly_offs, true)
            : in_array($d->dayOfWeekIso, LeaveController::weekendDays($d->year), true);
    }

    /** 'n/a' (not employed), 'Holiday', 'Weekly Off', 'Leave' or null for a working day. */
    public static function dayType(object $employee, object $shift, string $date, array $ctx): ?string
    {
        if ((!empty($employee->joining_date) && $date < substr($employee->joining_date, 0, 10))
            || (!empty($employee->resign_date) && $date > substr($employee->resign_date, 0, 10))) {
            return 'n/a';
        }
        if (isset($ctx['holidays'][$date])) {
            return 'Holiday';
        }
        if (self::isWeeklyOff($shift, $date)) {
            return 'Weekly Off';
        }

        return isset($ctx['leave'][$employee->id][$date]) ? 'Leave' : null;
    }

    /**
     * Status and minutes of one day from check-in / check-out ('H:i' or 'H:i:s') against the shift.
     * $forced keeps a status picked by hand (late / overtime minutes are still worked out).
     */
    public static function evaluate(object $shift, string $date, ?string $in, ?string $out, ?string $dayType, ?string $forced = null): array
    {
        $start = Carbon::parse($date . ' ' . $shift->start_time);
        $end = Carbon::parse($date . ' ' . $shift->end_time);
        if ($end->lte($start)) {
            $end->addDay();   // night shift
        }
        $inAt = $in ? Carbon::parse($date . ' ' . $in) : null;
        $outAt = $out ? Carbon::parse($date . ' ' . $out) : null;
        // Night shift: a time in the early part of the day is the next morning (e.g. in 00:30 is late, out 06:10 is overtime).
        $cutoff = self::nightCutoff($shift);
        if ($cutoff !== null) {
            if ($inAt && self::minuteOfDay($in) < $cutoff) {
                $inAt->addDay();
            }
            if ($outAt && self::minuteOfDay($out) < $cutoff) {
                $outAt->addDay();
            }
        }
        if ($inAt && $outAt && $outAt->lt($inAt)) {
            $outAt->addDay();
        }
        $worked = $inAt && $outAt ? $inAt->diffInMinutes($outAt) : 0;

        $late = $overtime = 0;
        if (in_array($dayType, ['Holiday', 'Weekly Off', 'Leave', 'n/a'], true)) {
            $status = $dayType === 'n/a' ? 'Absent' : $dayType;
            $overtime = $dayType !== 'Leave' ? $worked : 0;   // work on an off day is all overtime
        } elseif (!$inAt) {
            $status = 'Absent';
        } else {
            $lateBy = max(0, $start->diffInMinutes($inAt, false));
            $late = $lateBy > (int) $shift->grace_minutes ? $lateBy : 0;
            $shiftMinutes = $start->diffInMinutes($end);
            $half = ((int) $shift->half_day_after_minutes > 0 && $lateBy >= (int) $shift->half_day_after_minutes)
                || ($outAt && $worked < $shiftMinutes / 2);
            $status = $half ? 'Half Day' : ($late ? 'Late' : 'Present');

            if ($outAt && $outAt->gt($end)) {
                $after = $end->diffInMinutes($outAt);
                $overtime = $after >= max(1, (int) $shift->overtime_after_minutes) ? $after : 0;
            }
        }

        return [
            'attendance_status' => $forced ?: $status,
            'late_minutes'      => min($late, 65535),
            'overtime_minutes'  => min($overtime, 65535),
            'worked_minutes'    => min($worked, 65535),
        ];
    }

    /**
     * For a shift that runs past midnight (22:00 - 06:00): the minute of the day before which a punch belongs
     * to the shift that started the evening before — halfway between the shift's end and its next start.
     * Null for a day shift.
     */
    public static function nightCutoff(object $shift): ?int
    {
        $start = self::minuteOfDay($shift->start_time);
        $end = self::minuteOfDay($shift->end_time);

        return $end < $start ? $end + intdiv($start - $end, 2) : null;
    }

    private static function minuteOfDay(string $time): int
    {
        $p = explode(':', $time);

        return (int) $p[0] * 60 + (int) ($p[1] ?? 0);
    }

    /** Insert or update one attendance row. */
    public static function store(object $employee, string $date, array $data): void
    {
        $existing = DB::table('attendances')->where('employee_id', $employee->id)->where('date', $date)->value('id');
        if ($existing) {
            DB::table('attendances')->where('id', $existing)->update($data + ['updated_by' => Auth::id(), 'updated_date' => now(), 'updated_at' => now()]);
        } else {
            DB::table('attendances')->insert($data + [
                'employee_id' => $employee->id, 'branch_id' => $employee->branch_id ?? null, 'date' => $date,
                'deleted' => 'No', 'status' => 'Active',
                'created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * Rebuilds attendance days from the punch log (first punch = in, last = out) for the employees
     * and dates given. Days entered by hand (source Manual) are kept. Returns ['days' => n, 'skipped' => n].
     */
    public static function rebuildFromPunches(array $employeeIds, ?string $from, ?string $to, bool $anyBranch = false): array
    {
        if (!$employeeIds || !$from || !$to) {
            return ['days' => 0, 'skipped' => 0];
        }

        // A device can hold employees of every branch: its sync rebuilds all of them, whichever branch the user is in
        // (otherwise the punches get linked but the days of other branches' employees are never built).
        $employees = self::employeesQuery(Carbon::parse($from)->subDay()->toDateString(), $to, null, $employeeIds, !$anyBranch)->get()->keyBy('id');

        // Night shifts: a punch before the shift's cut-off belongs to the shift that started the day before,
        // so the punches of the day before $from and after $to are read too.
        $prev = Carbon::parse($from)->subDay()->toDateString();
        $punches = DB::table('attendance_punches')->whereIn('employee_id', $employeeIds)
            ->whereBetween('punched_at', [$prev . ' 00:00:00', Carbon::parse($to)->addDay()->toDateString() . ' 23:59:59'])
            ->orderBy('punched_at')->get(['employee_id', 'punched_at', 'source'])
            ->groupBy(function ($p) use ($employees) {
                $date = substr($p->punched_at, 0, 10);
                $e = $employees[$p->employee_id] ?? null;
                $cutoff = $e ? self::nightCutoff(self::shiftFor($e)) : null;
                if ($cutoff !== null && self::minuteOfDay(substr($p->punched_at, 11, 5)) < $cutoff) {
                    $date = Carbon::parse($date)->subDay()->toDateString();
                }

                return $p->employee_id . '|' . $date;
            })
            // Days in the range, plus a night shift of the day before whose punches fall in the range.
            ->filter(function ($list, $key) use ($from, $to) {
                $date = explode('|', $key)[1];

                return ($date >= $from && $date <= $to)
                    || $list->contains(fn ($p) => substr($p->punched_at, 0, 10) >= $from && substr($p->punched_at, 0, 10) <= $to);
            });

        $existing = DB::table('attendances')->whereIn('employee_id', $employeeIds)->whereBetween('date', [$prev, $to])
            ->get(['employee_id', 'date', 'source'])->keyBy(fn ($r) => $r->employee_id . '|' . $r->date);
        $ctx = self::context($prev, $to, $employeeIds);

        $days = $skipped = 0;
        foreach ($punches as $key => $list) {
            [$empId, $date] = explode('|', $key);
            $e = $employees[(int) $empId] ?? null;
            if (!$e) {
                continue;
            }
            if (($existing[$key]->source ?? null) === 'Manual') {
                $skipped++;
                continue;
            }

            $first = $list->first()->punched_at;
            $last = $list->last()->punched_at;
            $in = substr($first, 11, 8);
            $out = $list->count() > 1 && Carbon::parse($first)->diffInMinutes(Carbon::parse($last)) >= 1 ? substr($last, 11, 8) : null;

            $shift = self::shiftFor($e);
            self::store($e, $date, self::evaluate($shift, $date, $in, $out, self::dayType($e, $shift, $date, $ctx)) + [
                'check_in' => $in, 'check_out' => $out, 'status_override' => 'No', 'work_shift_id' => $shift->id,
                'source' => $list->contains('source', 'Device') ? 'Device' : 'Import',
            ]);
            $days++;
        }

        return ['days' => $days, 'skipped' => $skipped];
    }

    /**
     * Per employee, every day of the month with its status code and the month's counts.
     * A working day without a row counts as Absent only when attendance was taken that day
     * (anyone has a row for the date) — days nobody recorded show "·" and count nowhere.
     */
    public static function monthSummary(Carbon $month, ?int $departmentId = null, ?array $employeeIds = null): array
    {
        $from = $month->copy()->startOfMonth()->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();
        $today = today()->toDateString();

        $employees = self::employeesQuery($from, $to, $departmentId, $employeeIds)->get();
        $ids = $employees->pluck('id')->all();
        $rows = DB::table('attendances')->whereIn('employee_id', $ids ?: [0])->whereBetween('date', [$from, $to])->get()
            ->keyBy(fn ($r) => $r->employee_id . '|' . $r->date);
        // Only this branch's attendance tells whether attendance was taken on a day.
        $recorded = array_flip(BranchController::scope(DB::table('attendances'), 'branch_id')->whereBetween('date', [$from, $to])->distinct()->pluck('date')->all());
        $ctx = self::context($from, $to, $ids);

        $dates = [];
        foreach (CarbonPeriod::create($from, $to) as $d) {
            $dates[] = ['date' => $d->toDateString(), 'day' => $d->day, 'dow' => $d->format('D'),
                'holiday' => $ctx['holidays'][$d->toDateString()]['title'] ?? null, 'recorded' => isset($recorded[$d->toDateString()]),
                'weekend' => in_array($d->dayOfWeekIso, LeaveController::weekendDays($d->year), true)];
        }

        $out = [];
        foreach ($employees as $e) {
            $shift = self::shiftFor($e);
            $c = ['present' => 0, 'late' => 0, 'half_day' => 0, 'absent' => 0, 'leave' => 0, 'holiday' => 0, 'weekly_off' => 0, 'no_data' => 0,
                'late_minutes' => 0, 'ot_minutes' => 0];
            $days = [];
            foreach ($dates as $d) {
                $date = $d['date'];
                $r = $rows[$e->id . '|' . $date] ?? null;
                $type = self::dayType($e, $shift, $date, $ctx);

                if ($type === 'n/a') {
                    $status = null;
                } elseif ($r) {
                    $status = $r->attendance_status;
                } elseif ($type) {
                    $status = $type;
                } elseif ($date <= $today && isset($recorded[$date])) {
                    $status = 'Absent';
                } else {
                    $status = $date > $today ? null : '·';
                }

                match ($status) {
                    'Present'    => $c['present']++,
                    'Late'       => $c['late']++,
                    'Half Day'   => $c['half_day']++,
                    'Absent'     => $c['absent']++,
                    'Leave'      => $c['leave']++,
                    'Holiday'    => $c['holiday']++,
                    'Weekly Off' => $c['weekly_off']++,
                    '·'          => $c['no_data']++,
                    default      => null,
                };
                if ($r) {
                    $c['late_minutes'] += (int) $r->late_minutes;
                    $c['ot_minutes'] += (int) $r->overtime_minutes;
                }

                $days[] = [
                    'code'   => $status === '·' ? '·' : (self::CODES[$status] ?? ''),
                    'status' => $status,
                    'in'     => $r && $r->check_in ? substr($r->check_in, 0, 5) : null,
                    'out'    => $r && $r->check_out ? substr($r->check_out, 0, 5) : null,
                    'late'   => (int) ($r->late_minutes ?? 0),
                    'ot'     => (int) ($r->overtime_minutes ?? 0),
                ];
            }
            $c['attended'] = $c['present'] + $c['late'] + $c['half_day'];
            $c['ot_hours'] = round($c['ot_minutes'] / 60, 2);

            $out[] = [
                'employee_id' => $e->id, 'code' => $e->employee_code, 'name' => $e->name, 'designation' => $e->designation,
                'department' => $e->department, 'shift' => $shift->name, 'days' => $days, 'counts' => $c,
                'basic_salary' => (float) $e->basic_salary, 'gross_salary' => (float) $e->gross_salary,
            ];
        }

        return ['month' => $month->format('Y-m'), 'month_label' => $month->format('F Y'), 'dates' => $dates, 'employees' => $out];
    }

    /**
     * What "Send to salary" would write for each employee of the month:
     * absence (absent + ½ per half day) × daily rate, late fine (lates ÷ N, whole days) × daily rate,
     * overtime hours × multiplier × gross / days / hours per day.
     */
    public static function salaryLines(Carbon $month, ?int $departmentId = null): array
    {
        $month = $month->copy()->startOfMonth();
        $s = self::settings();
        $summary = self::monthSummary($month, $departmentId);
        $processed = array_flip(PayrollRunController::processedIds($month));
        $existing = DB::table('salary_adjustments')->where('month', $month->toDateString())->where('deleted', 'No')
            ->where('remarks', 'like', self::REMARK_PREFIX . '%')->groupBy('employee_id')
            ->selectRaw('employee_id, COUNT(*) as n')->pluck('n', 'employee_id');
        $employees = DB::table('employees')->whereIn('id', array_column($summary['employees'], 'employee_id') ?: [0])->get()->keyBy('id');

        $lines = [];
        foreach ($summary['employees'] as $row) {
            $c = $row['counts'];
            $e = $employees[$row['employee_id']];
            $daily = SalaryAdjustmentController::dailyRate($e, $month);
            $hourly = round((float) $e->gross_salary / $month->daysInMonth / max(1, (int) $s->hours_per_day), 2);

            $absenceDays = $s->absence_deduction === 'Yes' ? $c['absent'] + $c['half_day'] * 0.5 : 0;
            $lateDays = (int) $s->lates_per_day > 0 ? intdiv($c['late'], (int) $s->lates_per_day) : 0;
            $otHours = $s->overtime_pay === 'Yes' ? $c['ot_hours'] : 0;

            $lines[$row['employee_id']] = [
                'employee_id'    => $row['employee_id'],
                'code'           => $row['code'],
                'name'           => $row['name'],
                'department'     => $row['department'],
                'processed'      => isset($processed[$row['employee_id']]),
                'replaces'       => (int) ($existing[$row['employee_id']] ?? 0),
                'absent'         => $c['absent'],
                'half_day'       => $c['half_day'],
                'late'           => $c['late'],
                'leave'          => $c['leave'],
                'no_data'        => $c['no_data'],
                'daily_rate'     => $daily,
                'hourly_rate'    => $hourly,
                'ot_multiplier'  => (float) $s->overtime_multiplier,
                'absence_days'   => $absenceDays,
                'absence_amount' => round($daily * $absenceDays, 2),
                'late_days'      => $lateDays,
                'late_amount'    => round($daily * $lateDays, 2),
                'ot_hours'       => $otHours,
                'ot_amount'      => round($otHours * (float) $s->overtime_multiplier * $hourly, 2),
            ];
        }

        return $lines;
    }

    /* ------------------------------------------------------------------ */
    /*  File reading helpers                                               */
    /* ------------------------------------------------------------------ */

    public static function importPath(?string $token): string
    {
        return storage_path('app/attendance-imports/' . basename((string) $token));
    }

    /** All rows of the first sheet as raw values (dates stay Excel serials). */
    public static function readSheet(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['csv', 'txt'], true)) {
            $reader = new Csv();
            $head = (string) file_get_contents($path, false, null, 0, 4096);
            $reader->setDelimiter(collect([',', ';', "\t", '|'])->sortByDesc(fn ($d) => substr_count($head, $d))->first());
            $reader->setInputEncoding(Csv::GUESS_ENCODING);
        } else {
            $reader = IOFactory::createReaderForFile($path);
        }
        $reader->setReadDataOnly(true);
        $rows = $reader->load($path)->getActiveSheet()->toArray(null, true, false, false);

        return array_values(array_filter($rows, fn ($r) => array_filter($r, fn ($v) => $v !== null && trim((string) $v) !== '')));
    }

    /** Column guess from the header row: ZKTeco "No., Name, Date/Time" or "AC-No., Date, Time". */
    public static function guessMapping(array $rows): array
    {
        $head = array_map(fn ($v) => strtolower(trim((string) $v)), $rows[0] ?? []);
        $find = function (array $patterns) use ($head) {
            foreach ($patterns as $p) {
                foreach ($head as $i => $h) {
                    if ($h !== '' && preg_match($p, $h)) {
                        return $i;
                    }
                }
            }

            return null;
        };

        $code = $find(['/employee.?code|emp.?code|staff.?code/']);
        $device = $find(['/ac-?no|enroll|user.?id|^no\.?$|badge|card|^id$|person.?id/']);
        $dt = $find(['/date.?\/?.?time|datetime|timestamp|punch.?time|check.?time|log.?time/']);
        $date = $find(['/^date$|^day$|att.?date/']);
        $time = $find(['/^time$|clock|in.?time/']);
        $isHeader = $code !== null || $device !== null || $dt !== null || $date !== null;

        return [
            'header'     => $isHeader,
            'id_col'     => $code ?? $device ?? 0,
            'id_type'    => $code !== null ? 'code' : 'device',
            'mode'       => $dt === null && $date !== null && $time !== null ? 'split' : 'datetime',
            'dt_col'     => $dt ?? $date ?? min(1, count($head) - 1),
            'date_col'   => $date,
            'time_col'   => $time,
            'date_order' => 'auto',
        ];
    }

    /** 'Y-m-d H:i:s' (or 'Y-m-d' with $dateOnly) from an Excel serial or a text date. */
    public static function parseDateTime($value, string $order = 'auto', bool $dateOnly = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            // whole days + seconds rounded (Excel stores time as a fraction: 18:10 can read back as 18:09:59.99)
            $days = (int) floor((float) $value);
            $dt = Carbon::instance(ExcelDate::excelToDateTimeObject($days))->startOfDay()->addSeconds((int) round(((float) $value - $days) * 86400));

            return $dateOnly ? $dt->toDateString() : $dt->format('Y-m-d H:i:s');
        }

        $v = trim(preg_replace('/\s+/', ' ', (string) $value));
        if (!preg_match('/^(\d{1,4})[-\/.](\d{1,2})[-\/.](\d{1,4})(?:[ T]+(\d{1,2}):(\d{2})(?::(\d{2}))?(?:\.\d+)?\s*([AaPp]\.?[Mm]\.?)?)?$/', $v, $m)) {
            try {
                $dt = Carbon::parse($v);

                return $dateOnly ? $dt->toDateString() : $dt->format('Y-m-d H:i:s');
            } catch (Throwable) {
                return null;
            }
        }

        [$a, $b, $c] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if (strlen($m[1]) === 4 || $order === 'YMD') {
            [$y, $mo, $d] = [$a, $b, $c];
        } elseif ($order === 'MDY' || ($order === 'auto' && $a <= 12 && $b > 12)) {
            [$mo, $d, $y] = [$a, $b, $c];
        } else {
            [$d, $mo, $y] = [$a, $b, $c];
        }
        if ($y < 100) {
            $y += 2000;
        }
        if (!checkdate($mo, $d, $y)) {
            return null;
        }
        if ($dateOnly || !isset($m[4]) || $m[4] === '') {
            return $dateOnly ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : sprintf('%04d-%02d-%02d 00:00:00', $y, $mo, $d);
        }

        $h = (int) $m[4];
        $ampm = strtolower(str_replace('.', '', $m[7] ?? ''));
        if ($ampm === 'pm' && $h < 12) {
            $h += 12;
        } elseif ($ampm === 'am' && $h === 12) {
            $h = 0;
        }
        if ($h > 23 || (int) $m[5] > 59) {
            return null;
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $mo, $d, $h, (int) $m[5], (int) ($m[6] ?? 0));
    }

    /** 'H:i:s' from an Excel time fraction or text like "9:05", "09:05:33", "6:10 PM". */
    public static function parseTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            $f = fmod((float) $value, 1.0);
            $sec = (int) round($f * 86400) % 86400;

            return sprintf('%02d:%02d:%02d', intdiv($sec, 3600), intdiv($sec % 3600, 60), $sec % 60);
        }
        if (!preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([AaPp]\.?[Mm]\.?)?/', (string) $value, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $ampm = strtolower(str_replace('.', '', $m[4] ?? ''));
        if ($ampm === 'pm' && $h < 12) {
            $h += 12;
        } elseif ($ampm === 'am' && $h === 12) {
            $h = 0;
        }

        return $h <= 23 && (int) $m[2] <= 59 ? sprintf('%02d:%02d:%02d', $h, (int) $m[2], (int) ($m[3] ?? 0)) : null;
    }

    private static function combine(?string $date, ?string $time): ?string
    {
        return $date && $time ? $date . ' ' . $time : null;
    }

    private static function num($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }

    /* ------------------------------------------------------------------ */

    private function departments()
    {
        return DB::table('departments')->where('deleted', 'No')->orderBy('name')->get(['id', 'name']);
    }

    private function dateParam(?string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) && strtotime($value) ? $value : today()->toDateString();
    }

    /** [month Carbon, department id|null, employee id|null] from the query / body. */
    private function monthFilters(Request $request): array
    {
        $m = (string) $request->input('month', $request->query('month'));
        $month = preg_match('/^\d{4}-\d{2}$/', $m) ? Carbon::createFromFormat('!Y-m', $m) : now()->startOfMonth();

        return [
            $month,
            $request->filled('department_id') ? (int) $request->input('department_id') : null,
            $request->filled('employee_id') ? (int) $request->input('employee_id') : null,
        ];
    }
}
