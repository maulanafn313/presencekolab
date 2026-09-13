<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = ['intern_groups', 'intern_group_members', 'kpi_monthly_cache'];
foreach($tables as $t) {
    echo "\n-- Table: $t --\n";
    print_r(DB::select("SHOW CREATE TABLE $t")[0]);
}
