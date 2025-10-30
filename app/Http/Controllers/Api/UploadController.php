<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Profile;
use App\Models\Group;
use App\Jobs\BackupProfileToGoogleDrive;
use Illuminate\Support\Facades\Log;

class UploadController extends BaseController
{
    public function store(Request $request)
    {
        if ($files = $request->file('file')) {
            try {
                if($request->file('file')->getSize() > 0) {
                    // Get group_id from request or find from profile
                    $groupId = $request->input('group_id');
                    
                    // If no group_id provided, try to find from filename (s3_path)
                    if (!$groupId) {
                        $fileName = $request->file_name;
                        // Extract s3_path from filename (remove extensions like _ext_cmd.json, _restore_cookie.json, etc)
                        $s3Path = preg_replace('/(_ext_cmd\.json|_ext_info\.json|_restore_cookie\.json|_import_cookie\.json)$/', '', $fileName);
                        
                        // Find profile by s3_path
                        $profile = Profile::where('s3_path', $s3Path)->first();
                        if ($profile) {
                            $groupId = $profile->group_id;
                        } else {
                            // Default to 1 if profile not found
                            $groupId = 1;
                        }
                    }
                    
                    // Store file in group-specific folder
                    $file = $request->file->storeAs('public/profiles/' . $groupId, $request->file_name);

                    // If upload success -> $file = public\/profiles\/{group_id}\/filename.ext
                    $fileName = str_replace("public/profiles/{$groupId}/", "", $file);
                    
                    // Trigger backup to Google Drive if auto_backup is enabled
                    $this->triggerBackupIfNeeded($groupId, $fileName);
                    
                    // Return path without group_id for backward compatibility with client
                    return $this->getJsonResponse(true, 'Thành công', ['path' => 'storage/profiles', 'file_name' => $fileName]);
                }else {
                    return $this->getJsonResponse(false, 'Thất bại', ['message' => 'File rỗng']);
                }
            } catch (\Exception $ex){
                return $this->getJsonResponse(false, 'Thất bại', $ex);
            }
            // store file into profile folder
        }
        return $this->getJsonResponse(false, 'Thất bại', []);
    }

    public function delete(Request $request) {
        // Get group_id from request or find from profile
        $groupId = $request->input('group_id');
        
        // If no group_id provided, try to find from filename
        if (!$groupId) {
            $fileName = $request->file;
            // Extract s3_path from filename
            $s3Path = preg_replace('/(_ext_cmd\.json|_ext_info\.json|_restore_cookie\.json|_import_cookie\.json)$/', '', $fileName);
            
            // Find profile by s3_path
            $profile = Profile::where('s3_path', $s3Path)->first();
            if ($profile) {
                $groupId = $profile->group_id;
            }
        }
        
        if ($groupId) {
            $fullLocation = 'public/profiles/' . $groupId . '/' . $request->file;
            
            // Also try to delete from old location for backward compatibility
            if (!Storage::exists($fullLocation)) {
                $fullLocation = 'public/profiles/' . $request->file;
            }
        } else {
            // Fallback to old location
            $fullLocation = 'public/profiles/' . $request->file;
        }
        
        Storage::delete($fullLocation);
        return $this->getJsonResponse(true, 'Thành công', []);
    }

    /**
     * Trigger backup job if group has auto_backup enabled
     *
     * @param int $groupId
     * @param string $fileName
     * @return void
     */
    protected function triggerBackupIfNeeded($groupId, $fileName)
    {
        try {
            $group = Group::find($groupId);
            
            if (!$group || !$group->auto_backup) {
                return;
            }

            // Check if Google Drive credentials exist
            $credentialsPath = storage_path('app/google-drive-credentials.json');
            $tokenPath = storage_path('app/google-drive-token.json');
            
            if (!file_exists($credentialsPath) || !file_exists($tokenPath)) {
                Log::debug('Google Drive not configured for auto backup');
                return;
            }

            // Extract s3_path from filename to find profile
            $s3Path = preg_replace('/(_ext_cmd\.json|_ext_info\.json|_restore_cookie\.json|_import_cookie\.json)$/', '', $fileName);
            $profile = Profile::where('s3_path', $s3Path)->first();
            
            if ($profile) {
                // Dispatch backup job for this profile
                Log::info("Triggering backup for uploaded file: {$fileName} (Profile ID: {$profile->id}, Group ID: {$groupId})");
                
                // Ensure job is dispatched immediately without delay for file uploads
                BackupProfileToGoogleDrive::dispatch($profile->id, ['file_upload'])
                    ->onQueue('backups');
                
                Log::info("Backup job dispatched successfully for profile {$profile->id}");
            } else {
                Log::debug("Profile not found for file: {$fileName}, skipping backup");
            }
        } catch (\Exception $e) {
            Log::error("Error triggering backup for file {$fileName}: " . $e->getMessage());
        }
    }
}
