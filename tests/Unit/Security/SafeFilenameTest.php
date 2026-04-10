<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Support\SafeFilename;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for App\Support\SafeFilename.
 *
 * The helper exists to defang the browser-supplied client filename before
 * it lands in any storage path. Each test below corresponds to a real
 * attack class observed in upload bug bounties — keep them green or we
 * regress on filename injection across every upload handler in the app.
 */
class SafeFilenameTest extends TestCase
{
    public function test_simple_name_passes_unchanged(): void
    {
        $this->assertSame('hello.jpg', SafeFilename::sanitize('hello.jpg'));
    }

    public function test_spaces_become_underscores(): void
    {
        $this->assertSame('hello_world.jpg', SafeFilename::sanitize('hello world.jpg'));
    }

    public function test_extension_is_lowercased(): void
    {
        $this->assertSame('photo.jpg', SafeFilename::sanitize('photo.JPG'));
    }

    public function test_unix_path_traversal_is_stripped(): void
    {
        // basename() must remove `../../etc/` so the result is just the
        // leaf, with the dangerous extension replaced by the fallback.
        $this->assertSame('passwd.bin', SafeFilename::sanitize('../../etc/passwd'));
    }

    public function test_windows_path_traversal_is_stripped(): void
    {
        $this->assertSame(
            'evil.exe',
            SafeFilename::sanitize('..\\..\\windows\\system32\\evil.exe'),
        );
    }

    public function test_nul_byte_extension_bypass_is_blocked(): void
    {
        // Classic `evil.php\x00.jpg` trick: PHP filesystem ops truncate at
        // NUL, so a naive extension check on `.jpg` lets the file land as
        // `.php`. We strip NUL bytes outright.
        $result = SafeFilename::sanitize("evil.php\0.jpg");
        $this->assertStringNotContainsString("\0", $result);
        $this->assertSame('evil.php.jpg', $result);
    }

    public function test_dotfile_attempts_are_neutralised(): void
    {
        // Leading dots get trimmed; runs of dots get collapsed. The
        // attacker cannot create a `.htaccess` or `.env`-shaped file.
        $result = SafeFilename::sanitize('....htaccess');
        $this->assertStringStartsNotWith('.', $result);
        $this->assertSame('file.htaccess', $result);
    }

    public function test_empty_input_returns_safe_placeholder(): void
    {
        $this->assertSame('file.bin', SafeFilename::sanitize(''));
    }

    public function test_oversized_name_is_truncated(): void
    {
        $huge = str_repeat('a', 1000) . '.jpg';
        $result = SafeFilename::sanitize($huge);

        // Stays under the 120-char limit AND keeps the extension intact.
        $this->assertLessThanOrEqual(120, strlen($result));
        $this->assertStringEndsWith('.jpg', $result);
    }

    public function test_special_characters_become_underscores(): void
    {
        $this->assertSame(
            'spec_________.pdf',
            SafeFilename::sanitize('spec!@#%^&*().pdf'),
        );
    }

    public function test_extension_allowlist_blocks_executable(): void
    {
        // The classic upload-an-RCE trick: the user uploads `evil.php`
        // pretending it's an avatar. With an image allowlist the
        // extension is replaced with the safe fallback.
        $result = SafeFilename::sanitize('evil.php', ['jpg', 'png', 'gif']);
        $this->assertSame('evil.bin', $result);
    }

    public function test_extension_allowlist_passes_legit_image(): void
    {
        $result = SafeFilename::sanitize('avatar.PNG', ['jpg', 'png', 'gif']);
        $this->assertSame('avatar.png', $result);
    }

    public function test_double_extension_only_keeps_last(): void
    {
        // `evil.tar.gz` → only `gz` is treated as the extension, which is
        // the same behaviour every browser/OS uses for MIME sniffing.
        $this->assertSame(
            'dual.tar.gz',
            SafeFilename::sanitize('dual.tar.gz', ['gz']),
        );
    }

    public function test_trailing_dot_is_trimmed(): void
    {
        // Windows silently strips trailing dots and creates the file
        // anyway, defeating extension checks. We trim them so the
        // checked extension matches what actually lands on disk.
        $result = SafeFilename::sanitize('trailing.dot.');
        $this->assertStringEndsNotWith('.', $result);
    }

    public function test_underscore_in_basename_is_preserved(): void
    {
        // Underscores are part of the allowed character class — make
        // sure we are not accidentally stripping them.
        $this->assertSame(
            'snake_case_name.jpg',
            SafeFilename::sanitize('snake_case_name.jpg'),
        );
    }

    public function test_hyphen_in_basename_is_preserved(): void
    {
        $this->assertSame(
            'kebab-case-name.jpg',
            SafeFilename::sanitize('kebab-case-name.jpg'),
        );
    }
}
