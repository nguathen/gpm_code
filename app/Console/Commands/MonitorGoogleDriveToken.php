<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Log;

class MonitorGoogleDriveToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'googledrive:monitor-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor and auto-refresh Google Drive token to ensure it never expires';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $credentialsPath = storage_path('app/google-drive-credentials.json');
        $tokenPath = storage_path('app/google-drive-token.json');
        
        if (!file_exists($credentialsPath) || !file_exists($tokenPath)) {
            $this->warn('Google Drive not configured. Skipping token monitoring.');
            return 0;
        }

        try {
            $this->info('Checking Google Drive token status...');
            
            // Initialize service (will auto-refresh if needed)
            $service = app(GoogleDriveService::class);
            
            // Test connection
            $client = new \Google\Client();
            $client->setAuthConfig($credentialsPath);
            $tokenData = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($tokenData);
            
            if ($client->isAccessTokenExpired()) {
                $this->warn('Token was expired, but should have been auto-refreshed');
            } else {
                $expiresIn = $tokenData['expires_in'] ?? 0;
                $createdAt = $tokenData['created'] ?? time();
                $expiresAt = $createdAt + $expiresIn;
                $remainingTime = $expiresAt - time();
                
                $this->info('Token is valid');
                $this->info('Expires in: ' . gmdate('H:i:s', $remainingTime));
            }
            
            // Test API call
            $driveService = new \Google\Service\Drive($client);
            $about = $driveService->about->get(['fields' => 'user']);
            
            $this->info('Connected as: ' . $about->getUser()->getDisplayName());
            $this->info('Email: ' . $about->getUser()->getEmailAddress());
            $this->info('✓ Google Drive connection is healthy');
            
            Log::info('Google Drive token monitoring: Connection healthy');
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Google Drive token monitoring failed: ' . $e->getMessage());
            Log::error('Google Drive token monitoring failed: ' . $e->getMessage());
            return 1;
        }
    }
}
