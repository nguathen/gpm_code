<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Profile;
use App\Models\Setting;
use App\Models\Group;
use App\Models\BackupLog;
use Illuminate\Support\Facades\Artisan;
use stdClass;

// Simple, so not use middleware
class AdminController extends Controller
{
    public function index(){
        $loginUser = Auth::user();
        if ($loginUser == null || $loginUser->role != 2)
            return redirect('/admin/auth');

        $users = User::where('id', '<>', $loginUser->id)->orderBy('role', 'desc')->get();

        $storageType = 's3';
        $setting = Setting::where('name', 'storage_type')->first();
        
        // Tạo setting nếu chưa có dựa trên thông tin trong file .env
        if($setting == null) {
            $setting = new Setting();
            $setting->name = 'storage_type';

            $apiKey = env('S3_KEY');
            $apiSecret = env('S3_PASSWORD');
            $apiBucket = env('S3_BUCKET');
            $apiRegion = env('S3_REGION');
            
            if($apiKey != null && $apiSecret != null && $apiBucket != null && $apiRegion != null) {
                $setting->value = 's3';
            } else {
                $setting->value = 'hosting';
            }
            $setting->save();
        }

        $storageType = $setting->value;

        $s3Config = new stdClass();
        $s3Config->S3_KEY = env('S3_KEY');
        $s3Config->S3_PASSWORD = env('S3_PASSWORD');
        $s3Config->S3_BUCKET = env('S3_BUCKET');
        $s3Config->S3_REGION = env('S3_REGION');

        $cache_extension_setting = Setting::where('name', 'cache_extension')->first()->value ?? "off";
        
        // Get all groups for backup management
        $groups = Group::where('id', '!=', 0)->orderBy('sort')->get();
        
        // Check Google Drive configuration status
        $googleDriveConfigured = file_exists(storage_path('app/google-drive-credentials.json')) && 
                                 file_exists(storage_path('app/google-drive-token.json'));
        
        $googleDriveRootFolderId = env('GOOGLE_DRIVE_ROOT_FOLDER_ID', '');
        
        // Get Google Drive account info if configured
        $googleDriveAccount = null;
        if ($googleDriveConfigured) {
            try {
                $client = new \Google\Client();
                $client->setAuthConfig(storage_path('app/google-drive-credentials.json'));
                $client->addScope(\Google\Service\Drive::DRIVE_FILE);
                $client->setAccessType('offline');
                
                $tokenPath = storage_path('app/google-drive-token.json');
                $accessToken = json_decode(file_get_contents($tokenPath), true);
                $client->setAccessToken($accessToken);
                
                // Refresh token if expired
                if ($client->isAccessTokenExpired()) {
                    if ($client->getRefreshToken()) {
                        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
                    }
                }
                
                $service = new \Google\Service\Drive($client);
                $about = $service->about->get(['fields' => 'user']);
                
                $googleDriveAccount = [
                    'name' => $about->getUser()->getDisplayName(),
                    'email' => $about->getUser()->getEmailAddress()
                ];
            } catch (\Exception $e) {
                // If error, mark as not configured
                $googleDriveConfigured = false;
            }
        }
        
        return view('index', compact('users', 'storageType', 's3Config', 'cache_extension_setting', 'groups', 'googleDriveConfigured', 'googleDriveRootFolderId', 'googleDriveAccount'));
    }

    public function toogleActiveUser($id) {
        $user = User::find($id);
        if ($user == null)
            return;

        if ($user->active == 0) $user->active = 1;
        else if ($user->active == 1) $user->active = 0;

        $user->save();
        return redirect()->back();
    }

    public function saveSetting(Request $request){
        $setting = Setting::where('name', 'storage_type')->first();
        if ($setting == null)
            $setting = new Setting();

        $setting->name = 'storage_type';
        $setting->value = $request->type;
        $setting->save();

        if ($setting->value == 'hosting'){
            Artisan::call('storage:link');
        } else if ($setting->value == 's3'){
            $this->setEnvironmentValue('S3_KEY', $request->S3_KEY);
            $this->setEnvironmentValue('S3_PASSWORD', $request->S3_PASSWORD);
            $this->setEnvironmentValue('S3_BUCKET', $request->S3_BUCKET);
            $this->setEnvironmentValue('S3_REGION', $request->S3_REGION);
        }

        $cache_extension_setting = Setting::where('name', 'cache_extension')->first();
        if ($cache_extension_setting == null)
            $cache_extension_setting = new Setting();
        $cache_extension_setting->name = 'cache_extension';
        $cache_extension_setting->value = $request->cache_extension ?? "off";
        $cache_extension_setting->save();

        return redirect()->back()->with('msg', 'Storge type is changed to: '.$setting->value);
    }

