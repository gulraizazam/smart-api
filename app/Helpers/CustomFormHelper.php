<?php

declare(strict_types=1);
namespace App\Helpers;

use Illuminate\Http\Request;

class CustomFormHelper
{
    /**
     * It will field string content to proper array
     *
     * @param $content json string
     * @return array
     */
    public static function getContentArrayTest(string $content): array
    {
        return [
            'title' => 'what are your skills? ',
            'options' => [
                ['label' => 'Adobe'],
                ['label' => 'programming'],
            ],
        ];
    }

    /**
     * It will field string content to proper array
     *
     * @param $content json string
     * @return array
     */
    public static function getContentArray(string $content): array
    {
        $json_content = json_decode($content, true);
        if (isset($json_content) && is_array($json_content)) {
            $data = [];
            if (isset($json_content['title'])) {
                $data['title'] = $json_content['title'];
            } else {
                $data['title'] = DefaultField::FIELD_CONTENT['title'];
            }

            if (isset($json_content['rows'])) {
                $data['rows'] = $json_content['rows'];
            } else {
                $data['rows'] = 0;
            }

            if (isset($json_content['options']) && is_array($json_content['options'])) {
                $options = [];
                foreach ($json_content['options'] as $option) {
                    if (is_array($option['label'])) {
                        try {
                            $options[] = ['label' => $option['label']['text']];
                        } catch (\Exception $e) {
                            $options[] = ['label' => DefaultField::OPTION_TITLE.'- except'];
                        }
                    } else {
                        $options[] = ['label' => DefaultField::OPTION_TITLE.' - not'];
                        break;
                    }
                }
                $data['options'] = $options;
            } else {
                $options = [];
                $options[] = ['label' => DefaultField::OPTION_TITLE];
                $data['options'] = $options;
            }

            return $data;
        } else {
            return DefaultField::FIELD_CONTENT;
        }

    }

    public static function getContentJson(Request $request): string
    {
        $data = [];
        if ($request->has('question')) {
            $data['title'] = $request->get('question');
        } else {
            $data['title'] = DefaultField::TITLE;
        }

        if ($request->has('rows')) {
            $data['rows'] = $request->get('rows');
        } else {
            $data['rows'] = 0;
        }

        if ($request->has('description')) {
            $data['description'] = $request->get('description');
        } else {
            $data['description'] = DefaultField::DESCRIPTION;
        }

        if ($request->has('field')) {
            $options = $request->get('field');

            foreach ($options as $option) {
                if (isset($option)) {
                    $data['options'][] = ['label' => ['text' => $option]];
                }
            }
            $total = count($options);

            if ($total > 1 && $options[$total - 1] == $options[$total - 2]) {
                unset($options[$total - 1]);
            }

        } else {
            $data['options'][] = ['label' => ['text' => 'option']];
        }

        return json_encode($data);

    }
}
