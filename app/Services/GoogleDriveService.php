<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GoogleDriveService
{
    protected $client;
    protected $service;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setAuthConfig(storage_path('app/google-drive-credentials.json'));
        $this->client->addScope(Drive::DRIVE_FILE);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent'); // Force to get refresh token
        
        // Load token if exists
        $tokenPath = storage_path('app/google-drive-token.json');
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $this->client->setAccessToken($accessToken);
        }

        // Auto refresh token if expired
        $this->refreshTokenIfNeeded();

        $this->service = new Drive($this->client);
    }

    /**
     * Refresh access token if expired
     * This ensures the service always has a valid token
     *
     * @return bool
     */
    protected function refreshTokenIfNeeded()
    {
        try {
            if ($this->client->isAccessTokenExpired()) {
                $refreshToken = $this->client->getRefreshToken();
                
                if (!$refreshToken) {
                    // Try to get refresh token from stored token
                    $tokenPath = storage_path('app/google-drive-token.json');
                    if (file_exists($tokenPath)) {
                        $storedToken = json_decode(file_get_contents($tokenPath), true);
                        if (isset($storedToken['refresh_token'])) {
                            $refreshToken = $storedToken['refresh_token'];
                            $this->client->setAccessToken($storedToken);
                        }
                    }
                }
                
                if ($refreshToken) {
                    Log::info('Google Drive: Refreshing expired access token');
                    $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                    
                    if (isset($newToken['error'])) {
                        Log::error('Google Drive token refresh failed: ' . $newToken['error']);
                        return false;
                    }
                    
                    // Preserve refresh token if not included in new token
                    if (!isset($newToken['refresh_token']) && $refreshToken) {
                        $newToken['refresh_token'] = $refreshToken;
                    }
                    
                    // Save new token
                    $tokenPath = storage_path('app/google-drive-token.json');
                    file_put_contents($tokenPath, json_encode($newToken));
                    
                    Log::info('Google Drive: Access token refreshed successfully');
                    return true;
                } else {
                    Log::error('Google Drive: No refresh token available. Re-authentication required.');
                    return false;
                }
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Google Drive token refresh error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a folder in Google Drive
     *
     * @param string $folderName
     * @param string|null $parentFolderId
     * @return string|null Folder ID
     */
    public function createFolder($folderName, $parentFolderId = null)
    {
        try {
            $fileMetadata = new DriveFile([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder'
            ]);

            if ($parentFolderId) {
                $fileMetadata->setParents([$parentFolderId]);
            }

            $folder = $this->service->files->create($fileMetadata, [
                'fields' => 'id'
            ]);

            return $folder->id;
        } catch (\Exception $e) {
            Log::error('Google Drive create folder error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload a file to Google Drive with auto retry on token expiration
     *
     * @param string $filePath Local file path
     * @param string $fileName File name on Google Drive
     * @param string $folderId Google Drive folder ID
     * @return string|null File ID
     */
    public function uploadFile($filePath, $fileName, $folderId)
    {
        try {
            if (!file_exists($filePath)) {
                Log::error('File not found: ' . $filePath);
                return null;
            }

            // Ensure token is fresh before upload
            $this->refreshTokenIfNeeded();

            $fileMetadata = new DriveFile([
                'name' => $fileName,
                'parents' => [$folderId]
            ]);

            $content = file_get_contents($filePath);
            $mimeType = mime_content_type($filePath);

            $file = $this->service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id'
            ]);

            return $file->id;
        } catch (\Google\Service\Exception $e) {
            // Handle token expiration errors
            if ($e->getCode() == 401) {
                Log::warning('Google Drive: Token expired during upload, refreshing...');
                if ($this->refreshTokenIfNeeded()) {
                    // Retry once after refresh
                    return $this->uploadFile($filePath, $fileName, $folderId);
                }
            }
            Log::error('Google Drive upload error: ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            Log::error('Google Drive upload error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update an existing file in Google Drive with auto retry on token expiration
     *
     * @param string $fileId Google Drive file ID
     * @param string $filePath Local file path
     * @return bool
     */
    public function updateFile($fileId, $filePath)
    {
        try {
            if (!file_exists($filePath)) {
                Log::error('File not found: ' . $filePath);
                return false;
            }

            // Ensure token is fresh before update
            $this->refreshTokenIfNeeded();

            $content = file_get_contents($filePath);
            $mimeType = mime_content_type($filePath);

            $this->service->files->update($fileId, new DriveFile(), [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart'
            ]);

            return true;
        } catch (\Google\Service\Exception $e) {
            // Handle token expiration errors
            if ($e->getCode() == 401) {
                Log::warning('Google Drive: Token expired during update, refreshing...');
                if ($this->refreshTokenIfNeeded()) {
                    // Retry once after refresh
                    return $this->updateFile($fileId, $filePath);
                }
            }
            Log::error('Google Drive update error: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            Log::error('Google Drive update error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a file from Google Drive
     *
     * @param string $fileId
     * @return bool
     */
    public function deleteFile($fileId)
    {
        try {
            $this->service->files->delete($fileId);
            return true;
        } catch (\Exception $e) {
            Log::error('Google Drive delete error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Search for a file in a folder
     *
     * @param string $fileName
     * @param string $folderId
     * @return string|null File ID
     */
    public function searchFile($fileName, $folderId)
    {
        try {
            $query = "name='{$fileName}' and '{$folderId}' in parents and trashed=false";
            
            $response = $this->service->files->listFiles([
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id, name)'
            ]);

            $files = $response->getFiles();
            
            if (count($files) > 0) {
                return $files[0]->getId();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Google Drive search error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Backup a file to Google Drive with smart skip
     * If file exists and unchanged, skip. Otherwise, update or create new.
     *
     * @param string $localPath
     * @param string $fileName
     * @param string $folderId
     * @return bool|string Returns true on success, 'skipped' if unchanged, false on error
     */
    public function backupFile($localPath, $fileName, $folderId)
    {
        try {
            if (!file_exists($localPath)) {
                Log::error("Local file not found: {$localPath}");
                return false;
            }

            // Get local file info
            $localMd5 = md5_file($localPath);
            $localSize = filesize($localPath);
            $localModTime = filemtime($localPath);

            // Search if file already exists
            $existingFileId = $this->searchFileWithMetadata($fileName, $folderId);

            if ($existingFileId) {
                // Get existing file metadata
                try {
                    $existingFile = $this->service->files->get($existingFileId['id'], [
                        'fields' => 'id,name,size,md5Checksum,modifiedTime'
                    ]);

                    $remoteMd5 = $existingFile->getMd5Checksum();
                    $remoteSize = $existingFile->getSize();

                    // Compare MD5 checksum - most reliable way
                    if ($remoteMd5 && $remoteMd5 === $localMd5) {
                        Log::debug("File unchanged, skipping: {$fileName} (MD5: {$localMd5})");
                        return 'skipped';
                    }

                    // Fallback: compare size if MD5 not available
                    if (!$remoteMd5 && $remoteSize == $localSize) {
                        Log::debug("File likely unchanged (same size), skipping: {$fileName}");
                        return 'skipped';
                    }

                    // File changed, update it
                    Log::debug("File changed, updating: {$fileName} (Local MD5: {$localMd5}, Remote MD5: {$remoteMd5})");
                    return $this->updateFile($existingFileId['id'], $localPath);

                } catch (\Exception $e) {
                    // If can't get metadata, update anyway to be safe
                    Log::warning("Cannot get file metadata, updating: {$fileName}");
                    return $this->updateFile($existingFileId['id'], $localPath);
                }
            } else {
                // File doesn't exist, upload new
                Log::debug("New file, uploading: {$fileName}");
                $fileId = $this->uploadFile($localPath, $fileName, $folderId);
                return $fileId !== null;
            }
        } catch (\Exception $e) {
            Log::error('Google Drive backup error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Search for a file in a folder with metadata
     *
     * @param string $fileName
     * @param string $folderId
     * @return array|null File info with id
     */
    protected function searchFileWithMetadata($fileName, $folderId)
    {
        try {
            $query = "name='{$fileName}' and '{$folderId}' in parents and trashed=false";
            
            $response = $this->service->files->listFiles([
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id, name, size, md5Checksum, modifiedTime)'
            ]);

            $files = $response->getFiles();
            
            if (count($files) > 0) {
                return [
                    'id' => $files[0]->getId(),
                    'name' => $files[0]->getName(),
                    'size' => $files[0]->getSize(),
                    'md5' => $files[0]->getMd5Checksum(),
                    'modifiedTime' => $files[0]->getModifiedTime()
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Google Drive search error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Download a file from Google Drive
     *
     * @param string $fileId
     * @param string $destinationPath
     * @return bool
     */
    public function downloadFile($fileId, $destinationPath)
    {
        try {
            $this->refreshTokenIfNeeded();

            $response = $this->service->files->get($fileId, [
                'alt' => 'media'
            ]);

            $content = $response->getBody()->getContents();

            // Ensure directory exists
            $directory = dirname($destinationPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($destinationPath, $content);
            
            Log::info("Downloaded file from Google Drive: {$fileId} to {$destinationPath}");
            return true;

        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() == 401) {
                Log::warning('Google Drive: Token expired during download, refreshing...');
                if ($this->refreshTokenIfNeeded()) {
                    return $this->downloadFile($fileId, $destinationPath); // Retry once
                }
            }
            Log::error('Google Drive download error: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            Log::error('Google Drive download error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * List all files in a folder
     *
     * @param string $folderId
     * @return array Array of files with id, name, size, md5Checksum
     */
    public function listFilesInFolder($folderId)
    {
        try {
            $this->refreshTokenIfNeeded();

            $query = "'{$folderId}' in parents and trashed=false and mimeType!='application/vnd.google-apps.folder'";
            
            $files = [];
            $pageToken = null;

            do {
                $response = $this->service->files->listFiles([
                    'q' => $query,
                    'spaces' => 'drive',
                    'fields' => 'nextPageToken, files(id, name, size, md5Checksum, modifiedTime)',
                    'pageToken' => $pageToken,
                    'pageSize' => 100
                ]);

                foreach ($response->getFiles() as $file) {
                    $files[] = [
                        'id' => $file->getId(),
                        'name' => $file->getName(),
                        'size' => $file->getSize(),
                        'md5' => $file->getMd5Checksum(),
                        'modifiedTime' => $file->getModifiedTime()
                    ];
                }

                $pageToken = $response->getNextPageToken();
            } while ($pageToken != null);

            return $files;

        } catch (\Exception $e) {
            Log::error('Google Drive list files error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Sync files from Google Drive to local storage
     *
     * @param string $folderId Google Drive folder ID
     * @param string $localPath Local path to sync to
     * @return array ['downloaded' => int, 'skipped' => int, 'failed' => int]
     */
    public function syncFromGoogleDrive($folderId, $localPath)
    {
        $downloaded = 0;
        $skipped = 0;
        $failed = 0;

        try {
            // Ensure local directory exists
            if (!is_dir($localPath)) {
                mkdir($localPath, 0755, true);
            }

            // Get all files from Google Drive
            $driveFiles = $this->listFilesInFolder($folderId);
            
            Log::info("Starting sync from Google Drive folder {$folderId}. Found " . count($driveFiles) . " files.");

            foreach ($driveFiles as $driveFile) {
                $fileName = $driveFile['name'];
                $localFilePath = $localPath . '/' . $fileName;

                // Check if local file exists and is same
                if (file_exists($localFilePath)) {
                    $localMd5 = md5_file($localFilePath);
                    $remoteMd5 = $driveFile['md5'];

                    if ($localMd5 === $remoteMd5) {
                        Log::debug("File unchanged, skipping download: {$fileName}");
                        $skipped++;
                        continue;
                    }

                    Log::debug("File changed on Google Drive, downloading: {$fileName}");
                } else {
                    Log::debug("New file on Google Drive, downloading: {$fileName}");
                }

                // Download file
                $result = $this->downloadFile($driveFile['id'], $localFilePath);
                
                if ($result) {
                    $downloaded++;
                } else {
                    $failed++;
                }
            }

            Log::info("Sync completed from Google Drive. Downloaded: {$downloaded}, Skipped: {$skipped}, Failed: {$failed}");

        } catch (\Exception $e) {
            Log::error('Sync from Google Drive error: ' . $e->getMessage());
        }

        return [
            'downloaded' => $downloaded,
            'skipped' => $skipped,
            'failed' => $failed,
            'total' => count($driveFiles ?? [])
        ];
    }

    /**
     * Get or create folder for a group
     *
     * @param int $groupId
     * @param string $groupName
     * @return string|null Folder ID
     */
    public function getOrCreateGroupFolder($groupId, $groupName)
    {
        try {
            // Check if folder already exists by searching
            $rootFolderId = config('services.google_drive.root_folder_id');
            $folderName = "Group_{$groupId}_{$groupName}";
            
            $query = "name='{$folderName}' and mimeType='application/vnd.google-apps.folder' and trashed=false";
            if ($rootFolderId) {
                $query .= " and '{$rootFolderId}' in parents";
            }
            
            $response = $this->service->files->listFiles([
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id, name)'
            ]);

            $files = $response->getFiles();
            
            if (count($files) > 0) {
                return $files[0]->getId();
            }

            // Create new folder
            return $this->createFolder($folderName, $rootFolderId);
        } catch (\Exception $e) {
            Log::error('Google Drive get/create folder error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if Google Drive is configured
     *
     * @return bool
     */
    public function isConfigured()
    {
        $credentialsPath = storage_path('app/google-drive-credentials.json');
        $tokenPath = storage_path('app/google-drive-token.json');
        
        return file_exists($credentialsPath) && file_exists($tokenPath);
    }
}

