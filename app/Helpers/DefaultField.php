<?php

declare(strict_types=1);
namespace App\Helpers;

class DefaultField
{
    public const FIELD_CONTENT = [
        'title' => self::TITLE,
        'options' => [
            ['label' => self::OPTION_TITLE],
        ],
    ];

    public const TITLE = 'Question';

    public const OPTION_TITLE = 'Option';

    const DESCRIPTION = "Question's Description";
}
