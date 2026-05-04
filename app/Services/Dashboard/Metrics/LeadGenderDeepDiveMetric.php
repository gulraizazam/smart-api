<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Deep-dive payload for the half-width Lead Funnel panel. Composes three
 * views over the same lead data:
 *
 *   1. funnel   — 4-stage counts by gender (same shape as LeadGenderFunnelMetric)
 *   2. trend    — 12-week lead→converted rate per gender
 *   3. sources  — top 5 lead sources in range with F/M conversion rates
 *
 * Gender resolution rule matches LeadGenderFunnelMetric:
 *   COALESCE(NULLIF(patients.gender, 0), NULLIF(leads.gender, 0)) — so
 *   retroactive gender corrections on the patient record flow through
 *   automatically.
 */
final class LeadGenderDeepDiveMetric implements Metric
{
    // Legacy hardcoded IDs — kept as fallback if the dynamic `is_*` flag
    // lookups return nothing (e.g. fresh seed without converted rows yet).
    // Prefer the dynamic accessors `statusIds()` over reading these directly.
    private const STATUS_BOOKED_OR_BEYOND = [4, 6, 10, 9, 11];

    private const STATUS_ARRIVED_OR_BEYOND = [6, 10, 9, 11];

    private const STATUS_CONVERTED = [9, 11];

    private const TREND_WEEKS = 12;

    private const SOURCES_LIMIT = 5;

    /**
     * Leads created in the last N days are excluded from rate/RPL
     * calculations — they haven't had time to close. Denominators without
     * a maturity cutoff always understate conversion rates during the
     * current period (especially for short windows).
     */
    private const int MATURITY_DAYS = 7;

    /**
     * Per-gender sample size floor for the category breakdown. We only
     * compute a fair F vs M comparison when both genders have ≥ this
     * many mature leads in the service. Below this, the row is still
     * shown but marked `low_confidence`.
     */
    private const int MIN_LEADS_PER_GENDER = 15;

    /**
     * Cache of resolved status IDs per account (dynamic; avoids hardcoding).
     * Shape: ['converted' => int[], 'arrived' => int[], 'booked' => int[]].
     *
     * @var array<int, array{converted:list<int>, arrived:list<int>, booked:list<int>}>
     */
    private array $statusCache = [];

    /**
     * @return array<string, mixed>
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return $this->empty();
        }

        $funnel = $this->funnel($scope, $range);
        $economics = $this->economics($scope, $range, $funnel);
        $economics = $this->attachPriorPeriodDeltas($scope, $range, $economics);
        $economics['by_category'] = $this->categoryConversionBreakdown($scope, $range);
        $economics['maturity_days'] = self::MATURITY_DAYS;
        $rescue = $this->rescue($funnel, $economics);

        return [
            'funnel' => $funnel,
            'trend' => $this->trend($scope),
            'sources' => $this->sources($scope, $range),
            'economics' => $economics,
            'rescue' => $rescue,
        ];
    }

    /**
     * Augment the in-range economics payload with the equivalent window
     * shifted one period earlier, plus % delta vs prior. Lets the UI show
     * "↑ 4.2% vs last period" chips under the RPL hero.
     *
     * @param  array{female:array<string, mixed>, male:array<string, mixed>}  $current
     * @return array<string, mixed>
     */
    private function attachPriorPeriodDeltas(MetricScope $scope, DateRange $range, array $current): array
    {
        $prevRange = $range->previousPeriod();
        $prevFunnel = $this->funnel($scope, $prevRange);
        $prev = $this->economics($scope, $prevRange, $prevFunnel);

        $delta = static function (float $cur, float $prev): ?float {
            if ($prev <= 0.0) {
                return null;
            }

            return round((($cur - $prev) / $prev) * 100, 1);
        };

        foreach (['female', 'male'] as $g) {
            $current[$g]['prev'] = [
                'rev_per_lead' => $prev[$g]['rev_per_lead'],
                'rev_per_conv' => $prev[$g]['rev_per_conv'],
                'leads' => $prev[$g]['leads'],
                'conversions' => $prev[$g]['conversions'],
                'range' => [
                    'from' => $prevRange->startString(),
                    'to' => $prevRange->endString(),
                ],
            ];
            $current[$g]['delta'] = [
                'rev_per_lead_pct' => $delta((float) $current[$g]['rev_per_lead'], (float) $prev[$g]['rev_per_lead']),
                'rev_per_conv_pct' => $delta((float) $current[$g]['rev_per_conv'], (float) $prev[$g]['rev_per_conv']),
            ];
        }

        return $current;
    }

