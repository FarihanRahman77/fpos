<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SeedsApp;

/**
 * Base for tests that need the app's data: fresh fpos_testing schema once per run (RefreshDatabase, from the
 * schema dump + later migrations), then every test inside a transaction with the SeedsApp fixtures.
 * No real email or SMS ever leaves: Mail::fake() and Http::fake().
 */
abstract class AppTestCase extends TestCase
{
    use RefreshDatabase;
    use SeedsApp;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Http::fake();
        DB::statement('SET @fpos_branch = NULL');
        self::resetAppStatics();
        $this->seedApp();
        self::resetAppStatics();
    }

    /** Every HTTP call starts with clean per-request caches, as a real request does. */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        self::resetAppStatics();
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        self::resetAppStatics();

        return $response;
    }

    /** POST as an XHR expecting JSON (how the admin screens call the store / delete endpoints). */
    protected function ajaxPost(string $uri, array $data = [])
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->post($uri, $data);
    }

    protected function ajaxGet(string $uri)
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->get($uri);
    }

    /** Fails when a response body leaks SQL / stack details. */
    protected function assertNoSqlLeak($response): void
    {
        $body = (string) $response->getContent();
        foreach (['SQLSTATE', 'select * from', 'insert into', 'Integrity constraint', 'Stack trace', '#0 '] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $body, 'Response leaks internal details: ' . $needle);
        }
    }
}
