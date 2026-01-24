<?php

namespace Alptis\Wordpress\Healthcheck;

abstract class Check
{
    private string $name;
    private string $label;

    public function __construct(string $name, string $label)
    {
        $this->name = $name;
        $this->label = $label;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function check(): CheckResult
    {
        $startTime = microtime(true);
        return $this->executeCheck($startTime);
    }

    abstract protected function executeCheck(float $startTime): CheckResult;

    /**
     * Healthcheck endpoints are public: keep failure details (paths, hosts, user names…) out of the
     * check messages and write them to the PHP error log instead.
     */
    protected function logFailure(string $details): void
    {
        error_log(sprintf('[alptis-wordpress-healthcheck] %s: %s', $this->getName(), $details));
    }
}
