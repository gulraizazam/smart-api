<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$pbs = App\Models\PackageBundles::with(['bundle','service'])->where('package_id', 44395)->get();
foreach($pbs as $pb) {
    echo 'id:' . $pb->id . ' bundle_id:' . $pb->bundle_id . ' source_type:' . $pb->source_type 
        . ' bundle_name:' . ($pb->bundle ? $pb->bundle->name : 'NULL') 
        . ' service_name:' . ($pb->service ? $pb->service->name : 'NULL') . PHP_EOL;
}
