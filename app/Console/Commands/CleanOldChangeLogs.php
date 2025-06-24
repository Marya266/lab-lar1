<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ChangeLog;
use Carbon\Carbon;

class CleanOldChangeLogs extends Command
{
    protected $signature = 'logs:clean {--days=30 : Number of days to keep logs}';
    protected $description = 'Clean old change logs older than specified days';

    public function handle(): int
    {
        $days = $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);
        
        $deletedCount = ChangeLog::where('created_at', '<', $cutoffDate)->delete();
        
        $this->info("Удалено логов старше {$days} дней: {$deletedCount}");
        
        return Command::SUCCESS;
    }
}
