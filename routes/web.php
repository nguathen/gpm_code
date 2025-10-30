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
    
    // Get all group folders
    $groupFolders = glob($publicPath . '/*', GLOB_ONLYDIR);
    
    foreach ($groupFolders as $folder) {
        $filePath = $folder . '/' . $filename;
        if (file_exists($filePath)) {
            if ($request->isMethod('HEAD')) {
                // HEAD request: return headers only
                return response('', 200)
                    ->header('Content-Type', mime_content_type($filePath))
                    ->header('Content-Length', filesize($filePath));
            }
            return response()->file($filePath);
        }
    }
    
    // Fallback: try old location (root profiles folder) for backward compatibility
    $oldPath = $publicPath . '/' . $filename;
    if (file_exists($oldPath)) {
        if ($request->isMethod('HEAD')) {
            return response('', 200)
                ->header('Content-Type', mime_content_type($oldPath))
                ->header('Content-Length', filesize($oldPath));
        }
        return response()->file($oldPath);
    }
    
    // File not found locally, proxy from Google Drive
    try {
        // Find file record in database
        $profileFile = ProfileFile::where('file_name', $filename)->first();
        
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