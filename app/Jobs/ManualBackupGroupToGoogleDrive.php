<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Group;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ManualBackupGroupToGoogleDrive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $groupId;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600; // 10 minutes for large backups

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($groupId)
    {
        $this->groupId = $groupId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $group = Group::find($this->groupId);
            
            if (!$group) {
                Log::warning("Group {$this->groupId} not found for manual backup");
                return;
            }

            Log::info("Starting manual backup for group {$group->id} ({$group->name})");

            // Check if Google Drive is configured
            $credentialsPath = storage_path('app/google-drive-credentials.json');
            $tokenPath = storage_path('app/google-drive-token.json');
            
            if (!file_exists($credentialsPath) || !file_exists($tokenPath)) {
                Log::error('Google Drive not configured for manual backup');
                return;
            }

            $googleDriveService = app(GoogleDriveService::class);

            // Get or create Google Drive folder
            $folderId = $group->google_drive_folder_id;
            
            if (!$folderId) {
                $folderId = $googleDriveService->getOrCreateGroupFolder($group->id, $group->name);
                
                if ($folderId) {
                    $group->google_drive_folder_id = $folderId;
                    $group->save();
                } else {
                    Log::error("Failed to create Google Drive folder for group {$group->id}");
                    return;
                }
            }

            // Backup all files in the group folder
            $groupFolder = 'profiles/' . $group->id;
            
            if (!Storage::disk('public')->exists($groupFolder)) {
                Log::warning("Group folder not found: {$groupFolder}");
                return;
            }
            
            $files = Storage::disk('public')->files($groupFolder);
            $totalFiles = count($files);
            $successCount = 0;
            $skippedCount = 0;
            $failCount = 0;
            
            Log::info("Found {$totalFiles} files to backup for group {$group->id}");
            
            foreach ($files as $index => $file) {
                $fileName = basename($file);
                $localPath = storage_path('app/public/' . $file);
                
                Log::debug("Backing up file " . ($index + 1) . "/{$totalFiles}: {$fileName}");
                
                $result = $googleDriveService->backupFile($localPath, $fileName, $folderId);
                
                if ($result === 'skipped') {
                    $skippedCount++;
                    Log::debug("File unchanged, skipped: {$fileName}");
                } elseif ($result) {
                    $successCount++;
                } else {
                    $failCount++;
                    Log::error("Failed to backup file: {$fileName}");
                }
            }

            Log::info("Manual backup completed for group {$group->id}: {$successCount} uploaded, {$skippedCount} skipped, {$failCount} failed out of {$totalFiles} total");

        } catch (\Exception $e) {
            Log::error("Manual backup job failed for group {$this->groupId}: " . $e->getMessage());
            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error("Manual backup job permanently failed for group {$this->groupId}: " . $exception->getMessage());
    }
}
