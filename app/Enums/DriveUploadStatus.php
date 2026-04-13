<?php

declare(strict_types=1);

namespace App\Enums;

enum DriveUploadStatus: string
{
    case Pending = 'pending';
    case Uploaded = 'uploaded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Upload',
            self::Uploaded => 'Uploaded',
            self::Failed => 'Upload Failed',
        };
    }
}
