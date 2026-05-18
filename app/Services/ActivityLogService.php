<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\ActivityLogRenderer;
use App\Models\Activity;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ActivityLogService
{
    /**
     * Get activity logs with optional filters
     *
     * @param  array  $filters  - Optional filters: patient_id, start_date, end_date, service_id, location_id, activity_type, user_id
     */
    public static function getActivityLogs(array $filters = []): array
    {
        $query = self::buildQuery($filters);

        $activities = $query->orderBy('created_at', 'desc')->get();

        return self::formatActivities($activities);
    }

    /**
     * Stream ALL rows matching the filters for CSV export. Uses lazy() so
     * memory stays bounded even on 100k+ results. Returns a generator of
     * rendered rows with plain-text description (HTML stripped) plus
     * actor/tag columns suitable for a CSV file.
     *
     * @return \Generator<int, array<string, string>>
     */
    public static function streamActivityLogs(array $filters = []): \Generator
    {
        $query = self::buildQuery($filters)->orderByDesc('id');

        foreach ($query->lazy(1000) as $activity) {
            $rendered = ActivityLogRenderer::renderCompact($activity);
            $plain = $rendered !== null
                ? trim(strip_tags($rendered))
                : trim(strip_tags((string) $activity->description));

            // Extract tag from the rendered HTML (first act-tag span content).
            $tag = '';
            if ($rendered !== null && preg_match('/<span class="act-tag[^"]*">([^<]+)<\/span>/', $rendered, $m)) {
                $tag = trim($m[1]);
            }

            $rawTs = $activity->created_at ?? null;
            $createdAtLocal = $rawTs
                ? Carbon::parse((string) $rawTs, 'UTC')
                    ->setTimezone(config('app.timezone'))
                    ->format('M j, Y g:i A')
                : '';

            yield [
                'created_at' => $createdAtLocal,
                'tag' => $tag,
                'activity_type' => (string) ($activity->activity_type ?? ''),
                'actor' => (string) ($activity->user->name ?? ''),
                'description_plain' => $plain,
            ];
        }
    }

    /**
     * Cursor-paginated variant for the activity logs report.
     * Separate from getActivityLogs() to avoid changing the patient-card
     * contract (PatientService expects an array of all rows for a single
     * patient — bounded and small). Reports filter the full tenant set
     * which easily exceeds memory without pagination.
     *
     * @return array{data: array, next_cursor: ?string, total: ?int}
     */
    public static function paginateActivityLogs(array $filters = [], ?string $cursor = null, int $perPage = 100): array
    {
        $query = self::buildQuery($filters)->orderByDesc('id');

        /** @var CursorPaginator $paginator */
        $paginator = $query->cursorPaginate(
            perPage: $perPage,
            cursor: $cursor ? Cursor::fromEncoded($cursor) : null,
        );

        $total = $cursor === null ? (clone $query)->toBase()->getCountForPagination() : null;

        return [
            'data' => self::formatActivities($paginator->items()),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'total' => $total,
        ];
    }

    private static function buildQuery(array $filters)
    {
        $query = Activity::with(['user', 'serviceR', 'patientR', 'centre'])
            ->where('account_id', Auth::user()->account_id);

        // Row-level access control per HIPAA §164.312(a)(1). Users without
        // `activity_logs_view_all` see only their own actions (rows where
        // created_by matches their id). Super-Admin passes through via the
        // global Gate::before. Compliance/audit roles should be granted the
        // view-all permission explicitly.
        if (! Gate::allows('activity_logs_view_all')) {
            $query->where('created_by', Auth::id());
        }

        // Suppress activity types configured as display-hidden (e.g. lead_converted
        // which fires as a pair with appointment_converted and adds no new info).
        $suppressed = (array) config('activity_log.suppress_from_display', []);
        if ($suppressed !== []) {
            $query->whereNotIn('activity_type', $suppressed);
        }

        if (! empty($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }

        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $startDate = $filters['start_date'].' 00:00:00';
            $endDate = $filters['end_date'].' 23:59:59';

            $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                    ->orWhereBetween('updated_at', [$startDate, $endDate]);
            });
        }

        if (! empty($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (! empty($filters['location_id'])) {
            $query->whereIn('centre_id', (array) $filters['location_id']);
        }

        if (! empty($filters['activity_type']) && $filters['activity_type'] !== 'all') {
            $query->where('activity_type', $filters['activity_type']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('created_by', $filters['user_id']);
        }

        // Freetext search — routed to clear-text columns only. The
        // `description` column is encrypted at rest (EncryptedLegacy cast
        // on Activity model), so a LIKE against it would only match the
        // 397k pre-encryption rows and silently miss new writes. Instead
        // we search:
        //   activities.patient   (stored patient name)
        //   activities.service   (stored service name)
        //   activities.action    (event verb)
        //   users.name via created_by relation (actor)
        // Each already narrows well under the outer account_id + date scan.
        if (! empty($filters['search'])) {
            $escaped = addcslashes((string) $filters['search'], '%_\\');
            $needle = '%'.$escaped.'%';

            $query->where(function ($q) use ($needle): void {
                $q->where('patient', 'like', $needle)
                    ->orWhere('service', 'like', $needle)
                    ->orWhere('action', 'like', $needle)
                    ->orWhereHas('user', function ($uq) use ($needle): void {
                        $uq->where('name', 'like', $needle);
                    });
            });
        }

        // Amount range — financial events carry `amount` as decimal/string
        // depending on writer. Compare as numeric.
        if (isset($filters['amount_min']) && $filters['amount_min'] !== '' && is_numeric($filters['amount_min'])) {
            $query->where('amount', '>=', (float) $filters['amount_min']);
        }
        if (isset($filters['amount_max']) && $filters['amount_max'] !== '' && is_numeric($filters['amount_max'])) {
            $query->where('amount', '<=', (float) $filters['amount_max']);
        }

        // Multi-select tag families. For each tag, include its known
        // activity_type values AND prefix-match patterns. All tags OR'd
        // together, then AND'd into the outer query.
        if (! empty($filters['tags']) && is_array($filters['tags'])) {
            $allTypes = [];
            $allPrefixes = [];
            foreach ($filters['tags'] as $tag) {
                $spec = ActivityLogRenderer::filterSpecForTag((string) $tag);
                $allTypes = array_merge($allTypes, $spec['types']);
                $allPrefixes = array_merge($allPrefixes, $spec['prefixes']);
            }
            $allTypes = array_values(array_unique($allTypes));
            $allPrefixes = array_values(array_unique($allPrefixes));

            $query->where(function ($q) use ($allTypes, $allPrefixes): void {
                if ($allTypes !== []) {
                    $q->whereIn('activity_type', $allTypes);
                }
                foreach ($allPrefixes as $prefix) {
                    $escaped = addcslashes($prefix, '%_\\');
                    $q->orWhere('activity_type', 'like', $escaped.'%');
                }
            });

            // VISIT tag narrows invoice_created to amount=0; CONS excludes
            // amount=0 invoice_created rows (those are VISIT).
            $hasVisit = in_array('VISIT', $filters['tags'], true);
            $hasCons = in_array('CONS', $filters['tags'], true);
            if ($hasVisit && ! $hasCons) {
                $query->where(function ($q): void {
                    $q->where('activity_type', '!=', 'invoice_created')
                        ->orWhere('amount', '=', 0)
                        ->orWhereNull('amount');
                });
            } elseif ($hasCons && ! $hasVisit) {
                $query->where(function ($q): void {
                    $q->where('activity_type', '!=', 'invoice_created')
                        ->orWhere('amount', '>', 0);
                });
            }
        }

        return $query;
    }

    /**
     * Format activities for display.
     *
     * Appends "— by <actor>" to the stored description at render time
     * (never at write time). The stored `description` column stays
     * PII-free and compact; the actor name is derived live from the
     * eager-loaded `user` relation (belongsTo via created_by). Skips
     * the append when the description already mentions the actor (the
     * legacy ActivityLogger helper often embeds the name already).
     */
    private static function formatActivities(mixed $activities): array
    {
        $data = [];

        foreach ($activities as $activity) {
            $timestamp = $activity->updated_at ?? $activity->created_at;

            // Compact renderer (HIPAA-aligned) handles ~98% of the feed with
            // structured column reads — bypasses the stored description
            // entirely and produces a colored-tag line. Returns null for
            // unknown types; caller falls back to legacy path below.
            $compact = ActivityLogRenderer::renderCompact($activity);

            if ($compact !== null) {
                $description = $compact;
                // Compact already formats "· by Actor" inline; skip appendActor.
            } else {
                $description = $activity->description;
                if ($description === null) {
                    if (config('features.activities_strict_descriptions', false)) {
                        throw new \RuntimeException(
                            "Activity #{$activity->id} has no stored description. ".
                            'Producers must populate `description` at write time. '.
                            '(Disable config(features.activities_strict_descriptions) to fall back to runtime rendering.)'
                        );
                    }
                    $description = ActivityLogRenderer::render($activity);
                }

                $description = self::appendActor($activity, $description);
            }

            // `activities.created_at/updated_at` are written by Eloquent
            // under config('app.timezone') (Asia/Karachi) — the DB
            // connection has no UTC conversion — so the stored value is
            // already local wall-clock time. Parsing it AS UTC and then
            // setTimezone()-ing to Karachi double-applied the +5h offset
            // (activity feed showed times 5 hours ahead). Carbon::parse()
            // defaults to the app timezone, which is exactly how it was
            // stored — no conversion needed.
            $localTs = Carbon::parse((string) $timestamp);

            $data[] = [
                'type' => $activity->activity_type ?? $activity->action ?? 'unknown',
                'description' => $description,
                'created_at' => $timestamp,
                'time_formatted' => $localTs->format('M j, Y g:i A'),
                'time_short' => $localTs->format('m-d-Y H:i'),
            ];
        }

        return $data;
    }

    private static function appendActor(mixed $activity, string $description): string
    {
        $actor = $activity->user?->name;
        if ($actor === null || $actor === '') {
            return $description;
        }

        if (str_contains($description, $actor)) {
            return $description;
        }

        $createdBy = (int) ($activity->created_by ?? 0);
        if ($createdBy > 0) {
            $subjectPattern = '/User#'.preg_quote((string) $createdBy, '/').'\b/';
            if (preg_match($subjectPattern, $description) === 1) {
                // Self-action — don't repeat the actor name.
                return $description;
            }
        }

        return $description.' — by '.$actor;
    }
}
