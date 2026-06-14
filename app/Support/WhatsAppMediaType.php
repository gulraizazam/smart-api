<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Maps an upload's mime type to the WhatsApp Cloud API media category
 * (image | video | audio | document) and gates which types may be sent.
 * Images/videos/audio are accepted broadly (Meta validates the specifics);
 * documents are an explicit allowlist so we don't forward arbitrary binaries.
 */
final class WhatsAppMediaType
{
    /** @var list<string> */
    private const DOCUMENT_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
    ];

    /** @return 'image'|'video'|'audio'|'document' */
    public static function fromMime(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'document',
        };
    }

    public static function isSupported(string $mime): bool
    {
        return str_starts_with($mime, 'image/')
            || str_starts_with($mime, 'video/')
            || str_starts_with($mime, 'audio/')
            || in_array($mime, self::DOCUMENT_MIMES, true);
    }
}
