<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Enums\ResourceType;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\DoctorResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;

/**
 * Per-resource leaderboard for the People section. Doctor segment is
 * implemented; therapist / consultant follow the same pattern once role
 * semantics are defined.
 *
 * For each doctor in scope, computes revenue, conversion, avg client value,
 * upsell revenue, feedback score, patient return rate, patients seen.
 * Company average included for the outlier badges / reference lines.
 *
 * Return shape: { rows: list<resource_row>, company_avg: { ... } }
 */
final class ResourceLeaderboardMetric implements Metric
{
    public function __construct(
        private readonly RevenueMetric $revenue,
        private readonly ConversionMetric $conversion,
        private readonly UpsellMetric $upsell,
        private readonly FeedbackMetric $feedback,
        private readonly PatientReturnMetric $patientReturn,
        private readonly AppointmentsMetric $appointments,
        private readonly DoctorResolver $doctors,
    ) {}

    /**
     * @return array{rows: list<array<string, mixed>>, company_avg: array<string, float>}
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        $doctorIds = $this->doctors->idsInScope($scope);

        if ($doctorIds === []) {
            return ['rows' => [], 'company_avg' => $this->emptyAverages()];
        }

        $names = $this->doctors->names($doctorIds);
        $rows = [];

        foreach ($doctorIds as $doctorId) {
            $docScope = MetricScope::doctor($scope->accountId, $doctorId);

            $revenue = $this->revenue->compute($docScope, $range);
            $conversion = $this->conversion->compute($docScope, $range);
            $upsell = $this->upsell->compute($docScope, $range);
            $feedback = $this->feedback->compute($docScope, $range);
            $return = $this->patientReturn->compute($docScope, $range);
            $appts = $this->appointments->compute($docScope, $range);

            $avgValue = $conversion['total_converted'] > 0
                ? round($revenue['total_revenue'] / $conversion['total_converted'], 2)
                : 0.0;

            $rows[] = [
                'resource_id' => $doctorId,
                'resource_type' => ResourceType::Doctor->value,
                'resource_name' => $names[$doctorId] ?? "User #{$doctorId}",
                'revenue' => $revenue['total_revenue'],
                'conversion_rate' => $conversion['conversion_rate'],
                'avg_client_value' => $avgValue,
                'upsell_revenue' => $upsell['upsell_revenue'],
                'feedback_score' => $feedback['avg_rating'],
                'return_rate' => $return['return_rate'],
                'patients_seen' => $appts['total'],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);

        return [
            'rows' => $rows,
            'company_avg' => $this->companyAverages($rows),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, float>
     */
    private function companyAverages(array $rows): array
    {
        if ($rows === []) {
            return $this->emptyAverages();
        }

        $n = count($rows);
        $keys = ['revenue', 'conversion_rate', 'avg_client_value', 'upsell_revenue', 'feedback_score', 'return_rate', 'patients_seen'];

        $avg = [];
        foreach ($keys as $key) {
            $sum = array_sum(array_column($rows, $key));
            $avg[$key] = round($sum / $n, 2);
        }

        return $avg;
    }

    /**
     * @return array<string, float>
     */
    private function emptyAverages(): array
    {
        return [
            'revenue' => 0.0, 'conversion_rate' => 0.0, 'avg_client_value' => 0.0,
            'upsell_revenue' => 0.0, 'feedback_score' => 0.0, 'return_rate' => 0.0,
            'patients_seen' => 0.0,
        ];
    }
}
