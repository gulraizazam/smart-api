<?php

declare(strict_types=1);
namespace App\Console\Commands;

use App\Jobs\SyncSingleAppointmentJob;
use App\Models\Appointments;
use App\Models\HeavyLifter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Queue;

class HandleHeavyLifting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:handle-heavy-lifting';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handle large requests divided into chunks';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the job.
     */
    public function handle(): int
    {
        try {
            $heavyLifts = HeavyLifter::limit(5)->get();

            if ($heavyLifts->isEmpty()) {
                return Command::SUCCESS;
            }

            foreach ($heavyLifts as $heavyLift) {
                $payload = json_decode($heavyLift->payload, true);

                if (!is_array($payload)) {
                    Log::warning('HandleHeavyLifting: malformed payload, skipping', [
                        'heavy_lifter_id' => $heavyLift->id,
                    ]);
                    HeavyLifter::where('id', $heavyLift->id)->forceDelete();
                    continue;
                }

                switch ($heavyLift->type) {
                    case 'upload-appointments':
                        $this->processAppointmentBatch($payload);
                        break;
                    default:
                        Log::warning('HandleHeavyLifting: unknown type', [
                            'heavy_lifter_id' => $heavyLift->id,
                            'type' => $heavyLift->type,
                        ]);
                        break;
                }

                HeavyLifter::where('id', $heavyLift->id)->forceDelete();
            }
        } catch (\Exception $e) {
            Log::error('HandleHeavyLifting exception', ['error' => $e->getMessage()]);
        }

        return Command::SUCCESS;
    }

    /**
     * Fetch a batch of appointment ids and dispatch sync jobs for each.
     *
     * Supports two payload formats:
     *  - NEW (seek pagination — fast at any depth):
     *      ['account_id' => int, 'id_min' => int, 'id_max' => int]
     *      Uses indexed range scan: WHERE id BETWEEN id_min AND id_max.
     *      Per-batch query time: ~1ms regardless of batch position.
     *
     *  - LEGACY (offset pagination — kept for backward compat with any
     *            in-flight rows from before the refactor):
     *      ['account_id' => int, 'offset' => int, 'records_per_page' => int]
     *      Uses LIMIT/OFFSET — slows linearly with offset depth (up to 100ms+).
     *
     * Once the queue has drained any legacy rows, the LEGACY branch becomes
     * dead code and can be removed.
     */
    private function processAppointmentBatch(array $payload): void
    {
        if (empty($payload['account_id'])) {
            Log::warning('HandleHeavyLifting: appointment batch payload missing account_id', $payload);
            return;
        }

        $query = Appointments::where('account_id', $payload['account_id']);

        if (isset($payload['id_min'], $payload['id_max'])) {
            // NEW: seek pagination via indexed id range
            $query->whereBetween('id', [(int) $payload['id_min'], (int) $payload['id_max']]);
        } elseif (isset($payload['offset'], $payload['records_per_page'])) {
            // LEGACY: offset pagination — kept for backward compatibility
            $query->orderBy('id', 'asc')
                ->limit((int) $payload['records_per_page'])
                ->offset((int) $payload['offset']);
        } else {
            Log::warning('HandleHeavyLifting: appointment batch payload missing id range / offset', $payload);
            return;
        }

        $appointments = $query->select('id')->get();

        if ($appointments->isEmpty()) {
            return;
        }

        $jobs = [];
        foreach ($appointments as $appointment) {
            $jobs[] = new SyncSingleAppointmentJob([
                'account_id' => $payload['account_id'],
                'appointment_id' => $appointment->id,
            ]);
        }

        Queue::bulk($jobs);
    }
}
