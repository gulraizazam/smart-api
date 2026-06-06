<?php

declare(strict_types=1);

namespace App\Http\Resources\Feedback;

use App\Helpers\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class FeedbackDatatableResource extends JsonResource
{
    /**
     * Pre-loaded users dictionary for creator name resolution.
     * Set before calling ::collection() to avoid N+1 queries.
     */
    public static array $usersLookup = [];

    public function toArray(Request $request): array
    {
        $canViewContact = Gate::allows('contact');

        return [
            'id' => $this->id,
            'paient_id' => $this->patient_id,
            'paient_name' => $this->patient_name,
            'phone' => $this->when(
                $canViewContact,
                fn () => GeneralFunctions::prepareNumber4Call($this->patient?->phone ?? ''),
            ),
            'service_id' => $this->service_id ?? '',
            'service' => $this->service?->name ?? '',
            'treatment' => $this->treatment?->name ?? '',
            'created_at' => $this->created_at?->format('F j, Y h:i A'),
            'created_by' => $this->resolveCreatorName(),
            'location' => $this->location?->name ?? '',
            'doctor' => $this->doctor?->name ?? '',
            'rating' => $this->rating ?? '',
            'comment' => $this->comment ?? '',
        ];
    }

    protected function resolveCreatorName(): string
    {
        if (!empty(self::$usersLookup) && $this->created_by) {
            return self::$usersLookup[$this->created_by]->name ?? 'N/A';
        }

        return $this->creator?->name ?? 'N/A';
    }
}
