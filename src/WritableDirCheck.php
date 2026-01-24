<?php

namespace Alptis\Wordpress\Healthcheck;

class WritableDirCheck extends Check
{
    private string $dirPath;

    public function __construct(string $name, string $label, string $dirPath)
    {
        parent::__construct($name, $label);
        $this->dirPath = $dirPath;
    }

    protected function executeCheck(float $startTime): CheckResult
    {
        if (!is_writable($this->dirPath)) {
            return $this->failure(sprintf('directory "%s" is not writable', $this->dirPath), $startTime);
        }

        // tempnam() falls back to the system temp directory (with a notice) when it cannot write in dirPath
        $tmpfname = @tempnam($this->dirPath, "healthcheck_test_");
        if ($tmpfname === false || !file_exists($tmpfname) || !str_starts_with($tmpfname, $this->dirPath)) {
            if ($tmpfname !== false && file_exists($tmpfname)) {
                unlink($tmpfname);
            }
            return $this->failure(
                sprintf('unable to create a temporary file in directory "%s"', $this->dirPath),
                $startTime
            );
        }
        unlink($tmpfname);

        return CheckResult::success('Directory is writable', $startTime);
    }

    private function failure(string $details, float $startTime): CheckResult
    {
        $this->logFailure($details);

        return CheckResult::critical('Directory is not writable', $startTime);
    }
}