    /**
     * Resolve converted/arrived/booked status IDs for the given account.
     * Uses the `is_converted`, `is_arrived`, `is_booked` flags on
     * lead_statuses so the numbers stay correct when an account adds
     * custom statuses. Falls back to the hardcoded defaults if flags
     * aren't populated yet.
     *
     * @return array{converted:list<int>, arrived:list<int>, booked:list<int>}
     */
    private function statusIds(int $accountId): array
    {
        if (isset($this->statusCache[$accountId])) {
            return $this->statusCache[$accountId];
        }

        $rows = DB::table('lead_statuses')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->selectRaw('id, COALESCE(is_converted, 0) AS is_converted, COALESCE(is_arrived, 0) AS is_arrived, COALESCE(is_booked, 0) AS is_booked')
            ->get();

        $converted = [];
        $arrived = [];
        $booked = [];
        foreach ($rows as $r) {
            if ((int) $r->is_converted === 1) {
                $converted[] = (int) $r->id;
            }
            // Arrived/booked are hierarchical — anything past a stage counts.
            if ((int) $r->is_arrived === 1 || (int) $r->is_converted === 1) {
                $arrived[] = (int) $r->id;
            }
            if ((int) $r->is_booked === 1 || (int) $r->is_arrived === 1 || (int) $r->is_converted === 1) {
                $booked[] = (int) $r->id;
            }
        }

        if ($converted === []) {
            $converted = self::STATUS_CONVERTED;
        }
        if ($arrived === []) {
            $arrived = self::STATUS_ARRIVED_OR_BEYOND;
        }
        if ($booked === []) {
            $booked = self::STATUS_BOOKED_OR_BEYOND;
        }

        return $this->statusCache[$accountId] = [
            'converted' => $converted,
            'arrived' => $arrived,
            'booked' => $booked,
        ];
    }

    /**
     * Maturity cutoff timestamp (midnight). Leads created after this
     * instant are excluded from rate/RPL calcs so early-period leads
     * that haven't had time to close don't depress conversion rates.
     */
    private function maturityCutoff(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays(self::MATURITY_DAYS)->endOfDay();
    }

