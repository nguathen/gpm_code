<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\Profile;

class MigrateProfileFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'profiles:migrate-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate profile files from old structure to group-based folder structure';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting profile files migration...');
        
        $oldProfilesPath = storage_path('app/public/profiles');
        
        // Check if old profiles folder exists
        if (!file_exists($oldProfilesPath)) {
            $this->error('Profiles folder does not exist: ' . $oldProfilesPath);
            return 1;
        }

        // Get all files in the old profiles folder (not in subdirectories)
        $files = array_diff(scandir($oldProfilesPath), ['.', '..']);
        $files = array_filter($files, function($file) use ($oldProfilesPath) {
            return is_file($oldProfilesPath . '/' . $file);
        });

        if (empty($files)) {
            $this->info('No files found in old profiles folder.');
            return 0;
        }

        $this->info('Found ' . count($files) . ' files to migrate.');
        
        $movedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        // Get all profiles with their s3_path and group_id
        $profiles = Profile::all();
        
        $this->info('Processing ' . $profiles->count() . ' profiles...');
        
        $progressBar = $this->output->createProgressBar(count($files));
        $progressBar->start();

        foreach ($files as $fileName) {
            try {
                // Find which profile this file belongs to by matching s3_path
                $matchedProfile = null;
                
                foreach ($profiles as $profile) {
                    $s3Path = $profile->s3_path;
                    // Check if filename starts with profile's s3_path
                    if (strpos($fileName, $s3Path) === 0) {
                        $matchedProfile = $profile;
                        break;
                    }
                }

                if ($matchedProfile) {
                    $groupId = $matchedProfile->group_id;
                    $groupFolder = 'profiles/' . $groupId;
                    
                    // Create group folder if not exists
                    if (!Storage::disk('public')->exists($groupFolder)) {
                        Storage::disk('public')->makeDirectory($groupFolder);
                        $this->line("\nCreated folder: {$groupFolder}");
                    }

                    // Move file to group folder
                    $oldPath = 'profiles/' . $fileName;
                    $newPath = $groupFolder . '/' . $fileName;
                    
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->move($oldPath, $newPath);
                        $movedCount++;
                        $this->line("\nMoved: {$fileName} -> group_{$groupId}/");
                    } else {
                        $skippedCount++;
                    }
                } else {
                    // File doesn't match any profile, skip it
                    $this->line("\nSkipped (no matching profile): {$fileName}");
                    $skippedCount++;
                }
            } catch (\Exception $e) {
                $this->error("\nError processing {$fileName}: " . $e->getMessage());
                $errorCount++;
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Migration completed!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Moved', $movedCount],
                ['Skipped', $skippedCount],
                ['Errors', $errorCount],
                ['Total', count($files)]
            ]
        );

        return 0;
    }
}

