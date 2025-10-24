<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Profile;

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
}
