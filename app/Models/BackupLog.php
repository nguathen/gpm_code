<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'group_id',
        'profile_id',
        'operation',
        'status',
        'total_files',
        'processed_files',
        'success_count',
        'skipped_count',
        'failed_count',
        'total_size',
        'duration',
        'error_message',
        'failed_files',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'failed_files' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_size' => 'integer',
        'duration' => 'float'
    ];

    /**
     * Get the group associated with this backup log
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the profile associated with this backup log
     */
    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Update progress
     */
    public function updateProgress($processed, $success = 0, $skipped = 0, $failed = 0)
    {
        $this->update([
            'processed_files' => $processed,
            'success_count' => $success,
            'skipped_count' => $skipped,
            'failed_count' => $failed
        ]);
    }

    /**
     * Mark as completed
     */
    public function markCompleted($success, $skipped, $failed, $failedFiles = [])
    {
        $this->update([
            'status' => $failed > 0 ? 'failed' : 'completed',
            'success_count' => $success,
            'skipped_count' => $skipped,
            'failed_count' => $failed,
            'failed_files' => $failedFiles,
            'processed_files' => $success + $skipped + $failed,
            'completed_at' => now(),
            'duration' => $this->started_at ? now()->diffInSeconds($this->started_at) : null
        ]);
    }

    /**
     * Mark as failed
     */
    public function markFailed($errorMessage)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
            'duration' => $this->started_at ? now()->diffInSeconds($this->started_at) : null
        ]);
    }

    /**
     * Start processing
     */
    public function markRunning()
    {
        $this->update([
            'status' => 'running',
            'started_at' => now()
        ]);
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage()
    {
        if ($this->total_files == 0) {
            return 0;
        }
        return round(($this->processed_files / $this->total_files) * 100, 1);
    }

    /**
     * Get formatted size
     */
    public function getFormattedSizeAttribute()
    {
        $bytes = $this->total_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute()
    {
        if (!$this->duration) {
            return 'N/A';
        }
        
        if ($this->duration < 60) {
            return round($this->duration, 1) . 's';
        }
        
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        
        return "{$minutes}m " . round($seconds) . 's';
    }
}

