<?php

namespace App\Observers;

use App\Models\Profile;
use App\Models\Group;
use App\Jobs\BackupProfileToGoogleDrive;
use Illuminate\Support\Facades\Log;

class ProfileFileObserver
{
    /**
     * Handle the Profile "updated" event.
     *
     * @param  \App\Models\Profile  $profile
     * @return void
     */
    public function updated(Profile $profile)
    {
        // Skip backup if only status or timestamps changed (no actual data change)
        $dirtyAttributes = $profile->getDirty();
        $relevantChanges = array_diff_key($dirtyAttributes, array_flip(['status', 'last_run_at', 'last_run_by', 'updated_at']));
        
        if (empty($relevantChanges)) {
            // Only status/timestamp changed, no need to backup
            return;
        }
        
        // Check if group has auto_backup enabled
        $group = Group::find($profile->group_id);
        
        if (!$group || !$group->auto_backup) {
            return;
        }

        // Check if Google Drive credentials exist
        $credentialsPath = storage_path('app/google-drive-credentials.json');
        $tokenPath = storage_path('app/google-drive-token.json');
        
        if (!file_exists($credentialsPath) || !file_exists($tokenPath)) {
            Log::warning('Google Drive not configured for auto backup');
            return;
        }

        // Dispatch backup job to queue (async)
        Log::info("Dispatching backup job for profile {$profile->id}, changes: " . json_encode(array_keys($relevantChanges)));
        BackupProfileToGoogleDrive::dispatch($profile->id, array_keys($relevantChanges))
            ->onQueue('backups')
            ->delay(now()->addSeconds(5)); // Delay 5 seconds to batch multiple changes
    }

    /**
     * Handle the Profile "deleted" event.
     *
     * @param  \App\Models\Profile  $profile
     * @return void
     */
    public function deleted(Profile $profile)
    {
        // Optionally handle deletion - could delete from Google Drive or keep as archive
        // For now, we'll keep files in Google Drive as backup even after deletion
    }
}

