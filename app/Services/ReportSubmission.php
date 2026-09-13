<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\MonthlyReport;
use Illuminate\Support\Facades\DB;

class ReportSubmission
{
    public function daily(int $userId, string $date, string $content): DailyReport
    {
        return DB::transaction(function () use ($userId, $date, $content) {
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();

            return DailyReport::updateOrCreate(['user_id' => $userId, 'report_date' => $date], ['content' => $content, 'status' => 'pending']);
        });
    }

    public function monthly(int $userId, array $input): MonthlyReport
    {
        return DB::transaction(function () use ($userId, $input) {
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();

            return MonthlyReport::updateOrCreate(
                ['user_id' => $userId, 'month' => $input['month'], 'year' => $input['year']],
                ['summary' => $input['summary'], 'achievements' => $input['achievements'] ?? [], 'obstacles' => $input['obstacles'] ?? [], 'status' => 'pending']
            );
        });
    }
}
