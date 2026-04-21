<?php

declare(strict_types=1);

namespace App\Services\Dashboard\ValueObjects;

use App\Enums\ResourceType;
use InvalidArgumentException;

/**
 * Identifies a dimensional slice for a metric calculation.
 *
 * Scope combinations:
 * - Company-wide: branchIds=null, resourceType=null
 * - Branch subset: branchIds=[...], resourceType=null
 * - Single resource: resourceType + resourceId set (branchIds usually null)
 *
 * Empty array for branchIds is treated as "no branches allowed" (deny-all).
 * Null for branchIds is treated as "all branches" (company view).
 */
final readonly class MetricScope
{
    /**
     * @param  list<int>|null  $branchIds  null = all branches (company); array = subset; empty array = deny-all
     */
    public function __construct(
        public int $accountId,
        public ?array $branchIds = null,
        public ?ResourceType $resourceType = null,
        public ?int $resourceId = null,
    ) {
        if ($resourceType !== null && $resourceId === null) {
            throw new InvalidArgumentException('MetricScope: resourceType requires resourceId');
        }

        if ($resourceId !== null && $resourceType === null) {
            throw new InvalidArgumentException('MetricScope: resourceId requires resourceType');
        }

        if ($branchIds !== null) {
            foreach ($branchIds as $id) {
                if ($id <= 0) {
                    throw new InvalidArgumentException('MetricScope: branchIds must be a list of positive integers');
                }
            }
        }
    }

    public static function company(int $accountId): self
    {
        return new self($accountId);
    }

    public static function branches(int $accountId, array $branchIds): self
    {
        return new self($accountId, array_values(array_unique(array_map('intval', $branchIds))));
    }

    public static function doctor(int $accountId, int $doctorId): self
    {
        return new self($accountId, null, ResourceType::Doctor, $doctorId);
    }

    public static function resource(int $accountId, ResourceType $type, int $resourceId): self
    {
        return new self($accountId, null, $type, $resourceId);
    }

    public function isCompanyWide(): bool
    {
        return $this->branchIds === null && $this->resourceType === null;
    }

    public function isBranchScoped(): bool
    {
        return $this->branchIds !== null && $this->resourceType === null;
    }

    public function isResourceScoped(): bool
    {
        return $this->resourceType !== null;
    }

    public function isDenyAll(): bool
    {
        return $this->branchIds !== null && $this->branchIds === [];
    }

    public function withBranches(array $branchIds): self
    {
        return new self(
            $this->accountId,
            array_values(array_unique(array_map('intval', $branchIds))),
            $this->resourceType,
            $this->resourceId,
        );
    }

    /**
     * Stable cache-key fragment for this scope.
     */
    public function cacheKey(): string
    {
        $branches = $this->branchIds === null ? 'all' : implode(',', $this->branchIds);
        $resource = $this->resourceType === null
            ? 'none'
            : "{$this->resourceType->value}:{$this->resourceId}";

        return "acc:{$this->accountId}|br:{$branches}|res:{$resource}";
    }
}