    /**
     * Per-category conversion rate + revenue-per-lead split by gender. Reads
     * lead enquiries from the leads_services pivot, joins to the lead's
     * status for conversion detection. Groups at the service level.
     *
     * Two comparison dimensions are returned:
     *   • rate_winner / rate_gap_factor — who converts more often
     *   • rpl_winner  / rpl_gap_factor  — who's worth more per lead
     *
     * These two can disagree: a gender may convert less often but each
     * conversion is large enough that their revenue-per-lead is still
     * higher. Exposing both lets users decide *which* question to answer
     * (targeting efficiency vs. monetary yield) rather than collapsing.
     *
     * @return list<array{
     *   service_id:int, service_name:string,
     *   female:array{leads:int,conv:int,rate:float,revenue:float,rev_per_lead:float},
     *   male:array{leads:int,conv:int,rate:float,revenue:float,rev_per_lead:float},
     *   total:int,
     *   winner:string, gap_factor:float|null,
     *   rate_winner:string, rate_gap_factor:float|null,
     *   rpl_winner:string, rpl_gap_factor:float|null,
     * }>
     */
    private function categoryConversionBreakdown(MetricScope $scope, DateRange $range): array
    {
        $start = $range->startString().' 00:00:00';
        $end = $range->endString().' 23:59:59';
        $maturityEnd = min($end, $this->maturityCutoff()->format('Y-m-d H:i:s'));

        // Don't run the aggregation if the entire range is too young to
        // have had any leads close. Returning an empty list tells the UI
        // to render a "No mature leads yet" empty state.
        if ($maturityEnd < $start) {
            return [];
        }

        $ids = $this->statusIds($scope->accountId);
        $convertedList = implode(',', $ids['converted']);

        $query = DB::table('leads_services as ls')
            ->join('leads as l', 'l.id', '=', 'ls.lead_id')
            ->join('services as s', 's.id', '=', 'ls.service_id')
            ->leftJoin('users as p', function ($join) {
                $join->on('p.id', '=', 'l.patient_id')->where('p.user_type_id', 3);
            })
            ->where('l.account_id', $scope->accountId)
            ->whereNull('l.deleted_at')
            // Maturity window: lead must have been created before the
            // cutoff to be counted in rate/RPL denominators.
            ->whereBetween('l.created_at', [$start, $maturityEnd]);

        if ($scope->isBranchScoped() && $scope->branchIds !== null && $scope->branchIds !== []) {
            $query->whereIn('l.location_id', $scope->branchIds);
        }

        // Single-value effective gender — patient profile wins, lead
        // signup falls back. Matches LeadGenderFunnelMetric/trend/sources
        // so a lead whose signup and profile disagree is counted in
        // exactly one bucket (prior OR-pattern dual-counted such leads).
        $effectiveGender = 'COALESCE(NULLIF(p.gender, 0), NULLIF(l.gender, 0))';

        // DISTINCT lead_id per service so a lead enquiring about the same
        // service twice doesn't double-inflate the denominator.
        $rows = $query
            ->selectRaw('s.id AS service_id, s.name AS service_name')
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$effectiveGender} = 2 THEN l.id END) AS f_leads")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$effectiveGender} = 2 AND l.lead_status_id IN ({$convertedList}) THEN l.id END) AS f_conv")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$effectiveGender} = 1 THEN l.id END) AS m_leads")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$effectiveGender} = 1 AND l.lead_status_id IN ({$convertedList}) THEN l.id END) AS m_conv")
            ->selectRaw('COUNT(DISTINCT l.id) AS total_leads')
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$effectiveGender} NOT IN (1,2) OR {$effectiveGender} IS NULL THEN l.id END) AS u_leads")
            ->groupBy('s.id', 's.name')
            ->orderByDesc('total_leads')
            ->limit(15)
            ->get();

        // Matched RPL attribution: revenue counts only when the patient
        // bought a package line item that contains the same service they
        // enquired about. This eliminates the "enquired about laser but
        // bought facials → still credited to laser" over-counting.
        $revenueMap = $this->categoryRevenueMatched($scope, $start, $maturityEnd);

        $out = [];
        foreach ($rows as $row) {
            $serviceId = (int) $row->service_id;
            $fLeads = (int) $row->f_leads;
            $fConv = (int) $row->f_conv;
            $mLeads = (int) $row->m_leads;
            $mConv = (int) $row->m_conv;
            $uLeads = (int) $row->u_leads;
            $totalLeads = (int) $row->total_leads;

            $fRate = $fLeads > 0 ? round(($fConv / $fLeads) * 100, 1) : 0.0;
            $mRate = $mLeads > 0 ? round(($mConv / $mLeads) * 100, 1) : 0.0;

            $fRev = (float) ($revenueMap[$serviceId]['female'] ?? 0.0);
            $mRev = (float) ($revenueMap[$serviceId]['male'] ?? 0.0);
            $fRpl = $fLeads > 0 ? round($fRev / $fLeads, 2) : 0.0;
            $mRpl = $mLeads > 0 ? round($mRev / $mLeads, 2) : 0.0;

            // Low confidence = either gender has < MIN_LEADS_PER_GENDER
            // mature leads. Don't hide — just tag so the UI can de-emphasise.
            $lowConfidence = ($fLeads < self::MIN_LEADS_PER_GENDER) || ($mLeads < self::MIN_LEADS_PER_GENDER);

            // "No conversions yet" is a distinct signal from "tied at X%".
            // Flag it so the UI shows a different badge ("No conversions"
            // instead of "Tied") — the 208 leads with zero closes case.
            $noConversions = ($fConv === 0 && $mConv === 0 && $totalLeads > 0);

            if ($noConversions) {
                $rateWinner = 'none';
                $rateGap = null;
            } else {
                [$rateWinner, $rateGap] = $this->compareWinner($fRate, $mRate);
            }

            if ($fRpl === 0.0 && $mRpl === 0.0) {
                $rplWinner = 'none';
                $rplGap = null;
            } else {
                [$rplWinner, $rplGap] = $this->compareWinner($fRpl, $mRpl);
            }

            $unknownPct = $totalLeads > 0 ? round(($uLeads / $totalLeads) * 100, 1) : 0.0;

            // "Cross-sold" flag: the gender had conversions for this
            // category's enquiries, but the patients bought services
            // outside the enquired category. Distinct from no_conversions
            // — they DID convert, just into a different category. Useful
            // as a lead-magnet signal: good at generating interest but
            // losing purchase intent to other categories.
            $fCrossSold = $fConv > 0 && $fRev <= 0.0;
            $mCrossSold = $mConv > 0 && $mRev <= 0.0;

            $out[] = [
                'service_id' => $serviceId,
                'service_name' => (string) $row->service_name,
                'female' => [
                    'leads' => $fLeads, 'conv' => $fConv, 'rate' => $fRate,
                    'revenue' => round($fRev, 2), 'rev_per_lead' => $fRpl,
                    'cross_sold' => $fCrossSold,
                ],
                'male' => [
                    'leads' => $mLeads, 'conv' => $mConv, 'rate' => $mRate,
                    'revenue' => round($mRev, 2), 'rev_per_lead' => $mRpl,
                    'cross_sold' => $mCrossSold,
                ],
                'total' => $totalLeads,
                'unknown_leads' => $uLeads,
                'unknown_pct' => $unknownPct,
                // Legacy fields (kept for backwards compat):
                'winner' => $rateWinner,
                'gap_factor' => $rateGap,
                // Per-dimension winners:
                'rate_winner' => $rateWinner,
                'rate_gap_factor' => $rateGap,
                'rpl_winner' => $rplWinner,
                'rpl_gap_factor' => $rplGap,
                // Signals for the UI:
                'low_confidence' => $lowConfidence,
                'no_conversions' => $noConversions,
            ];
        }

        return $out;
    }

    /**
     * Matched-attribution revenue per (service_id × gender).
     *
     * Rule: a converted lead's revenue is credited to a category ONLY IF
     * the patient bought a package line item **in the same service-tree
     * root** (category) they enquired about. The services table is a tree
     * — `leads_services.service_id` typically stores the category root
     * (e.g. "Laser Hair Removal"), whereas `package_services.service_id`
     * stores the leaf actually bought (e.g. "Full-body Laser"). Matching
     * on exact ID would return zero for any purchase of a leaf service;
     * we compare roots instead.
     *
     * Root-comparison expression: `COALESCE(s.parent_id, s.id)` — a leaf's
     * root is its parent_id; a root's root is itself. Matching roots on
     * both sides correctly attributes "patient enquired about the Laser
     * category AND bought a Laser-category leaf." (Assumes the tree is
     * two levels deep, which is how this project's services are modelled.)
     *
     * Uses package_services.price (the line-item face value) as the
     * attribution base. Σ(category revenue) ≤ total cash collected — the
     * gap is revenue from unmatched services, which by definition wasn't
     * driven by the enquiry.
     *
     * @return array<int, array{female:float, male:float}>
     */
    private function categoryRevenueMatched(MetricScope $scope, string $leadStart, string $leadEnd): array
    {
        $ids = $this->statusIds($scope->accountId);
        $convertedList = implode(',', $ids['converted']);

        $query = DB::table('leads_services as ls')
            ->join('leads as l', 'l.id', '=', 'ls.lead_id')
            ->leftJoin('users as p', function ($join) {
                $join->on('p.id', '=', 'l.patient_id')->where('p.user_type_id', 3);
            })
            // Enquired-service row (to resolve its category root).
            ->join('services as s_e', 's_e.id', '=', 'ls.service_id')
            ->join('appointments as a', 'a.patient_id', '=', 'l.patient_id')
            ->join('packages as pk', 'pk.appointment_id', '=', 'a.id')
            ->join('package_services as pks', 'pks.package_id', '=', 'pk.id')
            // Purchased-service row (to resolve its category root).
            ->join('services as s_p', 's_p.id', '=', 'pks.service_id')
            // Same category tree on both sides:
            // COALESCE(parent_id, id) picks the root for leaves and the
            // self-id for roots, so category-level enquiries match
            // leaf-level purchases inside the same category.
            ->whereRaw('COALESCE(s_p.parent_id, s_p.id) = COALESCE(s_e.parent_id, s_e.id)')
            ->where('l.account_id', $scope->accountId)
            ->whereNull('l.deleted_at')
            ->whereBetween('l.created_at', [$leadStart, $leadEnd])
            ->whereRaw("l.lead_status_id IN ({$convertedList})")
            ->whereNotNull('l.patient_id')
            ->whereNull('pk.deleted_at')
            ->whereColumn('pk.created_at', '>=', 'l.created_at');

        if ($scope->isBranchScoped() && $scope->branchIds !== null && $scope->branchIds !== []) {
            $query->whereIn('l.location_id', $scope->branchIds)
                ->whereIn('pk.location_id', $scope->branchIds);
        }

        $rows = $query
            ->selectRaw('ls.service_id, '.$this->genderCase().' AS effective_gender')
            ->selectRaw('SUM(pks.price) AS revenue')
            ->groupBy('ls.service_id', 'effective_gender')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $sid = (int) $r->service_id;
            $gender = (string) $r->effective_gender;
            if ($gender !== 'female' && $gender !== 'male') {
                continue;
            }
            $map[$sid] ??= ['female' => 0.0, 'male' => 0.0];
            $map[$sid][$gender] += (float) $r->revenue;
        }

        return $map;
    }

    /**
     * Shared winner/gap-factor calc. Returns ['tie'|'female'|'male', float|null].
     * Gap threshold of 1.1 matches existing conversion-rate comparison so the
     * two dimensions use consistent sensitivity.
     *
     * @return array{0:string,1:float|null}
     */
    private function compareWinner(float $fValue, float $mValue): array
    {
        if ($fValue > 0 && $mValue > 0) {
            if ($fValue >= $mValue * 1.1) {
                return ['female', round($fValue / $mValue, 1)];
            }
            if ($mValue >= $fValue * 1.1) {
                return ['male', round($mValue / $fValue, 1)];
            }

            return ['tie', null];
        }

        if ($fValue > 0 && $mValue === 0.0) {
            return ['female', null];
        }
        if ($mValue > 0 && $fValue === 0.0) {
            return ['male', null];
        }

        return ['tie', null];
    }

    /**
     * Lead unit-economics per gender. Attribution model: a lead that reached
     * "converted" is linked to its patient_id; we sum that patient's
     * package_advances cash in the same date range and divide by leads
     * generated. Answers "what's every fresh lead worth to us?"
     *
     * Filters match the existing revenue sources (cash_flow=in, not adjusted,
     * not tax, not cancelled, positive amount) so the numbers reconcile with
     * Sales/Revenue tiles.
     *
     * @return array{
     *   female: array{leads:int, revenue:float, rev_per_lead:float, rev_per_conv:float, conversions:int},
     *   male:   array{leads:int, revenue:float, rev_per_lead:float, rev_per_conv:float, conversions:int},
     * }
     */
    private function economics(MetricScope $scope, DateRange $range, array $funnel): array
    {
        $start = $range->startString().' 00:00:00';
        $end = $range->endString().' 23:59:59';

        // Pull patient_id list for leads that converted in range, split by gender.
        $convertedIds = $this->statusIds($scope->accountId)['converted'];
        $convertedRows = $this->baseQuery($scope)
            ->whereNotNull('l.patient_id')
            ->whereIn('l.lead_status_id', $convertedIds)
            ->whereBetween('l.created_at', [$start, $end])
            ->selectRaw('l.patient_id, '.$this->genderCase().' AS eg')
            ->get();

        $patientIdsF = [];
        $patientIdsM = [];
        foreach ($convertedRows as $r) {
            $g = (string) $r->eg;
            if ($g === 'female') {
                $patientIdsF[] = (int) $r->patient_id;
            } elseif ($g === 'male') {
                $patientIdsM[] = (int) $r->patient_id;
            }
        }

        $revF = $this->sumRevenueForPatients($scope, array_unique($patientIdsF), $start, $end);
        $revM = $this->sumRevenueForPatients($scope, array_unique($patientIdsM), $start, $end);

        $leadsF = (int) ($funnel['generated']['female'] ?? 0);
        $leadsM = (int) ($funnel['generated']['male'] ?? 0);
        $convF = (int) ($funnel['converted']['female'] ?? 0);
        $convM = (int) ($funnel['converted']['male'] ?? 0);

        return [
            'female' => [
                'leads' => $leadsF,
                'revenue' => $revF,
                'rev_per_lead' => $leadsF > 0 ? round($revF / $leadsF, 2) : 0.0,
                'rev_per_conv' => $convF > 0 ? round($revF / $convF, 2) : 0.0,
                'conversions' => $convF,
            ],
            'male' => [
                'leads' => $leadsM,
                'revenue' => $revM,
                'rev_per_lead' => $leadsM > 0 ? round($revM / $leadsM, 2) : 0.0,
                'rev_per_conv' => $convM > 0 ? round($revM / $convM, 2) : 0.0,
                'conversions' => $convM,
            ],
        ];
    }

    /**
     * @param  list<int>  $patientIds
     */
    private function sumRevenueForPatients(MetricScope $scope, array $patientIds, string $start, string $end): float
    {
        if ($patientIds === []) {
            return 0.0;
        }

        // Net cash = cash_in − refund_out. Finance policy Q3
        // (2026-05-03): Marketing-side "revenue per lead" reports
        // must reflect actual money received, not gross billed
        // amounts. Refunds during the same period subtract from the
        // patient's contribution. Pre-fix this query summed only
        // `cash_flow='in'` and never deducted refunds, inflating
        // marketing revenue by the full refund volume.
        $base = DB::table('package_advances as pa')
            ->join('packages as pk', 'pk.id', '=', 'pa.package_id')
            ->join('appointments as a', 'a.id', '=', 'pk.appointment_id')
            ->where('pa.account_id', $scope->accountId)
            ->whereNull('pa.deleted_at')
            ->whereBetween('pa.created_at', [$start, $end])
            ->whereIn('a.patient_id', $patientIds);

        if ($scope->isBranchScoped() && $scope->branchIds !== null && $scope->branchIds !== []) {
            $base->whereIn('pk.location_id', $scope->branchIds);
        }

        $row = (clone $base)
            ->selectRaw("
                COALESCE(SUM(CASE
                    WHEN pa.cash_flow = 'in'
                        AND pa.is_adjustment = 0
                        AND pa.is_tax = 0
                        AND pa.is_cancel = 0
                        AND pa.cash_amount > 0
                    THEN pa.cash_amount ELSE 0 END), 0) AS cash_in,
                COALESCE(SUM(CASE
                    WHEN pa.cash_flow = 'out'
                        AND pa.is_refund = 1
                    THEN pa.cash_amount ELSE 0 END), 0) AS cash_out_refund
            ")
            ->first();

        $cashIn = (float) ($row->cash_in ?? 0);
        $cashOut = (float) ($row->cash_out_refund ?? 0);

        // Floor at 0 — Marketing surfaces shouldn't show negative
        // revenue (would happen if refunds in this window exceed the
        // window's inflows; the missing inflows landed in a prior
        // window).
        return round(max(0.0, $cashIn - $cashOut), 2);
    }

    /**
     * Rupee potential of leads stuck mid-funnel. For each gender:
     *   stuck_booked   = booked - arrived   (didn't show up)
     *   stuck_arrived  = arrived - converted (came but didn't buy)
     *   value          = stuck × rev_per_conversion × rescue_rate
     *
     * Rescue rate is a conservative 20% (industry rule of thumb for
     * re-engagement campaigns). Exposed as rescue_rate_pct so the UI can
     * show the assumption transparently.
     *
     * @return array<string, mixed>
     */
    private function rescue(array $funnel, array $economics): array
    {
        $rescueRate = 0.20;

        $computeSide = static function (string $gender) use ($funnel, $economics, $rescueRate): array {
            $booked = (int) ($funnel['booked'][$gender] ?? 0);
            $arrived = (int) ($funnel['arrived'][$gender] ?? 0);
            $converted = (int) ($funnel['converted'][$gender] ?? 0);
            $rpc = (float) ($economics[$gender]['rev_per_conv'] ?? 0);

            $stuckBooked = max(0, $booked - $arrived);
            $stuckArrived = max(0, $arrived - $converted);
            $stuckTotal = $stuckBooked + $stuckArrived;

            $potential = round($stuckTotal * $rpc * $rescueRate, 2);

            return [
                'stuck_booked' => $stuckBooked,
                'stuck_arrived' => $stuckArrived,
                'stuck_total' => $stuckTotal,
                'rev_per_conv' => $rpc,
                'potential' => $potential,
            ];
        };

        $f = $computeSide('female');
        $m = $computeSide('male');

        return [
            'rescue_rate_pct' => $rescueRate * 100,
            'female' => $f,
            'male' => $m,
            'potential_total' => round($f['potential'] + $m['potential'], 2),
            'stuck_total' => $f['stuck_total'] + $m['stuck_total'],
        ];
    }

    /**
     * 4-stage cumulative funnel counts split by gender for the given range.
     *
     * @return array<string, array{male:int, female:int, unknown:int, total:int}>
     */
    private function funnel(MetricScope $scope, DateRange $range): array
    {
        $start = $range->startString().' 00:00:00';
        $end = $range->endString().' 23:59:59';

        $query = $this->baseQuery($scope)
            ->whereBetween('l.created_at', [$start, $end]);

        $ids = $this->statusIds($scope->accountId);
        $booked = implode(',', $ids['booked']);
        $arrived = implode(',', $ids['arrived']);
        $converted = implode(',', $ids['converted']);

        $rows = $query
            ->selectRaw($this->genderCase().' AS effective_gender')
            ->selectRaw('COUNT(*) AS cnt_generated')
            ->selectRaw("SUM(CASE WHEN l.lead_status_id IN ({$booked}) THEN 1 ELSE 0 END) AS cnt_booked")
            ->selectRaw("SUM(CASE WHEN l.lead_status_id IN ({$arrived}) THEN 1 ELSE 0 END) AS cnt_arrived")
            ->selectRaw("SUM(CASE WHEN l.lead_status_id IN ({$converted}) THEN 1 ELSE 0 END) AS cnt_converted")
            ->groupBy('effective_gender')
            ->get();

        $stageCols = [
            'generated' => 'cnt_generated',
            'booked' => 'cnt_booked',
            'arrived' => 'cnt_arrived',
            'converted' => 'cnt_converted',
        ];
        $out = [];
        foreach ($stageCols as $key => $_) {
            $out[$key] = ['male' => 0, 'female' => 0, 'unknown' => 0, 'total' => 0];
        }

        foreach ($rows as $row) {
            $g = (string) $row->effective_gender;
            if (! in_array($g, ['male', 'female', 'unknown'], true)) {
                continue;
            }
            foreach ($stageCols as $key => $col) {
                $out[$key][$g] += (int) $row->{$col};
                $out[$key]['total'] += (int) $row->{$col};
            }
        }

        return $out;
    }

    /**
     * 12-week trailing rate series per gender. Range arg ignored — trend is
     * always a trailing window so the shape stays stable across date filters.
     *
     * @return array{
     *   series: list<array{week_start:string, f_rate:float, m_rate:float, f_gen:int, m_gen:int, f_conv:int, m_conv:int}>,
     *   f_avg_rate: float,
     *   m_avg_rate: float,
     * }
     */
    private function trend(MetricScope $scope): array
    {
        $endOfWeek = CarbonImmutable::now()->endOfWeek();
        $startOfFirstWeek = $endOfWeek->subWeeks(self::TREND_WEEKS - 1)->startOfWeek();

        $convertedList = implode(',', $this->statusIds($scope->accountId)['converted']);

        $rows = $this->baseQuery($scope)
            ->whereBetween('l.created_at', [
                $startOfFirstWeek->format('Y-m-d 00:00:00'),
                $endOfWeek->format('Y-m-d 23:59:59'),
            ])
            ->selectRaw('DATE(DATE_SUB(l.created_at, INTERVAL WEEKDAY(l.created_at) DAY)) AS week_start')
            ->selectRaw($this->genderCase().' AS effective_gender')
            ->selectRaw('COUNT(*) AS cnt_gen')
            ->selectRaw("SUM(CASE WHEN l.lead_status_id IN ({$convertedList}) THEN 1 ELSE 0 END) AS cnt_conv")
            ->groupBy('week_start', 'effective_gender')
            ->orderBy('week_start')
            ->get();

        // Index by week → gender → counts.
        $weekly = [];
        foreach ($rows as $row) {
            $wk = (string) $row->week_start;
            $g = (string) $row->effective_gender;
            $weekly[$wk][$g] = [
                'gen' => (int) $row->cnt_gen,
                'conv' => (int) $row->cnt_conv,
            ];
        }

        $series = [];
        $fRateSum = 0.0;
        $mRateSum = 0.0;
        $weeksWithData = 0;

        $cursor = $startOfFirstWeek;
        for ($i = 0; $i < self::TREND_WEEKS; $i++) {
            $key = $cursor->format('Y-m-d');
            $f = $weekly[$key]['female'] ?? ['gen' => 0, 'conv' => 0];
            $m = $weekly[$key]['male'] ?? ['gen' => 0, 'conv' => 0];

            $fRate = $f['gen'] > 0 ? round(($f['conv'] / $f['gen']) * 100, 1) : 0.0;
            $mRate = $m['gen'] > 0 ? round(($m['conv'] / $m['gen']) * 100, 1) : 0.0;

            $series[] = [
                'week_start' => $key,
                'f_rate' => $fRate,
                'm_rate' => $mRate,
                'f_gen' => $f['gen'],
                'm_gen' => $m['gen'],
                'f_conv' => $f['conv'],
                'm_conv' => $m['conv'],
            ];

            if (($f['gen'] + $m['gen']) > 0) {
                $fRateSum += $fRate;
                $mRateSum += $mRate;
                $weeksWithData++;
            }

            $cursor = $cursor->addWeek();
        }

        return [
            'series' => $series,
            'f_avg_rate' => $weeksWithData > 0 ? round($fRateSum / $weeksWithData, 1) : 0.0,
            'm_avg_rate' => $weeksWithData > 0 ? round($mRateSum / $weeksWithData, 1) : 0.0,
        ];
    }

    /**
     * Top N lead sources in range with F / M conversion rates.
     *
     * @return list<array{
     *   source_id:int|null, source_name:string,
     *   total:int,
     *   female:array{gen:int, conv:int, rate:float},
     *   male:array{gen:int, conv:int, rate:float},
     * }>
     */
    private function sources(MetricScope $scope, DateRange $range): array
    {
        $start = $range->startString().' 00:00:00';
        $end = $range->endString().' 23:59:59';
        $convertedList = implode(',', $this->statusIds($scope->accountId)['converted']);

        $rows = $this->baseQuery($scope)
            ->leftJoin('lead_sources as ls', 'ls.id', '=', 'l.lead_source_id')
            ->whereBetween('l.created_at', [$start, $end])
            ->selectRaw('l.lead_source_id AS source_id')
            ->selectRaw("COALESCE(ls.name, 'Unassigned') AS source_name")
            ->selectRaw($this->genderCase().' AS effective_gender')
            ->selectRaw('COUNT(*) AS cnt_gen')
            ->selectRaw("SUM(CASE WHEN l.lead_status_id IN ({$convertedList}) THEN 1 ELSE 0 END) AS cnt_conv")
            ->groupBy('source_id', 'source_name', 'effective_gender')
            ->get();

        // Re-shape: source_id → {name, genders}
        $bySource = [];
        foreach ($rows as $row) {
            $sid = $row->source_id === null ? 0 : (int) $row->source_id;
            if (! isset($bySource[$sid])) {
                $bySource[$sid] = [
                    'source_id' => $row->source_id === null ? null : (int) $row->source_id,
                    'source_name' => (string) $row->source_name,
                    'total' => 0,
                    'female' => ['gen' => 0, 'conv' => 0, 'rate' => 0.0],
                    'male' => ['gen' => 0, 'conv' => 0, 'rate' => 0.0],
                ];
            }
            $g = (string) $row->effective_gender;
            $bySource[$sid]['total'] += (int) $row->cnt_gen;
            if ($g === 'female' || $g === 'male') {
                $bySource[$sid][$g]['gen'] += (int) $row->cnt_gen;
                $bySource[$sid][$g]['conv'] += (int) $row->cnt_conv;
            }
        }

        foreach ($bySource as &$s) {
            $s['female']['rate'] = $s['female']['gen'] > 0
                ? round(($s['female']['conv'] / $s['female']['gen']) * 100, 1)
                : 0.0;
            $s['male']['rate'] = $s['male']['gen'] > 0
                ? round(($s['male']['conv'] / $s['male']['gen']) * 100, 1)
                : 0.0;
        }
        unset($s);

        usort($bySource, static fn ($a, $b) => $b['total'] <=> $a['total']);

        return array_values(array_slice($bySource, 0, self::SOURCES_LIMIT));
    }

    private function baseQuery(MetricScope $scope): Builder
    {
        $q = DB::table('leads as l')
            ->leftJoin('users as p', function ($join) {
                $join->on('p.id', '=', 'l.patient_id')
                    ->where('p.user_type_id', 3);
            })
            ->where('l.account_id', $scope->accountId)
            ->whereNull('l.deleted_at');

        if ($scope->isBranchScoped() && $scope->branchIds !== null && $scope->branchIds !== []) {
            $q->whereIn('l.location_id', $scope->branchIds);
        }

        return $q;
    }

    /**
     * Resolve effective gender from the lead row + patient row. Explicit
     * allowlist (1,2) so any future non-binary/other encoding falls to
     * 'unknown' safely rather than being silently mapped to one of the
     * two columns. Patient gender wins when present to honour retroactive
     * corrections on the patient record.
     */
    private function genderCase(): string
    {
        return 'CASE '
            ."WHEN p.gender IN (1,2) THEN (CASE p.gender WHEN 1 THEN 'male' WHEN 2 THEN 'female' END) "
            ."WHEN l.gender IN (1,2) THEN (CASE l.gender WHEN 1 THEN 'male' WHEN 2 THEN 'female' END) "
            ."ELSE 'unknown' END";
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(): array
    {
        $zero = ['male' => 0, 'female' => 0, 'unknown' => 0, 'total' => 0];

        $gEmpty = [
            'leads' => 0, 'revenue' => 0.0, 'rev_per_lead' => 0.0, 'rev_per_conv' => 0.0,
            'conversions' => 0,
            'prev' => ['rev_per_lead' => 0.0, 'rev_per_conv' => 0.0, 'leads' => 0, 'conversions' => 0, 'range' => ['from' => '', 'to' => '']],
            'delta' => ['rev_per_lead_pct' => null, 'rev_per_conv_pct' => null],
        ];
        $rEmpty = ['stuck_booked' => 0, 'stuck_arrived' => 0, 'stuck_total' => 0, 'rev_per_conv' => 0.0, 'potential' => 0.0];

        return [
            'funnel' => [
                'generated' => $zero, 'booked' => $zero, 'arrived' => $zero, 'converted' => $zero,
            ],
            'trend' => ['series' => [], 'f_avg_rate' => 0.0, 'm_avg_rate' => 0.0],
            'sources' => [],
            'economics' => [
                'female' => $gEmpty,
                'male' => $gEmpty,
                'by_category' => [],
                'maturity_days' => self::MATURITY_DAYS,
            ],
            'rescue' => [
                'rescue_rate_pct' => 20.0,
                'female' => $rEmpty, 'male' => $rEmpty,
                'potential_total' => 0.0, 'stuck_total' => 0,
            ],
        ];
    }
}
