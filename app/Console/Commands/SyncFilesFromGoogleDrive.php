<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProfileFile;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncFilesFromGoogleDrive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'files:sync-from-drive 
                            {--limit=50 : Maximum number of files to sync per run}
                            {--group= : Sync files for specific group ID only}
                            {--force : Force re-download even if MD5 matches}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically sync files from Google Drive to local storage when files are outdated or missing';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $googleDriveService = app(GoogleDriveService::class);
        
        if (!$googleDriveService->isConfigured()) {
            $this->warn('Google Drive is not configured. Skipping sync.');
            return 0;
        }

        $this->info('Starting file sync from Google Drive...');

        // Get files to sync
        $query = ProfileFile::whereNotNull('google_drive_file_id');
        
        if ($this->option('group')) {
            $query->where('group_id', $this->option('group'));
        }
        
        $files = $query->limit($this->option('limit'))->get();
        
        if ($files->isEmpty()) {
            $this->info('No files to sync.');
            return 0;
        }

        $this->info("Found {$files->count()} files to check.");

        $synced = 0;
        $skipped = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($files->count());
        $bar->start();

        foreach ($files as $profileFile) {
            try {
                $shouldSync = $this->shouldSyncFile($profileFile);
                
                if (!$shouldSync && !$this->option('force')) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Download file from Google Drive
                $localPath = $this->getLocalPath($profileFile);
                $destinationPath = storage_path('app/public/' . $localPath);

                // Ensure directory exists
                $directory = dirname($destinationPath);
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                // Get expected MD5 from DB
                $expectedMd5 = $profileFile->md5_checksum;

                // Download with verification
                $downloadSuccess = $googleDriveService->downloadFileWithVerification(
                    $profileFile->google_drive_file_id,
                    $destinationPath,
                    $expectedMd5
                );

                if ($downloadSuccess) {
                    // Verify downloaded file
                    $downloadedMd5 = md5_file($destinationPath);
                    $downloadedSize = filesize($destinationPath);

                    // Update database record
                    $profileFile->update([
                        'file_path' => $localPath,
                        'file_size' => $downloadedSize,
                        'md5_checksum' => $downloadedMd5,
                    ]);

                    $synced++;
                    Log::info("Synced file from Google Drive: {$profileFile->file_name} (Group: {$profileFile->group_id})");
                } else {
                    $failed++;
                    Log::warning("Failed to sync file from Google Drive: {$profileFile->file_name} (Group: {$profileFile->group_id})");
                }

            } catch (\Exception $e) {
                $failed++;
                Log::error("Error syncing file {$profileFile->file_name}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Sync completed!");
        $this->info("Synced: {$synced}, Skipped: {$skipped}, Failed: {$failed}");

        return 0;
    }

    /**
     * Check if file should be synced
     *
     * @param ProfileFile $profileFile
     * @return bool
     */
    protected function shouldSyncFile(ProfileFile $profileFile)
    {
        $localPath = $this->getLocalPath($profileFile);
        $fullPath = storage_path('app/public/' . $localPath);

        // File doesn't exist locally
        if (!file_exists($fullPath)) {
            return true;
        }

        // File exists but no MD5 in DB
        if (!$profileFile->md5_checksum) {
            return true;
        }

        // Check MD5 of local file
        $localMd5 = md5_file($fullPath);

        // MD5 mismatch - file is outdated
        if ($localMd5 !== $profileFile->md5_checksum) {
            return true;
        }

        // File is up-to-date
        return false;
    }

    /**
     * Get local file path for ProfileFile
     *
     * @param ProfileFile $profileFile
     * @return string
     */
    protected function getLocalPath(ProfileFile $profileFile)
    {
        // Use stored path if available
        if ($profileFile->file_path) {
            return $profileFile->file_path;
        }

        // Build path from group_id and file_name
        return "profiles/{$profileFile->group_id}/{$profileFile->file_name}";
    }
}

