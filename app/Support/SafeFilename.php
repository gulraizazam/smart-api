<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralised sanitisation for filenames that come from `UploadedFile::
 * getClientOriginalName()`. The browser-supplied filename is fully attacker
 * controlled — it can contain `..\..\`, `/etc/passwd`, NUL bytes, RTL
 * unicode tricks, and characters that confuse the underlying filesystem
 * (`*`, `?`, `<`, `>`, `|`, `:`, `"` on Windows; trailing dot/space on
 * Windows; `;` and `$` in shell pipelines).
 *
 * Several upload handlers were observed concatenating the raw client name
 * straight into a target path, e.g.:
 *
 *     $fileName = time() . '-' . $file->getClientOriginalName();
 *     $file->storeAs('public/centre_logo', $fileName);
 *
 * This helper exists so every such call site can route through one
 * audit-able choke point.
 */
final class SafeFilename
{
    /**
     * Maximum length of the returned filename (basename + extension). Long
     * enough for any reasonable upload, short enough to stay under the
     * common 255-byte filesystem limit even after a `time()-` prefix.
     */
    private const MAX_LENGTH = 120;

    /**
     * Sanitise a user-supplied filename to a safe basename.
     *
     * Steps:
     *  1. Run `basename()` to strip any directory components attackers
     *     embed via `../` or backslashes.
     *  2. Reject NUL bytes outright (PHP filesystem ops truncate at NUL).
     *  3. Lowercase the extension and validate it against an optional
     *     allowlist; fall back to `bin` when the extension is missing or
     *     denied.
     *  4. Replace any character outside `[A-Za-z0-9._-]` with `_`.
     *  5. Collapse runs of dots to a single dot (defends `....htaccess`).
     *  6. Trim leading dots/dashes so the file can never look like a
     *     dotfile or option flag.
     *  7. Truncate to MAX_LENGTH while preserving the extension.
     *
     * @param  string  $rawName        The untrusted client name.
     * @param  array<int,string>|null  $allowedExtensions Lowercase list
     *         (without leading dot). Pass null to skip extension policy.
     * @param  string  $fallbackExt    Extension to use when the original
     *         is missing or rejected.
     */
    public static function sanitize(
        string $rawName,
        ?array $allowedExtensions = null,
        string $fallbackExt = 'bin',
    ): string {
        // Step 1 — strip directory traversal. basename() handles both `/`
        // and `\` on every platform; we also normalise backslashes first
        // because PHP basename() on Linux ignores `\`.
        $name = basename(str_replace('\\', '/', $rawName));

        // Step 2 — reject NUL bytes. There is no legitimate filename that
        // contains them, and PHP fs ops silently truncate, allowing
        // `evil.php\x00.jpg` style bypasses.
        if (str_contains($name, "\0")) {
            $name = str_replace("\0", '', $name);
        }

        // Step 3 — split basename and extension.
        $extension = '';
        $dotPos = strrpos($name, '.');
        if ($dotPos !== false && $dotPos > 0) {
            $extension = strtolower(substr($name, $dotPos + 1));
            $name = substr($name, 0, $dotPos);
        }

        // Allowlist enforcement — fall back to a safe extension when the
        // caller specified a policy and the upload doesn't comply.
        if ($extension === '') {
            $extension = $fallbackExt;
        }
        if ($allowedExtensions !== null && !in_array($extension, $allowedExtensions, true)) {
            $extension = $fallbackExt;
        }

        // Sanitise the extension itself — only [a-z0-9] is permitted, max
        // 10 chars. Anything else gets replaced with the fallback.
        if (preg_match('/^[a-z0-9]{1,10}$/', $extension) !== 1) {
            $extension = $fallbackExt;
        }

        // Step 4 — replace any disallowed character in the basename with
        // an underscore. This is the core of the filter.
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? '';

        // Step 5 — collapse runs of dots so `....htaccess` becomes
        // `.htaccess`, then step 6 trims leading dots so even that becomes
        // `htaccess`.
        $name = preg_replace('/\.{2,}/', '.', $name) ?? '';

        // Step 6 — trim leading dots, dashes, underscores; trailing dots
        // (Windows quirk that silently strips them and creates the file
        // anyway, defeating extension allowlists).
        $name = ltrim($name, '.-_');
        $name = rtrim($name, '.');

        // After all that we may be left with an empty string — substitute
        // a stable placeholder so the caller always gets something usable.
        if ($name === '') {
            $name = 'file';
        }

        // Step 7 — truncate. Keep room for `.<extension>`.
        $maxBase = self::MAX_LENGTH - strlen($extension) - 1;
        if ($maxBase > 0 && strlen($name) > $maxBase) {
            $name = substr($name, 0, $maxBase);
        }

        return $name . '.' . $extension;
    }
}
