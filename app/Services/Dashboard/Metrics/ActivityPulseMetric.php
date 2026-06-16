<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Helpers\ActivityLogRenderer;
use App\Models\Activity;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\Carbon;

/**
 * Live activity pulse for the Overview section. Reads the `activities`
 * table directly so management users see company-wide (or branch-scoped)
 * notable events — not just rows they authored.
 *
 * Activity types are filtered to a curated "notable" list management cares
 * about (sales, refunds, feedback, memberships, cancellations). Rows are
 * scoped to the tenant's account_id, the user's assigned branches, and the
 * given date range.
 *
 * Descriptions are rendered through ActivityLogRenderer::renderCompact() so
 * the feed uses the exact same single-line HTML format as the canonical
 * Activity Logs report (tag chip + primary + subject + context + actor).
 * Falls back to the stored description when the renderer returns null.
 *
 * Security: Route-gated via `management_dashboard.view`. We deliberately do
 * NOT funnel through ActivityLogService::buildQuery() because that path
 * enforces the audit-log's per-actor HIPAA gate (`activity_logs_view_all`),
 * which is wrong for a management KPI feed. The curated notable types here
 * are all financial/operational (payments, memberships, cancellations) —
 * not PHI/clinical rows.
 */
final class ActivityPulseMetric implements Metric
{
    private const NOTABLE_TYPES = [
        'payment_received',
        'refund_made',
        'feedback_added',
        'membership_assigned',
        'membership_renewed',
        'membership_cancelled',
        'appointment_cancelled',
        'package_created',
        'voucher_refunded',
        'invoice_cancelled',
    ];

    /**
     * @return array{rows: list<array<string, mixed>>, has_more: bool, next_cursor: ?string}
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        return $this->fetch($scope, $range, null, 20);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, has_more: bool, next_cursor: ?string}
     */
    public function fetch(
        MetricScope $scope,
        DateRange $range,
        ?string $cursor = null,
        int $limit = 20,
    ): array {
        if ($scope->isDenyAll()) {
            return ['rows' => [], 'has_more' => false, 'next_cursor' => null];
        }

        $query = Activity::query()
            ->with(['user', 'serviceR', 'patientR', 'centre'])
            ->where('account_id', $scope->accountId)
            ->whereIn('activity_type', self::NOTABLE_TYPES)
            ->whereBetween('created_at', [
                $range->startString().' 00:00:00',
                $range->endString().' 23:59:59',
            ])
            ->orderByDesc('id');

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            $query->whereIn('centre_id', $scope->branchIds);
        }

        // Id-based cursor pagination (desc). Over-fetch by 1 to detect
        // has_more in a single round trip.
        if ($cursor !== null && ctype_digit($cursor)) {
            $query->where('id', '<', (int) $cursor);
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $page = $hasMore ? $rows->slice(0, $limit) : $rows;

        return [
            'rows' => $page->values()->map(fn (Activity $a): array => $this->shapeRow($a))->all(),
            'has_more' => $hasMore,
            'next_cursor' => $hasMore ? (string) $page->last()?->id : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeRow(Activity $a): array
    {
        $type = (string) ($a->activity_type ?? '');
        $rawCreated = $a->getAttribute('created_at');
        // `activities.created_at` is stored in UTC (Activity::creating →
        // toUtcStamp). Read it AS UTC and convert to the app timezone
        // (Asia/Karachi) for display — exactly what the canonical
        // ActivityLogService does. Parsing it without a zone (treating the UTC
        // value as local) showed every fresh row "5 hours ago".
        $createdCarbon = $rawCreated !== null
            ? Carbon::parse((string) $rawCreated, 'UTC')->setTimezone(config('app.timezone'))
            : null;

        // Prefer the canonical compact HTML renderer so the feed format
        // matches the Activity Logs report. Fall back to the stored
        // description (plain text) when the renderer doesn't handle the
        // type, or to the action label as a last resort.
        $compactHtml = ActivityLogRenderer::renderCompact($a);
        $storedDescription = (string) ($a->description ?? '');
        $descriptionHtml = $compactHtml
            ?? ($storedDescription !== '' ? e($storedDescription) : e((string) ($a->action ?? $type)));

        return [
            'id' => (int) $a->id,
            'activity_type' => $type,
            'time' => $createdCarbon?->format('Y-m-d H:i:s') ?? '',
            'time_for_humans' => $createdCarbon?->diffForHumans() ?? '',
            'description_html' => $descriptionHtml,
            'description' => $storedDescription !== '' ? $storedDescription : (string) ($a->action ?? $type),
            'amount' => $this->parseAmount($storedDescription),
            'patient' => (string) $a->getAttribute('patient'),
            'service' => (string) $a->getAttribute('service'),
            'branch_id' => $a->centre_id !== null ? (int) $a->centre_id : null,
            'severity' => $this->severityFor($type),
        ];
    }

    /**
     * Description strings often carry amounts in the form "PKR 12,345" or
     * "Rs. 12345". Extract the first such figure so the feed row can render
     * a currency chip. Returns null when nothing is found.
     */
    private function parseAmount(string $description): ?float
    {
        if (preg_match('/(?:PKR|Rs\.?)\s*([0-9][0-9,]*(?:\.[0-9]+)?)/i', $description, $m) === 1) {
            return (float) str_replace(',', '', $m[1]);
        }

        return null;
    }

    private function severityFor(string $activityType): string
    {
        return match ($activityType) {
            'payment_received', 'membership_assigned', 'membership_renewed', 'package_created' => 'positive',
            'feedback_added' => 'info',
            'appointment_cancelled', 'membership_cancelled', 'invoice_cancelled' => 'warning',
            'refund_made', 'voucher_refunded' => 'negative',
            default => 'neutral',
        };
    }
}
