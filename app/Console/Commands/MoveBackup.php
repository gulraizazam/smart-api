<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
class MoveBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'move:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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

            $d = DIRECTORY_SEPARATOR ;

            $path1 = storage_path() . $d . 'app' . $d . 'backups' ;

            is_dir($path1) ?: mkdir( $path1 , 0755, true);

            $contents = Storage::disk('local_for_backup')->allFiles();

            foreach ( $contents as $content ) {

                $data = explode('-', $content);

                $date = $data[1].'-'.$data[2].'-'.$data[3];

                $date = Carbon::parse($date)->format('Y-m-d');

                if ( $date <= Carbon::now()->subDays(1)->format('Y-m-d') ) {
                    File::move(storage_path().$d.'app'.$d.'Roles-Permissions-Manager'.$d.$content, storage_path().$d.'app'.$d.'backups'.$d.$content);
                }
            }

            $contents_old = Storage::disk('backups')->allFiles();

            foreach ($contents_old as $contact_delete ){

                $data = explode('-', $contact_delete);

                $date = $data[1].'-'.$data[2].'-'.$data[3];

                $date = Carbon::parse($date)->format('Y-m-d');

                if ( $date <= Carbon::now()->subDays(5)->format('Y-m-d') ) {

                    File::delete(storage_path().$d.'app'.$d.'backups'.$d.$contact_delete);
                }
            }

        } catch ( \Exception $exception ) {
            $this->error($exception->getFile() .'-----'. $exception->getMessage() . '-----code------'. $exception->getCode());
        }
    }
}