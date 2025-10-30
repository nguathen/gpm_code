<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Group;
use App\Models\GroupRole;
use App\Models\User;
use App\Models\ProfileFile;
use Illuminate\Support\Facades\Log;

class GroupController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Admin load tất cả groups
        if ($user->role == 2) {
            $groups = Group::where('id', '!=', 0)->orderBy('sort')->get();
        } else {
            // User chỉ load groups được share
            $groupIds = GroupRole::where('user_id', $user->id)->pluck('group_id');
            $groups = Group::where('id', '!=', 0)
                          ->whereIn('id', $groupIds)
                          ->orderBy('sort')
                          ->get();
        }
        
        return $this->getJsonResponse(true, 'Thành công', $groups);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role < 2)
            return $this->getJsonResponse(false, 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!', null);

        $group = new Group();
        $group->name = $request->name;
        $group->sort = $request->sort;
        $group->created_by = $user->id;
        $group->save();

        return $this->getJsonResponse(true, 'Thành công', $group);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if ($user->role < 2)
            return $this->getJsonResponse(false, 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!', null);

        $group = Group::find($id);

        if ($group == null)
            return $this->getJsonResponse(false, 'Group không tồn tại', null);

        $group->name = $request->name;
        $group->sort = $request->sort;
        $group->save();

        return $this->getJsonResponse(true, 'Cập nhật thành công', null);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $user = $request->user();

        if ($user->role < 2)
            return $this->getJsonResponse(false, 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!', null);

        $group = Group::find($id);
        if ($group == null)
            return $this->getJsonResponse(false, 'Group không tồn tại!', null);

        if ($group->profiles->count() > 0)
            return $this->getJsonResponse(false, 'Không thể xóa Group có liên kết với Profiles!', null);

        // Delete group folder and all files in it
        $this->deleteGroupFolder($id);

        $group->delete();

        return $this->getJsonResponse(true, 'Xóa thành công', null);
    }

    /**
     * Delete group folder and all its contents
     *
     * @param  int  $groupId
     * @return void
     */
    private function deleteGroupFolder($groupId)
    {
        try {
            $groupFolder = 'profiles/' . $groupId;
            if (Storage::disk('public')->exists($groupFolder)) {
                Storage::disk('public')->deleteDirectory($groupFolder);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the delete operation
        }
    }

    /**
     * Get total profile
     *
     * @return \Illuminate\Http\Response
     */
    public function getTotal()
    {
        $total = Group::count();
        return $this->getJsonResponse(true, 'OK', ['total' => $total]);
    }

    /**
     * Get list of users role
     */
    public function getGroupRoles($id)
    {
        $groupRoles = GroupRole::where('group_id', $id)
                            ->with(['group', 'user'])->get();
        return $this->getJsonResponse(true, 'OK', $groupRoles);
    }

    public function share($id, Request $request)
    {
        // Validate input
        $user = $request->user();

        $sharedUser = User::find($request->user_id);
        if ($sharedUser == null)
            return $this->getJsonResponse(false, 'User ID không tồn tại', null);

        if ($sharedUser->role == 2)
            return $this->getJsonResponse(false, 'Không cần set quyền cho Admin', null);

        $group = Group::find($id);
        if ($group == null)
            return $this->getJsonResponse(false, 'Profile không tồn tại', null);

        if ($user->role != 2 && $group->created_by != $user->id)
            return $this->getJsonResponse(false, 'Bạn phải là người tạo group', null);

        // Handing data
        $groupRole = GroupRole::where('group_id', $id)->where('user_id', $request->user_id)->first();

        // If role = 0, remove in GroupRole
        if ($request->role == 0){
            if ($groupRole != null)
                $groupRole->delete();

            return $this->getJsonResponse(true, 'OK', null);
        }

        if ($groupRole == null)
            $groupRole = new GroupRole();

        // Share
        $groupRole->group_id = $id;
        $groupRole->user_id = $request->user_id;
        $groupRole->role = $request->role;
        $groupRole->save();

        return $this->getJsonResponse(true, 'OK', null);
    }

    /**
     * Toggle auto backup for a group
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function toggleAutoBackup($id, Request $request)
    {
        $user = $request->user();

        if ($user->role < 2)
            return $this->getJsonResponse(false, 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!', null);

        $group = Group::find($id);
        if ($group == null)
            return $this->getJsonResponse(false, 'Group không tồn tại', null);

        $group->auto_backup = $request->auto_backup;
        $group->save();

        return $this->getJsonResponse(true, 'Cập nhật auto backup thành công', [
            'auto_backup' => $group->auto_backup
        ]);
    }

    /**
     * Manually backup all files in a group to Google Drive
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function manualBackup($id, Request $request)
    {
        $user = $request->user();

        if ($user->role < 2)
            return $this->getJsonResponse(false, 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!', null);

        $group = Group::find($id);
        if ($group == null)
            return $this->getJsonResponse(false, 'Group không tồn tại', null);

        try {
            $googleDriveService = app(\App\Services\GoogleDriveService::class);

            if (!$googleDriveService->isConfigured()) {
                return $this->getJsonResponse(false, 'Google Drive chưa được cấu hình', null);
            }

            // Get or create Google Drive folder for this group
            $folderId = $group->google_drive_folder_id;
            
            if (!$folderId) {
                $folderId = $googleDriveService->getOrCreateGroupFolder($group->id, $group->name);
                
                if ($folderId) {
                    $group->google_drive_folder_id = $folderId;
                    $group->save();
                } else {
                    return $this->getJsonResponse(false, 'Không thể tạo folder trên Google Drive', null);
                }
            }

            // Backup all files in the group folder
            $groupFolder = 'profiles/' . $group->id;
            
            if (!Storage::disk('public')->exists($groupFolder)) {
                return $this->getJsonResponse(false, 'Không tìm thấy folder của group', null);
            }
            
            $files = Storage::disk('public')->files($groupFolder);
            $successCount = 0;
            $failCount = 0;
            
            foreach ($files as $file) {
                $fileName = basename($file);
                $localPath = storage_path('app/public/' . $file);
                
                $result = $googleDriveService->backupFile($localPath, $fileName, $folderId);
                
                // Handle new return format (array with status and file_id)
                if (is_array($result)) {
                    $status = $result['status'];
                    $fileId = $result['file_id'] ?? null;
                    
                    if ($status === 'skipped' || $status === 'uploaded' || $status === 'updated') {
                        $successCount++;
                        
                        // Save file record if file_id exists
                        if ($fileId) {
                            $this->saveFileRecord($group->id, $fileName, $fileId, $file);
                        }
                    } else {
                        $failCount++;
                    }
                } elseif ($result === 'skipped') {
                    // Legacy format support
                    $successCount++;
                } elseif ($result) {
                    // Legacy format support
                    $successCount++;
                } else {
                    $failCount++;
                }
            }

            return $this->getJsonResponse(true, 'Backup hoàn tất', [
                'success' => $successCount,
                'failed' => $failCount,
                'total' => count($files)
            ]);

        } catch (\Exception $e) {
            return $this->getJsonResponse(false, 'Lỗi khi backup: ' . $e->getMessage(), null);
        }
    }

    /**
     * Save or update file record in database
     *
     * @param int $groupId
     * @param string $fileName
     * @param string $googleDriveFileId
     * @param string $filePath
     * @return void
     */
    protected function saveFileRecord($groupId, $fileName, $googleDriveFileId, $filePath)
    {
        try {
            $localPath = storage_path('app/public/' . $filePath);
            $fileSize = file_exists($localPath) ? filesize($localPath) : null;
            $md5Checksum = file_exists($localPath) ? md5_file($localPath) : null;
            
            ProfileFile::updateOrCreate(
                [
                    'group_id' => $groupId,
                    'file_name' => $fileName,
                ],
                [
                    'google_drive_file_id' => $googleDriveFileId,
                    'file_path' => $filePath,
                    'file_size' => $fileSize,
                    'md5_checksum' => $md5Checksum,
                ]
            );
            
            Log::debug("Saved file record: {$fileName} (Google Drive ID: {$googleDriveFileId})");
        } catch (\Exception $e) {
            Log::error("Failed to save file record for {$fileName}: " . $e->getMessage());
        }
    }
}
