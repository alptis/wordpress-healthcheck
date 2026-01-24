<?php

namespace Alptis\Wordpress\Healthcheck;

class CheckResult
{
    private string $status;
    private string $message;
    private float $duration;

    private const STATUS_SUCCESS = 'ok';
    private const STATUS_WARNING = 'warning';
    private const STATUS_CRITICAL = 'critical';

    protected function __construct(string $status, string $message, float $startTime, float $endTime)
    {
        $this->status = $status;
        $this->message = $message;
        $this->duration = $endTime - $startTime;
    }

    public static function success(string $message, float $startTime): CheckResult
    {
        return new CheckResult(self::STATUS_SUCCESS, $message, $startTime, microtime(true));
    }

    public static function critical(string $message, float $startTime): CheckResult
    {
        return new CheckResult(self::STATUS_CRITICAL, $message, $startTime, microtime(true));
    }

    public static function warning(string $message, float $startTime): CheckResult
    {
        return new CheckResult(self::STATUS_WARNING, $message, $startTime, microtime(true));
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isWarning(): bool
    {
        return $this->status === self::STATUS_WARNING;
    }

    public function isCritical(): bool
    {
        return $this->status === self::STATUS_CRITICAL;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }
}
