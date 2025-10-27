<?php

namespace App\Console\Commands;

use App\Services\GoogleDriveService;
use Illuminate\Console\Command;

class CleanupGoogleDriveTempFiles extends Command
{
    protected $signature = 'googledrive:cleanup-temp {--minutes=60 : Age in minutes of temp files to clean}';
    protected $description = 'Cleanup orphaned Google Drive temp files (.tmp, .backup)';

    public function handle()
    {
        $minutes = $this->option('minutes');
        
        $this->info("Cleaning temp files older than {$minutes} minutes...");
        
        try {
            $service = app(GoogleDriveService::class);
            $result = $service->cleanupTempFiles($minutes);
            
            $this->info("✓ Cleaned {$result['cleaned']} temp files");
            if ($result['freed'] > 0) {
                $this->info("✓ Freed " . $this->formatBytes($result['freed']));
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to cleanup temp files: " . $e->getMessage());
            return 1;
        }
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

