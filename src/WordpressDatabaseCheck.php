<?php

namespace Alptis\Wordpress\Healthcheck;

class WordpressDatabaseCheck extends Check
{
    private ?string $connectionError;

    /**
     * @param string|null $connectionError Known connection error, when WordPress already failed to connect
     */
    public function __construct(?string $connectionError = null)
    {
        parent::__construct('wordpress_database', 'WordPress database');
        $this->connectionError = $connectionError;
    }

    /**
     * Answers the healthcheck endpoints when WordPress cannot connect to its database.
     *
     * WordPress connects to the database before loading plugins, so this plugin never runs in that case:
     * call this method from the db.php drop-in (see alptis/bedrock-db-error-page) before WordPress stops.
     * Does nothing unless the current request targets a healthcheck endpoint.
     */
    public static function handleConnectionFailure(string $reason): void
    {
        Healthchecker::getInstance()
            ->addCheck(new self($reason))
            ->executeRequestFromUrl();
    }

    protected function executeCheck(float $startTime): CheckResult
    {
        if ($this->connectionError !== null) {
            return $this->failure($this->connectionError, $startTime);
        }

        global $wpdb;
        try {
            $isConnected = $wpdb->check_connection(false);
        } catch (\Throwable $e) {
            return $this->failure($e->getMessage(), $startTime);
        }

        if ($isConnected) {
            return CheckResult::success('Database is accessible', $startTime);
        } else {
            return $this->failure($wpdb->last_error ?: 'connection check failed', $startTime);
        }
    }

    private function failure(string $details, float $startTime): CheckResult
    {
        $this->logFailure($details);

        return CheckResult::critical('Database is not accessible', $startTime);
    }
}
