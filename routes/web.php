<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UpdateController;

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
Route::post('/admin/groups/toggle-auto-backup/{id}', [AdminController::class, 'toggleGroupAutoBackup']);
Route::post('/admin/groups/manual-backup/{id}', [AdminController::class, 'manualGroupBackup']);

Route::middleware(['auth:sanctum'])->get('/phpinfo', function(){
    phpinfo();
});

Route::middleware(['auth:sanctum'])->group(function(){
    Route::get('/auto-update', [UpdateController::class, 'updateFromRemoteZip']);
});

// Route to serve profile files from group folders with backward compatibility
Route::get('/storage/profiles/{filename}', function($filename) {
    // Try to find file in group folders
    $publicPath = storage_path('app/public/profiles');
    
    // Get all group folders
    $groupFolders = glob($publicPath . '/*', GLOB_ONLYDIR);
    
    foreach ($groupFolders as $folder) {
        $filePath = $folder . '/' . $filename;
        if (file_exists($filePath)) {
            return response()->file($filePath);
        }
    }
    
    // Fallback: try old location (root profiles folder) for backward compatibility
    $oldPath = $publicPath . '/' . $filename;
    if (file_exists($oldPath)) {
        return response()->file($oldPath);
    }
    
    abort(404);
})->where('filename', '.*');