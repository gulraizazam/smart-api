<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use App\Enums\Gender;
use App\Helpers\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class ApplicationUserResource extends JsonResource
{
    public static array $locationLookup = [];
    public static array $locationIdsByUser = [];

    public function toArray(Request $request): array
    {
        $canViewContact = Gate::allows('contact');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $canViewContact
                ? GeneralFunctions::contactStatus($this->phone)
                : '***********',
            'gender' => $this->resolveGender(),
            'locations' => $this->resolveLocations(),
            'roles' => $this->whenLoaded('role_has_users', fn (): array => $this->user_roles()->pluck('name')->all(), []),
            'created_at' => Carbon::parse($this->created_at)->format('F j,Y h:i A'),
            'active' => $this->active,
        ];
    }

    private function resolveGender(): string
    {
        if ($this->gender === null) {
            return 'N/A';
        }

        return Gender::tryFrom((int) $this->gender)?->label() ?? 'N/A';
    }

    private function resolveLocations(): array
    {
        $locationIds = self::$locationIdsByUser[$this->id] ?? [];

        return collect($locationIds)
            ->filter(fn (int $id): bool => isset(self::$locationLookup[$id]))
            ->map(fn (int $id): string => self::$locationLookup[$id]->name ?? '')
            ->values()
            ->all();
    }
}
