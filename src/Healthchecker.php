<?php

namespace Alptis\Wordpress\Healthcheck;

class Healthchecker
{
    private string $healthQueryVariable;
    private string $healthQueryCheckVariable;
    private string $healthcheckPath;
    /** @var Check[] */
    private array $checks;
    private static ?Healthchecker $instance = null;

    public static function getInstance(): Healthchecker
    {
        if (self::$instance === null) {
            self::$instance = new Healthchecker();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->healthQueryVariable = $this->getEnvValueOrDefault('ALPTIS_WORDPRESS_HEALTHCHECK_QUERY_VAR_NAME', 'health_check');
        $this->healthQueryCheckVariable = $this->getEnvValueOrDefault('ALPTIS_WORDPRESS_HEALTHCHECK_QUERY_CHECK_VAR_NAME', 'health_check_check');
        $this->healthcheckPath = $this->getEnvValueOrDefault('ALPTIS_WORDPRESS_HEALTHCHECK_PATH', 'monitoring/health');
        $this->checks = [];
    }

    public function addCheck(Check $check): Healthchecker
    {
        $this->checks[$check->getName()] = $check;
        return $this;
    }

    private function getEnvValueOrDefault(string $environmentVariableName, string $defaultValue): string
    {
        return getenv($environmentVariableName) ?: $defaultValue;
    }


    public function addQueryVars($public_query_vars): array
    {
        $public_query_vars[] = $this->healthQueryVariable;
        $public_query_vars[] = $this->healthQueryCheckVariable;
        return $public_query_vars;
    }

    public function addRewrite(): void
    {
        add_rewrite_rule(
            sprintf('^%s(/run)?$', $this->healthcheckPath),
            sprintf('index.php?%s=true', $this->healthQueryVariable),
            'top'
        );
        foreach ($this->checks as $check) {
            add_rewrite_rule(
                sprintf('^%s/run/%s$', $this->healthcheckPath, $check->getName()),
                sprintf(
                    'index.php?%s=true&%s=%s',
                    $this->healthQueryVariable,
                    $this->healthQueryCheckVariable,
                    $check->getName()
                ),
                'top'
            );
        }
        flush_rewrite_rules();
    }

    private function formatCheckResult(Check $check, CheckResult $result): array
    {
        if ($result->isSuccess()) {
            $status = 0;
            $statusName = 'check_result_ok';
        } elseif ($result->isWarning()) {
            $status = 1;
            $statusName = 'check_result_warning';
        } else {
            $status = 3;
            $statusName = 'check_result_critical';
        }

        return [
            'checkName' => $check->getLabel(),
            'message' => $result->getMessage(),
            'status_name' => $statusName,
            'service_id' => $check->getName(),
            'status' => $status,
            'duration' => $result->getDuration(),
        ];
    }

    public function executeRequest($wp): void
    {
        if (
            array_key_exists($this->healthQueryVariable, $wp->query_vars)
            && 'true' === $wp->query_vars[$this->healthQueryVariable]
        ) {
            $this->runChecks($wp->query_vars[$this->healthQueryCheckVariable] ?? '');
        }
    }

    /**
     * Same as executeRequest(), reading the current URL instead of the WordPress query variables.
     * Useful when WordPress stops before parsing the request (e.g. when the database is down).
     * Does nothing unless the current request targets a healthcheck endpoint.
     */
    public function executeRequestFromUrl(): void
    {
        $requestedCheck = $this->getRequestedCheckFromUrl();
        if ($requestedCheck !== null) {
            $this->runChecks($requestedCheck);
        }
    }

    private function runChecks(string $checkName): void
    {
        $isSingleCheck = array_key_exists($checkName, $this->checks);
        $this->sendResponse($isSingleCheck ? [$this->checks[$checkName]] : $this->checks, $isSingleCheck);
    }

    /**
     * Tells whether the current request targets a healthcheck endpoint, without needing WordPress to be loaded.
     */
    public static function isHealthcheckRequest(): bool
    {
        return self::getInstance()->getRequestedCheckFromUrl() !== null;
    }

    /**
     * Returns null when the current URL is not a healthcheck endpoint,
     * an empty string for the endpoint running every check, or the name of the requested check.
     */
    private function getRequestedCheckFromUrl(): ?string
    {
        if (($_GET[$this->healthQueryVariable] ?? null) === 'true') {
            $checkName = $_GET[$this->healthQueryCheckVariable] ?? '';
            return is_string($checkName) ? $checkName : '';
        }

        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $homePath = defined('WP_HOME') ? (string) parse_url(WP_HOME, PHP_URL_PATH) : '';
        if ($homePath !== '' && str_starts_with($path, $homePath)) {
            $path = substr($path, strlen($homePath));
        }
        $path = trim($path, '/');
        $healthcheckPath = preg_quote(trim($this->healthcheckPath, '/'), '#');

        if (preg_match(sprintf('#^%s(/run)?$#', $healthcheckPath), $path)) {
            return '';
        }
        if (preg_match(sprintf('#^%s/run/([^/]+)$#', $healthcheckPath), $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param Check[] $checks
     */
    private function sendResponse(array $checks, bool $isSingleCheck): void
    {
        $checksResponses = [];
        $hasFailures = false;
        foreach ($checks as $check) {
            $checkResult = $check->check();
            $hasFailures = $hasFailures || !$checkResult->isSuccess();
            $checksResponses[] = $this->formatCheckResult($check, $checkResult);
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        if ($isSingleCheck) {
            echo json_encode(reset($checksResponses));
        } else {
            echo json_encode([
                'checks' => $checksResponses,
                'globalStatus' => $hasFailures ? 'KO' : 'OK',
            ]);
        }
        exit;
    }

    public function start(): void
    {
        add_filter('query_vars', [$this, 'addQueryVars'], 10, 1);
        add_action('parse_request', [$this, 'executeRequest'], 1, 1);
        add_action('init', [$this, 'addRewrite'], 1);
    }
}
