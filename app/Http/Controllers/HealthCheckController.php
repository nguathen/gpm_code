<?php

namespace App\Http\Controllers;

use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    /**
     * Google Drive health check
     */
    public function googleDrive()
    {
        $health = [
            'status' => 'healthy',
            'checks' => [],
            'timestamp' => now()->toIso8601String()
        ];

        try {
            // Check Google Drive configuration
            $service = app(GoogleDriveService::class);
            $health['checks']['configuration'] = [
                'status' => $service->isConfigured() ? 'ok' : 'error',
                'message' => $service->isConfigured() ? 'Google Drive configured' : 'Missing credentials or token'
            ];

            // Check disk space
            $path = storage_path('app/public');
            $freeSpace = disk_free_space($path);
            $totalSpace = disk_total_space($path);
            $usedPercent = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);
            
            $health['checks']['disk_space'] = [
                'status' => $usedPercent < 90 ? 'ok' : 'warning',
                'free' => $this->formatBytes($freeSpace),
                'total' => $this->formatBytes($totalSpace),
                'used_percent' => $usedPercent . '%'
            ];

            // Check queue
            $queueSize = DB::table('jobs')->where('queue', 'backups')->count();
            $health['checks']['queue'] = [
                'status' => $queueSize < 100 ? 'ok' : 'warning',
                'pending_jobs' => $queueSize
            ];

            // Check temp files
            $tempFiles = count(glob(storage_path('app/public/profiles/*/*.tmp')));
            $backupFiles = count(glob(storage_path('app/public/profiles/*/*.backup')));
            
            $health['checks']['temp_files'] = [
                'status' => ($tempFiles + $backupFiles) < 50 ? 'ok' : 'warning',
                'tmp_count' => $tempFiles,
                'backup_count' => $backupFiles
            ];

            // Overall status
            $hasError = collect($health['checks'])->contains('status', 'error');
            $hasWarning = collect($health['checks'])->contains('status', 'warning');
            
            if ($hasError) {
                $health['status'] = 'unhealthy';
            } elseif ($hasWarning) {
                $health['status'] = 'degraded';
            }

        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['error'] = $e->getMessage();
        }

        $statusCode = $health['status'] === 'healthy' ? 200 : 
                     ($health['status'] === 'degraded' ? 200 : 503);

        return response()->json($health, $statusCode);
    }

    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

