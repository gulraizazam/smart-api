<?php
/**
 * Created by PhpStorm.
 * User: shehbaz@redsignal@biz
 * Date: 6/27/18
 * Time: 4:13 PM
 */


namespace App\Helpers;

use Illuminate\Http\Request;

class CustomFormHelper
{

    /**
     * It will field string content to proper array
     * @param $content json string
     * @return array
     */
    public static function getContentArrayTest($content)
    {

        $data = array(
            "title" => "what are your skills? ",
            "options" => array(
                array("label" => "Adobe"),
                array("label" => "programming")
            )
        );
        return $data;
    }

    /**
     * It will field string content to proper array
     * @param $content json string
     * @return array
     */
    public static function getContentArray($content)
    {

        $json_content = json_decode($content, true);
        if (isset($json_content) && is_array($json_content)) {
            $data = [];
            if (array_key_exists('title', $json_content) && isset($json_content["title"])) {
                $data["title"] = $json_content["title"];
            } else {
                $data["title"] = DefaultField::FIELD_CONTENT["title"];
            }

            if (array_key_exists('rows', $json_content) && isset($json_content["rows"])) {
                $data["rows"] = $json_content["rows"];
            } else {
                $data["rows"] = 0;
            }

            if (array_key_exists("options", $json_content) && is_array($json_content["options"])) {
                $options = [];
                foreach ($json_content["options"] as $option) {
                    if (is_array($option["label"])) {
                        try {
                            $options[] = array("label" => $option["label"]["text"]);
                        } catch (\Exception $e) {
                            $options[] = array("label" => DefaultField::OPTION_TITLE . "- except");
                        }
                    } else {
                        $options[] = array("label" => DefaultField::OPTION_TITLE . " - not");
                        break;
                    }
                }
                $data["options"] = $options;
            } else {
                $options = [];
                $options[] = array("label" => DefaultField::OPTION_TITLE);
                $data["options"] = $options;
            }
            return $data;
        } else {
            return DefaultField::FIELD_CONTENT;
        }

    }

    public static function getContentJson(Request $request)
    {
        $data = [];
        if ($request->has("question"))
            $data["title"] = $request->get("question");
        else
            $data["title"] = DefaultField::TITLE;

        if($request->has("rows"))
            $data["rows"] = $request->get("rows");
        else
            $data["rows"] = 0;

        if ($request->has("description"))
            $data["description"] = $request->get("description");
        else
            $data["description"] = DefaultField::DESCRIPTION;


        if ($request->has('field')) {
            $options = $request->get("field");

            foreach ($options as $option) {
                if (isset($option))
                    $data["options"][] = array("label" => array("text" => $option));
            }
            $total = count($options);

            if ($total > 1 && $options[$total - 1] == $options[$total - 2])
                unset($options[$total - 1]);

        } else {
            $data["options"][] = array("label" => array("text" => 'option'));
        }
        return json_encode($data);

    }
}

