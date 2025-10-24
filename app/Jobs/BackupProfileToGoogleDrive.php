<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Profile;
use App\Models\Group;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class BackupProfileToGoogleDrive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $profileId;
    public $changedAttributes;

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
    public $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($profileId, $changedAttributes = [])
    {
        $this->profileId = $profileId;
        $this->changedAttributes = $changedAttributes;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $profile = Profile::find($this->profileId);
            
            if (!$profile) {
                Log::warning("Profile {$this->profileId} not found for backup");
                return;
            }

            $group = Group::find($profile->group_id);
            
            if (!$group || !$group->auto_backup) {
                Log::info("Backup skipped for profile {$this->profileId} - group auto_backup disabled");
                return;
            }

            // Check if Google Drive is configured
            $credentialsPath = storage_path('app/google-drive-credentials.json');
            $tokenPath = storage_path('app/google-drive-token.json');
            
            if (!file_exists($credentialsPath) || !file_exists($tokenPath)) {
                Log::warning('Google Drive not configured for backup');
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

            // Backup files
            $this->backupProfileFiles($profile, $folderId, $googleDriveService);

            Log::info("Backup completed for profile {$this->profileId}, changes: " . json_encode($this->changedAttributes));

        } catch (\Exception $e) {
            Log::error("Backup job failed for profile {$this->profileId}: " . $e->getMessage());
            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Backup all files related to a profile
     *
     * @param Profile $profile
     * @param string $folderId
     * @param GoogleDriveService $googleDriveService
     * @return void
     */
    protected function backupProfileFiles(Profile $profile, $folderId, GoogleDriveService $googleDriveService)
    {
        $groupId = $profile->group_id;
        $s3Path = $profile->s3_path;
        
        // Get all files in the group folder that belong to this profile
        $groupFolder = 'profiles/' . $groupId;
        
        if (!Storage::disk('public')->exists($groupFolder)) {
            Log::warning("Group folder not found: {$groupFolder}");
            return;
        }
        
        $files = Storage::disk('public')->files($groupFolder);
        $backedUpCount = 0;
        
        foreach ($files as $file) {
            $fileName = basename($file);
            
            // Only backup files that belong to this profile (start with s3_path)
            if (strpos($fileName, $s3Path) === 0) {
                $localPath = storage_path('app/public/' . $file);
                
                // Backup to Google Drive
                $result = $googleDriveService->backupFile($localPath, $fileName, $folderId);
                
                if ($result) {
                    $backedUpCount++;
                    Log::debug("Backed up file: {$fileName}");
                } else {
                    Log::error("Failed to backup file: {$fileName}");
                }
            }
        }

        Log::info("Backed up {$backedUpCount} files for profile {$profile->id}");
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error("Backup job permanently failed for profile {$this->profileId}: " . $exception->getMessage());
    }
}
