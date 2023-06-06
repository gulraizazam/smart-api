<?php

namespace App\Console\Commands;

use App\Models\DBBackups;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MySQLDumpRemover extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup-old-remove';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs the mysqldump utility using info from .env to remove old backups';

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
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $ds = DIRECTORY_SEPARATOR;

            $db_backups = DBBackups::whereDate('created_at', '<=', Carbon::parse(Carbon::now())->subDays(30)->toDateString())
                ->get();
            if ($db_backups) {
                foreach ($db_backups as $db_backup) {
                    @unlink($db_backup->path.$ds.$db_backup->file);
                    DBBackups::where('id', $db_backup->id)->delete();
                }
            }
        } catch (\Exception $exception) {

        }
    }
}