    public function resetProfileStatus(){
        Profile::query()->update(['status' => 1]);
        // Forearch exception: Allowed memory size of ... bytes exhausted (tried to allocate ... bytes) 
        // $profiles = Profile::get();
        // foreach ($profiles as $profile){
        //     $profile->status = 1;
        //     $profile->save();
        // }
        return redirect()->back()->with('msg', 'Reset profile status successfully');
    }

    public function runMigrations() {
        try {
            // Chạy lệnh migrate qua Artisan
            // Artisan::call('migrate');
            UpdateController::migrationDatabase();
            
            // Trả về thông báo thành công
            return redirect()->back()->with('msg', 'Migration successfully');
        } catch (\Exception $e) {
            // Trả về lỗi nếu quá trình migrate thất bại
            return redirect()->back()->with('msg', 'Migration failed: '.$e->getMessage());
        }
    }

    public function uploadGoogleDriveCredentials(Request $request) {
        try {
            if (!$request->hasFile('credentials')) {
                return redirect()->back()->with('msg', 'Vui lòng chọn file credentials!');
            }

            $file = $request->file('credentials');
            
            // Validate JSON file
            $content = file_get_contents($file->getRealPath());
            $json = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return redirect()->back()->with('msg', 'File không đúng định dạng JSON!');
            }

            // Save credentials file
            $file->storeAs('', 'google-drive-credentials.json');
            
            return redirect()->back()->with('msg', 'Upload credentials thành công! Bây giờ hãy authenticate với Google Drive.');
        } catch (\Exception $e) {
            return redirect()->back()->with('msg', 'Lỗi: ' . $e->getMessage());
        }
    }

    public function getGoogleDriveAuthUrl() {
        try {
            $credentialsPath = storage_path('app/google-drive-credentials.json');
            
            if (!file_exists($credentialsPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chưa upload credentials file!'
                ]);
            }

            $client = new \Google\Client();
            $client->setAuthConfig($credentialsPath);
            $client->addScope(\Google\Service\Drive::DRIVE_FILE);
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');

            $authUrl = $client->createAuthUrl();

            return response()->json([
                'success' => true,
                'auth_url' => $authUrl
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function saveGoogleDriveToken(Request $request) {
        try {
            $authCode = $request->auth_code;
            
            if (empty($authCode)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng nhập authorization code!'
                ]);
            }

            $credentialsPath = storage_path('app/google-drive-credentials.json');
            
            if (!file_exists($credentialsPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chưa upload credentials file!'
                ]);
            }

            $client = new \Google\Client();
            $client->setAuthConfig($credentialsPath);
            $client->addScope(\Google\Service\Drive::DRIVE_FILE);
            $client->setAccessType('offline');

            // Exchange authorization code for access token
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

            if (isset($accessToken['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi: ' . ($accessToken['error_description'] ?? $accessToken['error'])
                ]);
            }

            // Save token
            file_put_contents(storage_path('app/google-drive-token.json'), json_encode($accessToken));

            // Test connection
            $client->setAccessToken($accessToken);
            $service = new \Google\Service\Drive($client);
            $about = $service->about->get(['fields' => 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Kết nối thành công!',
                'user' => [
                    'name' => $about->getUser()->getDisplayName(),
                    'email' => $about->getUser()->getEmailAddress()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function saveGoogleDriveRootFolder(Request $request) {
        try {
            $folderId = $request->folder_id ?? '';
            $this->setEnvironmentValue('GOOGLE_DRIVE_ROOT_FOLDER_ID', $folderId);
            
            return redirect()->back()->with('msg', 'Đã lưu Root Folder ID!');
        } catch (\Exception $e) {
            return redirect()->back()->with('msg', 'Lỗi: ' . $e->getMessage());
        }
    }

    public function resetGoogleDrive() {
        try {
            $credentialsPath = storage_path('app/google-drive-credentials.json');
            $tokenPath = storage_path('app/google-drive-token.json');
            
            if (file_exists($credentialsPath)) {
                unlink($credentialsPath);
            }
            
            if (file_exists($tokenPath)) {
                unlink($tokenPath);
            }
            
            return redirect()->back()->with('msg', 'Đã xóa cấu hình Google Drive!');
        } catch (\Exception $e) {
            return redirect()->back()->with('msg', 'Lỗi: ' . $e->getMessage());
        }
    }

    public function exportGoogleDriveAuth() {
        try {
            $credentialsPath = storage_path('app/google-drive-credentials.json');
            $tokenPath = storage_path('app/google-drive-token.json');
            
            if (!file_exists($credentialsPath) || !file_exists($tokenPath)) {
                return redirect()->back()->with('msg', 'Google Drive chưa được cấu hình đầy đủ!');
            }
            
            // Read token and validate refresh_token exists
            $token = json_decode(file_get_contents($tokenPath), true);
            
            if (!isset($token['refresh_token']) || empty($token['refresh_token'])) {
                return redirect()->back()->with('msg', '❌ Token không có refresh_token! Vui lòng authenticate lại với prompt "select_account consent" để có refresh_token mới.');
            }
            
            // Verify token is still valid by refreshing it
            try {
                $client = new \Google\Client();
                $client->setAuthConfig($credentialsPath);
                $client->addScope(\Google\Service\Drive::DRIVE_FILE);
                $client->setAccessType('offline');
                $client->setAccessToken($token);
                
                // Force refresh to ensure token works
                if ($client->getRefreshToken()) {
                    $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    
                    if (isset($newToken['error'])) {
                        return redirect()->back()->with('msg', '❌ Token không hợp lệ: ' . ($newToken['error_description'] ?? $newToken['error']));
                    }
                    
                    // Update token file with refreshed token
                    file_put_contents($tokenPath, json_encode($client->getAccessToken()));
                    $token = $client->getAccessToken();
                }
            } catch (\Exception $e) {
                return redirect()->back()->with('msg', '❌ Không thể verify token: ' . $e->getMessage());
            }
            
            // Create export data
            $exportData = [
                'credentials' => json_decode(file_get_contents($credentialsPath), true),
                'token' => $token,
                'root_folder_id' => env('GOOGLE_DRIVE_ROOT_FOLDER_ID', ''),
                'exported_at' => now()->toDateTimeString(),
                'server' => request()->getHost(),
                'version' => '1.0'
            ];
            
            $filename = 'google-drive-auth-' . date('Y-m-d-His') . '.json';
            $content = json_encode($exportData, JSON_PRETTY_PRINT);
            
            return response($content)
                ->header('Content-Type', 'application/json')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
                
        } catch (\Exception $e) {
            return redirect()->back()->with('msg', 'Lỗi khi export: ' . $e->getMessage());
        }
    }

    public function importGoogleDriveAuth(Request $request) {
        try {
            if (!$request->hasFile('auth_file')) {
                return redirect()->back()->with('msg', 'Vui lòng chọn file auth!');
            }

            $file = $request->file('auth_file');
            
            // Validate JSON file
            $content = file_get_contents($file->getRealPath());
            $authData = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return redirect()->back()->with('msg', 'File không đúng định dạng JSON!');
            }
            
            // Validate required fields
            if (!isset($authData['credentials']) || !isset($authData['token'])) {
                return redirect()->back()->with('msg', 'File auth không hợp lệ! Thiếu credentials hoặc token.');
            }
            
            // Validate refresh_token exists
            if (!isset($authData['token']['refresh_token']) || empty($authData['token']['refresh_token'])) {
                return redirect()->back()->with('msg', '❌ File auth không có refresh_token! Token này không thể sử dụng lâu dài. Vui lòng export lại từ server gốc.');
            }
            
            // Save credentials
            $credentialsPath = storage_path('app/google-drive-credentials.json');
            file_put_contents($credentialsPath, json_encode($authData['credentials'], JSON_PRETTY_PRINT));
            
            // Save token
            $tokenPath = storage_path('app/google-drive-token.json');
            file_put_contents($tokenPath, json_encode($authData['token'], JSON_PRETTY_PRINT));
            
            // Save root folder ID if exists
            if (isset($authData['root_folder_id']) && !empty($authData['root_folder_id'])) {
                $this->setEnvironmentValue('GOOGLE_DRIVE_ROOT_FOLDER_ID', $authData['root_folder_id']);
            }
            
            // Test connection and auto-refresh token
            try {
                $client = new \Google\Client();
                $client->setAuthConfig($credentialsPath);
                $client->addScope(\Google\Service\Drive::DRIVE_FILE);
                $client->setAccessType('offline');
                
                $accessToken = json_decode(file_get_contents($tokenPath), true);
                $client->setAccessToken($accessToken);
                
                // Check if token expired and refresh
                if ($client->isAccessTokenExpired()) {
                    if ($client->getRefreshToken()) {
                        // Refresh token
                        $newAccessToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                        
                        // Check for errors in refresh
                        if (isset($newAccessToken['error'])) {
                            throw new \Exception('Không thể refresh token: ' . ($newAccessToken['error_description'] ?? $newAccessToken['error']));
                        }
                        
                        // Save new token
                        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
                        Log::info('Google Drive token refreshed successfully after import');
                    } else {
                        throw new \Exception('Không có refresh token. Token có thể đã bị revoke.');
                    }
                }
                
                // Test connection
                $service = new \Google\Service\Drive($client);
                $about = $service->about->get(['fields' => 'user,storageQuota']);
                
                $userName = $about->getUser()->getDisplayName();
                $userEmail = $about->getUser()->getEmailAddress();
                
                // Get storage info
                $storageQuota = $about->getStorageQuota();
                $usedGB = round($storageQuota->getUsage() / 1024 / 1024 / 1024, 2);
                $limitGB = round($storageQuota->getLimit() / 1024 / 1024 / 1024, 2);
                
                return redirect()->back()->with('msg', "✅ Import thành công! Kết nối với: {$userName} ({$userEmail}). Storage: {$usedGB}/{$limitGB} GB");
                
            } catch (\Google\Service\Exception $e) {
                // Google API error
                $errorMsg = $e->getMessage();
                if (strpos($errorMsg, 'invalid_grant') !== false) {
                    return redirect()->back()->with('msg', '❌ Token đã bị revoke hoặc hết hạn. Vui lòng authenticate lại từ đầu.');
                }
                return redirect()->back()->with('msg', '❌ Lỗi Google API: ' . $errorMsg);
                
            } catch (\Exception $e) {
                return redirect()->back()->with('msg', '❌ Lỗi khi test connection: ' . $e->getMessage());
            }
            
        } catch (\Exception $e) {
            return redirect()->back()->with('msg', 'Lỗi khi import: ' . $e->getMessage());
        }
    }

    public function toggleGroupAutoBackup($id, Request $request) {
        $loginUser = Auth::user();
        if ($loginUser == null || $loginUser->role != 2) {
            return response()->json([
                'success' => false,
                'message' => 'Không đủ quyền. Bạn cần có quyền admin!'
            ]);
        }

        $group = Group::find($id);
        if ($group == null) {
            return response()->json([
                'success' => false,
                'message' => 'Group không tồn tại'
            ]);
        }

        $group->auto_backup = $request->auto_backup;
        $group->save();

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật auto backup thành công',
            'data' => [
                'auto_backup' => $group->auto_backup
            ]
        ]);
    }

    public function manualGroupBackup($id, Request $request) {
        $loginUser = Auth::user();
        if ($loginUser == null || $loginUser->role != 2) {
            return response()->json([
                'success' => false,
                'message' => 'Không đủ quyền. Bạn cần có quyền admin!'
            ]);
        }

        $group = Group::find($id);
        if ($group == null) {
            return response()->json([
                'success' => false,
                'message' => 'Group không tồn tại'
            ]);
        }

        try {
            $googleDriveService = app(\App\Services\GoogleDriveService::class);

            if (!$googleDriveService->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google Drive chưa được cấu hình'
                ]);
            }

            // Count files to backup
            $groupFolder = 'profiles/' . $group->id;
            
            if (!Storage::disk('public')->exists($groupFolder)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy folder của group'
                ]);
            }
            
            $files = Storage::disk('public')->files($groupFolder);
            $totalFiles = count($files);
            
            if ($totalFiles == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có file nào để backup'
                ]);
            }

            // Create backup log entry
            $backupLog = BackupLog::create([
                'type' => 'manual',
                'group_id' => $group->id,
                'operation' => 'backup_to_drive',
                'status' => 'queued',
                'total_files' => $totalFiles
            ]);

            // Dispatch backup job to queue (async) with backup log ID
            \App\Jobs\ManualBackupGroupToGoogleDrive::dispatch($group->id, $backupLog->id)
                ->onQueue('backups');

            return response()->json([
                'success' => true,
                'message' => "Đã bắt đầu backup {$totalFiles} files. Quá trình sẽ chạy background.",
                'data' => [
                    'total' => $totalFiles,
                    'status' => 'queued',
                    'backup_log_id' => $backupLog->id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi khởi tạo backup: ' . $e->getMessage()
            ]);
        }
    }

    public function syncGroupFromDrive($id, Request $request) {
        $loginUser = Auth::user();
        if ($loginUser == null || $loginUser->role != 2) {
            return response()->json([
                'success' => false,
                'message' => 'Không đủ quyền. Bạn cần có quyền admin!'
            ]);
        }

        $group = Group::find($id);
        if ($group == null) {
            return response()->json([
                'success' => false,
                'message' => 'Group không tồn tại'
            ]);
        }

        try {
            $googleDriveService = app(\App\Services\GoogleDriveService::class);

            if (!$googleDriveService->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google Drive chưa được cấu hình'
                ]);
            }

            if (!$group->google_drive_folder_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group chưa có folder trên Google Drive. Hãy backup trước.'
                ]);
            }

            // Get file count from Google Drive
            $driveFiles = $googleDriveService->listFilesInFolder($group->google_drive_folder_id);
            $totalFiles = count($driveFiles);

            if ($totalFiles == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có file nào trên Google Drive để sync'
                ]);
            }

            // Create backup log entry
            $backupLog = BackupLog::create([
                'type' => 'manual',
                'group_id' => $group->id,
                'operation' => 'sync_from_drive',
                'status' => 'queued',
                'total_files' => $totalFiles
            ]);

            // Dispatch sync job to queue (async) with backup log ID
            \App\Jobs\SyncGroupFromGoogleDrive::dispatch($group->id, $backupLog->id)
                ->onQueue('backups');

            return response()->json([
                'success' => true,
                'message' => "Đã bắt đầu sync {$totalFiles} files từ Google Drive. Quá trình sẽ chạy background.",
                'data' => [
                    'total' => $totalFiles,
                    'status' => 'queued',
                    'backup_log_id' => $backupLog->id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi khởi tạo sync: ' . $e->getMessage()
            ]);
        }
    }

    public function getBackupLogs(Request $request) {
        $query = BackupLog::with('group');

        // Filter by group
        if ($request->has('group_id') && $request->group_id != '') {
            $query->where('group_id', $request->group_id);
        }

        // Filter by status
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        // Filter by operation
        if ($request->has('operation') && $request->operation != '') {
            $query->where('operation', $request->operation);
        }

        // Order by latest
        $logs = $query->orderBy('created_at', 'desc')
                     ->limit(100)
                     ->get();

        return response()->json([
            'success' => true,
            'data' => $logs->map(function($log) {
                return [
                    'id' => $log->id,
                    'type' => $log->type,
                    'group_id' => $log->group_id,
                    'group_name' => $log->group ? $log->group->name : 'N/A',
                    'operation' => $log->operation,
                    'status' => $log->status,
                    'progress' => $log->getProgressPercentage(),
                    'total_files' => $log->total_files,
                    'processed_files' => $log->processed_files,
                    'success_count' => $log->success_count,
                    'skipped_count' => $log->skipped_count,
                    'failed_count' => $log->failed_count,
                    'total_size' => $log->total_size,
                    'formatted_size' => $log->formatted_size,
                    'duration' => $log->duration,
                    'formatted_duration' => $log->formatted_duration,
                    'error_message' => $log->error_message,
                    'failed_files' => $log->failed_files,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'started_at' => $log->started_at ? $log->started_at->format('Y-m-d H:i:s') : null,
                    'completed_at' => $log->completed_at ? $log->completed_at->format('Y-m-d H:i:s') : null
                ];
            })
        ]);
    }

    public function getBackupLog($id) {
        $log = BackupLog::with('group')->find($id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Backup log không tồn tại'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $log->id,
                'type' => $log->type,
                'group_id' => $log->group_id,
                'group_name' => $log->group ? $log->group->name : 'N/A',
                'operation' => $log->operation,
                'status' => $log->status,
                'progress' => $log->getProgressPercentage(),
                'total_files' => $log->total_files,
                'processed_files' => $log->processed_files,
                'success_count' => $log->success_count,
                'skipped_count' => $log->skipped_count,
                'failed_count' => $log->failed_count,
                'total_size' => $log->total_size,
                'formatted_size' => $log->formatted_size,
                'duration' => $log->duration,
                'formatted_duration' => $log->formatted_duration,
                'error_message' => $log->error_message,
                'failed_files' => $log->failed_files,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                'started_at' => $log->started_at ? $log->started_at->format('Y-m-d H:i:s') : null,
                'completed_at' => $log->completed_at ? $log->completed_at->format('Y-m-d H:i:s') : null
            ]
        ]);
    }

    // Write .env
    private function setEnvironmentValue($envKey, $envValue) {
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);

        $oldValue = env($envKey);
        
        // Check if key exists
        if (strpos($str, $envKey) !== false) {
            $str = str_replace("{$envKey}={$oldValue}", "{$envKey}={$envValue}", $str);
        } else {
            // Add new key at the end
            $str .= "\n{$envKey}={$envValue}";
        }
        
        $fp = fopen($envFile, 'w');
        fwrite($fp, $str);
        fclose($fp);
    }
}
