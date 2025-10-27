<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class CleanOldLogs extends Command
{
    protected $signature = 'logs:clean {--days=30 : Number of days to keep logs}';
    protected $description = 'Clean old log files to save disk space';

    public function handle()
    {
        $days = $this->option('days');
        $logPath = storage_path('logs');
        $cutoffDate = Carbon::now()->subDays($days);
        
        $this->info("Cleaning logs older than {$days} days (before {$cutoffDate->toDateString()})...");
        
        $files = File::glob($logPath . '/*.log*');
        $deletedCount = 0;
        $freedSpace = 0;
        
        foreach ($files as $file) {
            $fileModified = Carbon::createFromTimestamp(filemtime($file));
            
            if ($fileModified->lt($cutoffDate)) {
                $size = filesize($file);
                
                if (File::delete($file)) {
                    $deletedCount++;
                    $freedSpace += $size;
                    $this->line("Deleted: " . basename($file) . " (" . $this->formatBytes($size) . ")");
                }
            }
        }
        
        $this->info("\nCleaned {$deletedCount} log files, freed " . $this->formatBytes($freedSpace));
        
        return 0;
    }
    
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

