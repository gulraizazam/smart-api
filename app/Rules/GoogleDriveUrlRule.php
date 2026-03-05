<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class GoogleDriveUrlRule implements Rule
{
    private bool $isGoogleDrive = true;

    public function passes($attribute, $value): bool
    {
        if (empty($value)) {
            return true;
        }

        // Match Google Drive URLs:
        // https://drive.google.com/file/d/{id}/...
        // https://drive.google.com/open?id={id}
        // https://docs.google.com/...
        $pattern = '/^https:\/\/(drive|docs)\.google\.com\//i';

        $this->isGoogleDrive = (bool) preg_match($pattern, $value);

        // Sec 11.3: Non-matching URL = warning, not block. Log it.
        if (!$this->isGoogleDrive) {
            \Illuminate\Support\Facades\Log::warning('CashFlow: Non-Google-Drive attachment URL submitted', [
                'url' => $value,
                'user_id' => auth()->id(),
            ]);
        }

        // Always passes — warning only, not a blocker
        return true;
    }

    public function message(): string
    {
        return 'The :attribute must be a valid Google Drive URL.';
    }
}
