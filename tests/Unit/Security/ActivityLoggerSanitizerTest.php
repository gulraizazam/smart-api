<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Helpers\ActivityLogger;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Regression tests for ActivityLogger::sanitizeDescription (Round 4
 * Inj-C1 fix).
 *
 * Background: every method in ActivityLogger concatenates user-supplied
 * name fragments (`$creatorName`, `$patientName`, `$serviceName`,
 * `$locationName`, `$package->name`, etc.) into an HTML template that
 * the activity log view at activities.blade.php renders raw via
 * `{!! $activity['message'] !!}`. The sanitizer is the producer-side
 * mitigation: it tokenises the built description and keeps only a tiny
 * whitelist of legitimate `<span class="highlight*">` markers, escaping
 * everything else.
 *
 * The test reaches the private static method via reflection so we don't
 * need a database connection.
 */
class ActivityLoggerSanitizerTest extends TestCase
{
    private static function sanitize(string $html): string
    {
        $ref = new ReflectionMethod(ActivityLogger::class, 'sanitizeDescription');
        $ref->setAccessible(true);

        return $ref->invoke(null, $html);
    }

    public function test_legitimate_highlight_span_passes_through(): void
    {
        // The exact shape produced by the templates — must round-trip
        // unchanged so the activity log keeps its highlight styling.
        $input = '<span class="highlight">John Doe</span> created a lead';
        $this->assertSame($input, self::sanitize($input));
    }

    public function test_all_whitelisted_classes_pass(): void
    {
        $classes = ['highlight', 'highlight-orange', 'highlight-green', 'highlight-blue', 'highlight-red'];
        foreach ($classes as $cls) {
            $input = '<span class="' . $cls . '">x</span>';
            $this->assertSame($input, self::sanitize($input), "class={$cls}");
        }
    }

    public function test_script_tag_is_escaped(): void
    {
        // The whole point — a patient name like `<script>alert(1)</script>`
        // must NOT survive as executable HTML.
        $input = '<span class="highlight"><script>alert(1)</script></span>';
        $output = self::sanitize($input);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
        // The legit highlight span around it still wraps the (now-escaped) text.
        $this->assertStringStartsWith('<span class="highlight">', $output);
    }

    public function test_img_onerror_payload_is_escaped(): void
    {
        // The other classic stored-XSS vector.
        $input = '<img src=x onerror=alert(1)>';
        $output = self::sanitize($input);

        $this->assertStringNotContainsString('<img', $output);
        $this->assertStringContainsString('&lt;img', $output);
    }

    public function test_unknown_span_class_is_escaped_not_kept(): void
    {
        // A `<span class="malicious">` is suspicious — escape rather
        // than render. Defense in depth even though no template uses
        // anything outside the whitelist.
        $input = '<span class="malicious">payload</span>';
        $output = self::sanitize($input);

        $this->assertStringContainsString('&lt;span class=&quot;malicious&quot;&gt;', $output);
        $this->assertStringNotContainsString('<span class="malicious">', $output);
    }

    public function test_bare_span_is_escaped(): void
    {
        // `<span>` with no class — outside the whitelist, treat as text.
        $input = '<span>plain</span>';
        $output = self::sanitize($input);

        $this->assertStringNotContainsString('<span>plain</span>', $output);
        $this->assertStringContainsString('&lt;span&gt;', $output);
    }

    public function test_span_with_inline_style_is_escaped(): void
    {
        // An attacker injecting `<span style="background:url(javascript:...)">` —
        // the style attribute is suspicious; treat the whole tag as text.
        $input = '<span style="color:red">x</span>';
        $output = self::sanitize($input);

        $this->assertStringNotContainsString('<span style="color:red">', $output);
        $this->assertStringContainsString('&lt;span', $output);
    }

    public function test_legit_text_with_quotes_is_escaped(): void
    {
        // Quoted text outside spans must be HTML-escaped so it cannot
        // break out of an attribute context.
        $input = 'Patient O&apos;Reilly visited "Main" branch';
        $output = self::sanitize($input);

        // Already-escaped entities get re-escaped (& → &amp;) — correct
        // because the consumer renders with {!!}.
        $this->assertStringContainsString('&amp;apos;', $output);
        $this->assertStringContainsString('&quot;Main&quot;', $output);
    }

    public function test_br_tag_is_normalised_and_kept(): void
    {
        // <br> shows up in some descriptions; allow but normalise.
        $output = self::sanitize('line1<br>line2<br/>line3');

        $this->assertStringContainsString('<br>', $output);
        $this->assertStringContainsString('line1', $output);
        $this->assertStringContainsString('line2', $output);
        $this->assertStringContainsString('line3', $output);
    }

    public function test_full_template_with_injected_payload(): void
    {
        // Realistic payload: an attacker named themselves
        // `<script>fetch('//evil')</script>` and the producer concatenated
        // it into the standard "X created a Y" template.
        $input = '<span class="highlight"><script>fetch(\'//evil\')</script></span> created a <span class="highlight-orange">Service</span> lead';
        $output = self::sanitize($input);

        // The dangerous tag itself must be gone — the literal text
        // `fetch(` may still appear as escaped content, but it can no
        // longer execute because it lives inside `&lt;script&gt;...&lt;/script&gt;`.
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
        $this->assertStringContainsString('&lt;/script&gt;', $output);
        // The legit template markers survive.
        $this->assertStringContainsString('<span class="highlight">', $output);
        $this->assertStringContainsString('<span class="highlight-orange">', $output);
        $this->assertStringContainsString('Service', $output);
    }

    public function test_plain_text_description_is_escaped(): void
    {
        // The Consultation Booked / Package Created / Refund Made paths
        // build descriptions with NO span markers — those are pure
        // plaintext and must be HTML-escaped end-to-end.
        $input = 'Consultation Booked for <script>alert(1)</script> at Main';
        $output = self::sanitize($input);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('Consultation Booked', $output);
    }
}
