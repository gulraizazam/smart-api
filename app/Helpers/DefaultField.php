<?php
/**
 * Created by PhpStorm.
 * User: shehbaz@redsignal@biz
 * Date: 6/27/18
 * Time: 4:13 PM
 */


namespace App\Helpers;

use Illuminate\Http\Request;

class DefaultField
{

    public const FIELD_CONTENT = array(
        "title" => self::TITLE,
        "options" => array(
            array("label" => self::OPTION_TITLE)
        )
    );
    public const TITLE = 'Question';
    public const OPTION_TITLE = 'Option';
    const DESCRIPTION = "Question's Description";
}
