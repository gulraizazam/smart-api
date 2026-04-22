<?php

declare(strict_types=1);

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Generic envelope for patient form submissions (medical, measurement,
 * custom). Exposes only the minimum whitelist the mobile client needs —
 * never serialises raw model attributes.
 */
class PatientFormSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = (array) $this->resource;

        return [
            'form_id' => isset($data['form_id']) ? (int) $data['form_id'] : null,
            'reference_id' => isset($data['reference_id']) ? (int) $data['reference_id'] : null,
            'appointment_id' => isset($data['appointment_id']) ? (int) $data['appointment_id'] : null,
            'feedback_id' => isset($data['feedback_id']) ? (int) $data['feedback_id'] : null,
        ];
    }
}
