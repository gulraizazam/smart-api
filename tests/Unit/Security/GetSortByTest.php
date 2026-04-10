<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Regression tests for the global `getSortBy()` helper.
 *
 * Background: the helper is invoked from 70+ list controllers and feeds
 * its output directly into Eloquent `orderBy()`. Eloquent does NOT escape
 * the column name — it interpolates it raw into `ORDER BY <col>`. The
 * helper is therefore the single chokepoint protecting every datatable
 * page from SQL injection via the `sort[field]` parameter.
 *
 * If any of these tests start failing, attackers can pivot back into
 * `UNION SELECT` style data exfiltration on every list page in the app.
 */
class GetSortByTest extends TestCase
{
    public function test_defaults_apply_when_sort_is_absent(): void
    {
        $request = Request::create('/', 'GET');
        [$col, $dir] = getSortBy($request, 'id', 'desc');

        $this->assertSame('id', $col);
        $this->assertSame('desc', $dir);
    }

    public function test_simple_column_passes(): void
    {
        $request = Request::create('/', 'GET', [
            'sort' => ['field' => 'created_at', 'sort' => 'asc'],
        ]);
        [$col, $dir] = getSortBy($request);

        $this->assertSame('created_at', $col);
        $this->assertSame('asc', $dir);
    }

    public function test_prefix_dot_column_passes(): void
    {
        // Joined queries need `table.column` ordering. Allowed.
        $request = Request::create('/', 'GET', [
            'sort' => ['field' => 'users.email', 'sort' => 'desc'],
        ]);
        [$col, $dir] = getSortBy($request);

        $this->assertSame('users.email', $col);
        $this->assertSame('desc', $dir);
    }

    public function test_direction_is_case_insensitive(): void
    {
        $request = Request::create('/', 'GET', [
            'sort' => ['field' => 'id', 'sort' => 'DESC'],
        ]);
        [$col, $dir] = getSortBy($request);

        $this->assertSame('desc', $dir);
    }

    public function test_drop_table_payload_is_rejected(): void
    {
        $request = Request::create('/', 'GET', [
            'sort' => [
                'field' => 'name); DROP TABLE users; --',
                'sort' => 'asc',
            ],
        ]);
        [$col, $dir] = getSortBy($request, 'id', 'asc');

        // Falls back to caller-provided defaults — never the malicious value.
        $this->assertSame('id', $col);
        $this->assertSame('asc', $dir);
    }

    public function test_union_select_payload_is_rejected(): void
    {
        $request = Request::create('/', 'GET', [
            'sort' => [
                'field' => '1 UNION SELECT password FROM users',
                'sort' => 'asc',
            ],
        ]);
        [$col, $dir] = getSortBy($request, 'id', 'asc');

        $this->assertSame('id', $col);
    }

    public function test_backtick_payload_is_rejected(): void
    {
        $request = Request::create('/', 'GET', [
            'sort' => ['field' => 'name`', 'sort' => 'asc'],
        ]);
        [$col, $dir] = getSortBy($request, 'id');

        $this->assertSame('id', $col);
    }

    public function test_direction_injection_is_rejected(): void
    {
        $request = Request::create('/', 'GET', [
            'sort' => ['field' => 'id', 'sort' => 'asc; DROP TABLE x'],
        ]);
        [$col, $dir] = getSortBy($request, 'id', 'desc');

        // Bad direction → fall back to caller default.
        $this->assertSame('desc', $dir);
    }

    public function test_subquery_payload_is_rejected(): void
    {
        $request = Request::create('/', 'GET', [
            'sort' => [
                'field' => '(SELECT password FROM users WHERE id=1)',
                'sort' => 'asc',
            ],
        ]);
        [$col, $dir] = getSortBy($request, 'id');

        $this->assertSame('id', $col);
    }

    public function test_comma_separated_columns_are_rejected(): void
    {
        // ORDER BY with multiple comma-separated columns is also a
        // common injection surface. Reject anything containing a comma.
        $request = Request::create('/', 'GET', [
            'sort' => ['field' => 'name, password', 'sort' => 'asc'],
        ]);
        [$col, $dir] = getSortBy($request, 'id');

        $this->assertSame('id', $col);
    }

    public function test_prefix_arg_only_applies_to_created_at(): void
    {
        // Behavioural test: the third arg appends `<prefix>.created_at`
        // when the chosen column is exactly `created_at`. This shape is
        // depended on by patient/appointment list joins — keep it stable.
        $request = Request::create('/', 'GET', [
            'sort' => ['field' => 'created_at', 'sort' => 'asc'],
        ]);
        [$col, $dir] = getSortBy($request, 'id', 'asc', 'patients');

        $this->assertSame('patients.created_at', $col);
    }
}
