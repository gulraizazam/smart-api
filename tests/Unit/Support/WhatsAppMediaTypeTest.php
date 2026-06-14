<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\WhatsAppMediaType;
use PHPUnit\Framework\TestCase;

/**
 * Pins the mime → WhatsApp media category mapping and the supported-type gate.
 */
class WhatsAppMediaTypeTest extends TestCase
{
    public function test_maps_mime_to_the_whatsapp_category(): void
    {
        $this->assertSame('image', WhatsAppMediaType::fromMime('image/jpeg'));
        $this->assertSame('video', WhatsAppMediaType::fromMime('video/mp4'));
        $this->assertSame('audio', WhatsAppMediaType::fromMime('audio/ogg'));
        $this->assertSame('document', WhatsAppMediaType::fromMime('application/pdf'));
        $this->assertSame('document', WhatsAppMediaType::fromMime('application/octet-stream'));
    }

    public function test_supports_media_meta_accepts_and_rejects_arbitrary_binaries(): void
    {
        foreach (['image/jpeg', 'image/png', 'video/mp4', 'audio/ogg', 'application/pdf', 'text/csv'] as $mime) {
            $this->assertTrue(WhatsAppMediaType::isSupported($mime), "expected supported: {$mime}");
        }
        foreach (['application/x-msdownload', 'application/octet-stream', 'application/x-sh'] as $mime) {
            $this->assertFalse(WhatsAppMediaType::isSupported($mime), "expected unsupported: {$mime}");
        }
    }
}
