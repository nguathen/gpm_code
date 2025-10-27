<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GoogleDriveLogger
{
    protected $logger;
    protected $perfLogger;
    protected $isProduction;
    protected $debugEnabled;

    public function __construct()
    {
        $this->logger = Log::channel('google_drive');
        $this->perfLogger = Log::channel('performance');
        $this->isProduction = env('APP_ENV') === 'production';
        $this->debugEnabled = env('GOOGLE_DRIVE_DEBUG', false);
    }

    /**
     * Log debug message (only in dev or when debug enabled)
     */
    public function debug($message, $context = [])
    {
        if (!$this->isProduction || $this->debugEnabled) {
            $this->logger->debug($message, $context);
        }
    }

    /**
     * Log info message
     */
    public function info($message, $context = [])
    {
        $this->logger->info($message, $context);
    }

    /**
     * Log warning message
     */
    public function warning($message, $context = [])
    {
        $this->logger->warning($message, $context);
    }

    /**
     * Log error message
     */
    public function error($message, $context = [])
    {
        $this->logger->error($message, $context);
    }

    /**
     * Log performance metrics
     */
    public function perf($message, $duration = null, $context = [])
    {
        if ($duration !== null) {
            $context['duration_ms'] = round($duration * 1000, 2);
        }
        $this->perfLogger->info($message, $context);
    }

    /**
     * Log operation summary (always logged, concise)
     */
    public function summary($operation, $stats)
    {
        $message = "{$operation}: ";
        $parts = [];
        
        foreach ($stats as $key => $value) {
            $parts[] = "{$key}={$value}";
        }
        
        $message .= implode(', ', $parts);
        
        // Use appropriate level based on success
        if (isset($stats['failed']) && $stats['failed'] > 0) {
            $this->logger->warning($message);
        } else {
            $this->logger->info($message);
        }
    }

    /**
     * Start a performance timer
     */
    public function startTimer()
    {
        return microtime(true);
    }

    /**
     * End a performance timer and log
     */
    public function endTimer($start, $operation)
    {
        $duration = microtime(true) - $start;
        $this->perf($operation, $duration);
        return $duration;
    }
}

