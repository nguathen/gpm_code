<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends BaseController
{
    public function store(Request $request)
    {
        if ($files = $request->file('file')) {
            try {
                if($request->file('file')->getSize() > 0) {
                    // Get group_id from request, default to 1 if not provided
                    $groupId = $request->input('group_id', 1);
                    
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
        // Get group_id from request to delete from correct folder
        $groupId = $request->input('group_id', 1);
        $fullLocation = 'public/profiles/' . $groupId . '/' . $request->file;
        
        // Also try to delete from old location for backward compatibility
        if (!Storage::exists($fullLocation)) {
            $fullLocation = 'public/profiles/' . $request->file;
        }
        
        Storage::delete($fullLocation);
        return $this->getJsonResponse(true, 'Thành công', []);
    }
}
