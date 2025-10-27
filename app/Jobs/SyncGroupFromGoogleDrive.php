<?php

namespace App\Jobs;

use App\Models\Group;
use App\Services\GoogleDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGroupFromGoogleDrive implements ShouldQueue
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
    public $timeout = 600; // 10 minutes for large syncs

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
                Log::error("Sync failed: Group ID {$this->groupId} not found.");
                return;
            }

            if (!$group->google_drive_folder_id) {
                Log::error("Sync failed: Group {$group->id} has no Google Drive folder ID.");
                return;
            }

            $googleDriveService = app(GoogleDriveService::class);

            if (!$googleDriveService->isConfigured()) {
                Log::warning("Sync skipped: Google Drive not configured.");
                return;
            }

            $localPath = storage_path('app/public/profiles/' . $group->id);

            Log::info("Starting sync from Google Drive for group {$group->id} ({$group->name})");

            $result = $googleDriveService->syncFromGoogleDrive(
                $group->google_drive_folder_id,
                $localPath
            );

            if ($result['failed'] > 0) {
                Log::error("Sync completed for group {$group->id} with failures: {$result['downloaded']} downloaded, {$result['skipped']} skipped, {$result['failed']} failed out of {$result['total']} total");
                Log::error("Success rate: {$result['success_rate']}%");
                if (!empty($result['failed_files'])) {
                    Log::error("Failed files for group {$group->id}: " . implode(', ', $result['failed_files']));
                }
            } else {
                Log::info("Sync completed successfully for group {$group->id}: {$result['downloaded']} downloaded, {$result['skipped']} skipped, 0 failed out of {$result['total']} total (100% success)");
            }

        } catch (\Exception $e) {
            Log::error("Sync job failed for group {$this->groupId}: " . $e->getMessage());
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
        Log::error("Sync job permanently failed for group {$this->groupId}: " . $exception->getMessage());
    }
}

