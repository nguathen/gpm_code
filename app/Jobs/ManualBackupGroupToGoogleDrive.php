<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Group;
use App\Models\BackupLog;
use App\Models\ProfileFile;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ManualBackupGroupToGoogleDrive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $groupId;
    public $backupLogId;

    public $tries = 3;
    public $timeout = 600; // 10 minutes

    public function __construct($groupId, $backupLogId = null)
    {
        $this->groupId = $groupId;
        $this->backupLogId = $backupLogId;
    }

    public function handle()
    {
        $backupLog = null;
        
        try {
            $group = Group::find($this->groupId);
            
            if (!$group) {
                Log::warning("Group {$this->groupId} not found for manual backup");
                return;
            }

            // Create or get backup log
            if ($this->backupLogId) {
                $backupLog = BackupLog::find($this->backupLogId);
            }
            
            if (!$backupLog) {
                $backupLog = BackupLog::create([
                    'type' => 'manual',
                    'group_id' => $group->id,
                    'operation' => 'backup_to_drive',
                    'status' => 'queued'
                ]);
            }
            
            $backupLog->markRunning();
            Log::info("Starting manual backup for group {$group->id} ({$group->name})");

            // Check Google Drive configuration
            $credentialsPath = storage_path('app/google-drive-credentials.json');
            $tokenPath = storage_path('app/google-drive-token.json');
            
            if (!file_exists($credentialsPath) || !file_exists($tokenPath)) {
                $backupLog->markFailed('Google Drive not configured');
                return;
            }

            $googleDriveService = app(GoogleDriveService::class);

            // Get or create Google Drive folder
            $folderId = $group->google_drive_folder_id;
            
            if (!$folderId) {
                $folderId = $googleDriveService->getOrCreateGroupFolder($group->id, $group->name);
                
                if ($folderId) {
                    $group->google_drive_folder_id = $folderId;
                    $group->save();
                } else {
                    $backupLog->markFailed("Failed to create Google Drive folder");
                    return;
                }
            }

            // Get all files to backup
            $groupFolder = 'profiles/' . $group->id;
            
            if (!Storage::disk('public')->exists($groupFolder)) {
                $backupLog->markFailed("Group folder not found: {$groupFolder}");
                return;
            }
            
            $files = Storage::disk('public')->files($groupFolder);
            $totalFiles = count($files);
            
            // Calculate total size
            $totalSize = 0;
            foreach ($files as $file) {
                $totalSize += Storage::disk('public')->size($file);
            }
            
            // Update backup log with totals
            $backupLog->update([
                'total_files' => $totalFiles,
                'total_size' => $totalSize
            ]);
            
            Log::info("Found {$totalFiles} files to backup for group {$group->id}");
            
            $successCount = 0;
            $skippedCount = 0;
            $failCount = 0;
            $failedFiles = [];
            
            foreach ($files as $index => $file) {
                $fileName = basename($file);
                $localPath = storage_path('app/public/' . $file);
                
                $result = $googleDriveService->backupFile($localPath, $fileName, $folderId);
                
                // Handle new return format (array with status and file_id)
                if (is_array($result)) {
                    $status = $result['status'];
                    $fileId = $result['file_id'] ?? null;
                    
                    if ($status === 'skipped') {
                        $skippedCount++;
                        
                        // Save file record if file_id exists
                        if ($fileId) {
                            $this->saveFileRecord($group->id, $fileName, $fileId, $file);
                        }
                    } elseif ($status === 'uploaded' || $status === 'updated') {
                        $successCount++;
                        
                        // Save file record
                        if ($fileId) {
                            $this->saveFileRecord($group->id, $fileName, $fileId, $file);
                        }
                    } else {
                        $failCount++;
                        $failedFiles[] = $fileName;
                    }
                } elseif ($result === 'skipped') {
                    // Legacy format support
                    $skippedCount++;
                } elseif ($result) {
                    // Legacy format support
                    $successCount++;
                } else {
                    $failCount++;
                    $failedFiles[] = $fileName;
                }
                
                // Update progress every 10 files or at the end
                if (($index + 1) % 10 == 0 || $index == $totalFiles - 1) {
                    $backupLog->updateProgress(
                        $successCount + $skippedCount + $failCount,
                        $successCount,
                        $skippedCount,
                        $failCount
                    );
                }
            }

            $backupLog->markCompleted($successCount, $skippedCount, $failCount, $failedFiles);
            Log::info("Manual backup completed for group {$group->id}: {$successCount} uploaded, {$skippedCount} skipped, {$failCount} failed");

        } catch (\Exception $e) {
            Log::error("Manual backup job failed for group {$this->groupId}: " . $e->getMessage());
            if ($backupLog) {
                $backupLog->markFailed($e->getMessage());
            }
            throw $e;
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

    public function failed(\Throwable $exception)
    {
        Log::error("Manual backup job permanently failed for group {$this->groupId}: " . $exception->getMessage());
        
        if ($this->backupLogId) {
            $backupLog = BackupLog::find($this->backupLogId);
            if ($backupLog) {
                $backupLog->markFailed($exception->getMessage());
            }
        }
    }
}
