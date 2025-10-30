<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UpdateController;
use App\Models\ProfileFile;
use App\Services\GoogleDriveService;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [HomeController::class, 'index']);
Route::get('/setup', [HomeController::class, 'setup']);
Route::post('/setup', [HomeController::class, 'createDb']);
Route::get('/test', [HomeController::class, 'test']);
Route::get('/test', function(){
    return 'test';
});

Route::get('/admin/auth', function(){
    return view('login');
})->name('login');
Route::get('/admin/auth/logout', [AuthController::class, 'logout']);
Route::post('/admin/auth', [AuthController::class, 'login']);


Route::get('/admin', [AdminController::class, 'index']);
Route::get('/admin/active-user/{id}', [AdminController::class, 'toogleActiveUser']);
Route::get('/admin/reset-profile-status', [AdminController::class, 'resetProfileStatus']);
Route::get('/admin/save-setting', [AdminController::class, 'saveSetting']);
Route::get('/admin/migration', [AdminController::class, 'runMigrations']);
Route::post('/admin/google-drive/upload-credentials', [AdminController::class, 'uploadGoogleDriveCredentials']);
Route::get('/admin/google-drive/auth-url', [AdminController::class, 'getGoogleDriveAuthUrl']);
Route::post('/admin/google-drive/save-token', [AdminController::class, 'saveGoogleDriveToken']);
Route::post('/admin/google-drive/save-root-folder', [AdminController::class, 'saveGoogleDriveRootFolder']);
Route::get('/admin/google-drive/reset', [AdminController::class, 'resetGoogleDrive']);
Route::get('/admin/google-drive/export-auth', [AdminController::class, 'exportGoogleDriveAuth']);
Route::post('/admin/google-drive/import-auth', [AdminController::class, 'importGoogleDriveAuth']);
Route::post('/admin/groups/toggle-auto-backup/{id}', [AdminController::class, 'toggleGroupAutoBackup']);
Route::post('/admin/groups/manual-backup/{id}', [AdminController::class, 'manualGroupBackup']);
Route::post('/admin/groups/sync-from-drive/{id}', [AdminController::class, 'syncGroupFromDrive']);
Route::get('/admin/backup-logs', [AdminController::class, 'getBackupLogs']);
Route::get('/admin/backup-logs/{id}', [AdminController::class, 'getBackupLog']);

Route::middleware(['auth:sanctum'])->get('/phpinfo', function(){
    phpinfo();
});

// Health check endpoint (no auth required for monitoring)
Route::get('/health/google-drive', [App\Http\Controllers\HealthCheckController::class, 'googleDrive']);

Route::middleware(['auth:sanctum'])->group(function(){
    Route::get('/auto-update', [UpdateController::class, 'updateFromRemoteZip']);
});

