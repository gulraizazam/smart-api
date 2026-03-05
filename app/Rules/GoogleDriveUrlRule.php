<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class GoogleDriveUrlRule implements Rule
{
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

        return (bool) preg_match($pattern, $value);
    }

    public function message(): string
    {
        return 'The :attribute must be a valid Google Drive URL.';
    }
}
