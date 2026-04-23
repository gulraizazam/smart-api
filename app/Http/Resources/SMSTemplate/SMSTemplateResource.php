<?php

declare(strict_types=1);

namespace App\Http\Resources\SMSTemplate;

use App\Helpers\GeneralFunctions;
use App\Models\SMSTemplates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SMSTemplateResource extends JsonResource
{
    /**
     * When true, the resource includes the list of allowed template
     * variables (resolved from the slug) in the output. Set from the
     * controller on single-record endpoints; default off for list views.
     */
    public static bool $includeVariables = false;

    public function toArray(Request $request): array
    {
        /** @var SMSTemplates $template */
        $template = $this->resource;

        $payload = [
            'id' => (int) $template->id,
            'name' => (string) $template->name,
            'slug' => $template->slug,
            'content' => $template->content,
            'active' => (bool) $template->active,
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];

        if (self::$includeVariables) {
            $payload['variables'] = GeneralFunctions::smsTemplateVariables($template->slug) ?? [];
        }

        return $payload;
    }
}