// Route to serve profile files from group folders with backward compatibility
Route::match(['GET', 'HEAD'], '/storage/profiles/{filename}', function($filename, Request $request) {
    // Try to find file in group folders
    $publicPath = storage_path('app/public/profiles');
    $localFilePath = null;
    $groupId = null;
    
    // Get all group folders
    $groupFolders = glob($publicPath . '/*', GLOB_ONLYDIR);
    
    foreach ($groupFolders as $folder) {
        $filePath = $folder . '/' . $filename;
        if (file_exists($filePath)) {
            $localFilePath = $filePath;
            // Extract group_id from folder path (e.g., /path/profiles/12/file.json -> group_id = 12)
            $folderName = basename($folder);
            if (is_numeric($folderName)) {
                $groupId = (int)$folderName;
            }
            break;
        }
    }
    
    // Fallback: try old location (root profiles folder) for backward compatibility
    if (!$localFilePath) {
        $oldPath = $publicPath . '/' . $filename;
        if (file_exists($oldPath)) {
            $localFilePath = $oldPath;
        }
    }
    
    // If file exists locally, check if it's outdated
    if ($localFilePath) {
        try {
            // Find file record in database to check MD5
            // Use group_id if available for more accurate query
            $query = ProfileFile::where('file_name', $filename);
            if ($groupId) {
                $query->where('group_id', $groupId);
            }
            $profileFile = $query->first();
            
            if ($profileFile && $profileFile->md5_checksum) {
                // Optimize: Check file modification time and size first before calculating MD5
                // Only calculate MD5 if file might have changed
                $localFileSize = filesize($localFilePath);
                $localFileTime = filemtime($localFilePath);
                
                // If DB has size and it matches, and we have a recent cache, skip MD5 check
                // Otherwise, calculate MD5 to verify
                $needsMd5Check = true;
                if ($profileFile->file_size && $profileFile->file_size == $localFileSize) {
                    // Size matches, but still need to verify MD5 for security
                    // However, we can optimize by caching MD5 based on filemtime
                    $cacheKey = "file_md5_{$filename}_{$localFileTime}";
                    $cachedMd5 = cache()->get($cacheKey);
                    
                    if ($cachedMd5) {
                        $localMd5 = $cachedMd5;
                        $needsMd5Check = false;
                    } else {
                        $localMd5 = md5_file($localFilePath);
                        // Cache MD5 for 1 hour (or until file is modified)
                        cache()->put($cacheKey, $localMd5, 3600);
                    }
                } else {
                    $localMd5 = md5_file($localFilePath);
                }
                
                // Compare with database MD5
                if ($localMd5 !== $profileFile->md5_checksum) {
                    // File is outdated, serve from Google Drive instead
                    Log::info("File {$filename} is outdated locally (Local MD5: {$localMd5}, DB MD5: {$profileFile->md5_checksum}), serving from Google Drive");
                    
                    $googleDriveService = app(GoogleDriveService::class);
                    
                    if ($googleDriveService->isConfigured() && $profileFile->google_drive_file_id) {
                        try {
                            if ($request->isMethod('HEAD')) {
                                $fileInfo = $googleDriveService->getFileInfo($profileFile->google_drive_file_id);
                                if ($fileInfo) {
                                    return response('', 200)
                                        ->header('Content-Type', $fileInfo['mimeType'] ?? 'application/octet-stream')
                                        ->header('Content-Length', $fileInfo['size'] ?? 0);
                                }
                            } else {
                                return $googleDriveService->proxyDownload($profileFile->google_drive_file_id, $filename);
                            }
                        } catch (\Exception $e) {
                            Log::warning("Failed to serve from Google Drive for {$filename}, falling back to local file: " . $e->getMessage());
                            // Fallback: serve local file if Google Drive fails
                            if ($request->isMethod('HEAD')) {
                                return response('', 200)
                                    ->header('Content-Type', mime_content_type($localFilePath))
                                    ->header('Content-Length', filesize($localFilePath));
                            }
                            return response()->file($localFilePath);
                        }
                    }
                }
            }
            
            // File exists and is up-to-date (or no MD5 in DB), serve locally
            if ($request->isMethod('HEAD')) {
                return response('', 200)
                    ->header('Content-Type', mime_content_type($localFilePath))
                    ->header('Content-Length', filesize($localFilePath));
            }
            return response()->file($localFilePath);
            
        } catch (\Exception $e) {
            Log::error("Error checking file MD5 for {$filename}: " . $e->getMessage());
            // Fallback: serve local file if MD5 check fails
            if ($request->isMethod('HEAD')) {
                return response('', 200)
                    ->header('Content-Type', mime_content_type($localFilePath))
                    ->header('Content-Length', filesize($localFilePath));
            }
            return response()->file($localFilePath);
        }
    }
    
    // File not found locally, proxy from Google Drive
    try {
        // Find file record in database
        $query = ProfileFile::where('file_name', $filename);
        if ($groupId) {
            $query->where('group_id', $groupId);
        }
        $profileFile = $query->first();
        
        if ($profileFile && $profileFile->google_drive_file_id) {
            $googleDriveService = app(GoogleDriveService::class);
            
            if ($googleDriveService->isConfigured()) {
                // For HEAD request, just check if file exists and return headers
                if ($request->isMethod('HEAD')) {
                    $fileInfo = $googleDriveService->getFileInfo($profileFile->google_drive_file_id);
                    if ($fileInfo) {
                        return response('', 200)
                            ->header('Content-Type', $fileInfo['mimeType'] ?? 'application/octet-stream')
                            ->header('Content-Length', $fileInfo['size'] ?? 0);
                    }
                } else {
                    // GET request: proxy download from Google Drive
                    return $googleDriveService->proxyDownload($profileFile->google_drive_file_id, $filename);
                }
            }
        }
    } catch (\Exception $e) {
        Log::error("Error getting Google Drive file for {$filename}: " . $e->getMessage());
    }
    
    abort(404);
})->where('filename', '.*');