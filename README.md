# alptis/wordpress-healthcheck

WordPress plugin that exposes monitoring endpoints out of the box and lets you add your own checks programmatically.

The JSON output is compatible with the healthcheck status APIs of [LiipMonitorBundle](https://github.com/liip/LiipMonitorBundle), so the same monitoring tools can read both your Symfony and WordPress applications.

## Requirements

- PHP 8.0 or later
- WordPress with pretty permalinks enabled (the endpoints rely on rewrite rules)

## Installation

```shell
composer require alptis/wordpress-healthcheck
```

The package is of type `wordpress-plugin`, so [composer/installers](https://github.com/composer/installers) installs it in your plugins directory (in a [Bedrock](https://roots.io/bedrock/) project: `web/app/plugins`). Then activate the plugin in WordPress.

The plugin ships with its own autoloader. When a Composer autoloader is already loaded, it takes precedence for performance reasons.

## Default checks

| Class | Name | Description |
| --- | --- | --- |
| `WordpressDatabaseCheck` | `wordpress_database` | Checks that the database is reachable. |
| `WordpressUploadDirCheck` | `wordpress_uploaddir` | Checks that the uploads directory is writable (uses `WritableDirCheck`). |
| `WritableDirCheck` | – | Checks that a given directory is writable. Extend it to monitor other directories. |

## Endpoints

The default path is `/monitoring/health`.

| URL | Description |
| --- | --- |
| `/monitoring/health/run` | Runs every registered check and returns their statuses along with a global status. |
| `/monitoring/health/run/CHECK_NAME` | Runs a single check, for example `/monitoring/health/run/wordpress_database`. |

## Database outages

WordPress connects to its database before loading plugins: when the database is down, WordPress stops before this plugin runs, and the endpoints cannot answer.

In a [Bedrock](https://roots.io/bedrock/) project, install [alptis/bedrock-db-error-page](https://packagist.org/packages/alptis/bedrock-db-error-page) to cover this case. Its `db.php` drop-in calls `WordpressDatabaseCheck::handleConnectionFailure()` when the connection fails, so the endpoints still return their JSON report (HTTP 200):

- `/monitoring/health/run` returns `globalStatus: "KO"` with the `wordpress_database` check as critical. The other checks are not listed, since WordPress stops before registering them.
- `/monitoring/health/run/wordpress_database` returns the critical database check.
- Any other check URL returns the same report as `/monitoring/health/run`, as it does for unknown checks.

Every other URL keeps the regular database error page.

If you use another `db.php` drop-in, add this before WordPress stops:

```php
use Alptis\Wordpress\Healthcheck\Healthchecker;
use Alptis\Wordpress\Healthcheck\WordpressDatabaseCheck;

if (class_exists(Healthchecker::class) && Healthchecker::isHealthcheckRequest()) {
    // The error is only logged, never displayed. Sends the JSON report and exits.
    WordpressDatabaseCheck::handleConnectionFailure(mysqli_connect_error() ?: 'connection failed');
}
```

## Security

The endpoints are public, so the default checks never expose details such as database errors, hosts, user names or file paths: their responses only say `Database is not accessible` or `Directory is not writable`. The details are written to the PHP error log with `error_log()`, prefixed with `[alptis-wordpress-healthcheck]` and the check name:

```
[alptis-wordpress-healthcheck] wordpress_database: connection failed: Access denied for user 'wp'@'10.0.0.5'
[alptis-wordpress-healthcheck] wordpress_uploaddir: directory "/var/www/web/app/uploads" is not writable
```

In a Bedrock project, these lines end up wherever PHP errors are logged (for instance `web/app/debug.log` when `WP_DEBUG_LOG` is enabled).

Custom checks should follow the same rule: return a generic message and call `$this->logFailure($details)` for the details.

## Configuration

The following environment variables are optional:

| Variable | Default | Description |
| --- | --- | --- |
| `ALPTIS_WORDPRESS_HEALTHCHECK_PATH` | `monitoring/health` | Base path of the endpoints. |
| `ALPTIS_WORDPRESS_HEALTHCHECK_QUERY_VAR_NAME` | `health_check` | WordPress query variable used to trigger the healthcheck. |
| `ALPTIS_WORDPRESS_HEALTHCHECK_QUERY_CHECK_VAR_NAME` | `health_check_check` | WordPress query variable holding the name of a single check. |

## Adding custom checks

You can add your own checks from your theme's `functions.php` or from a dedicated plugin, using the existing classes as a base:

```php
use Alptis\Wordpress\Healthcheck\Check;
use Alptis\Wordpress\Healthcheck\CheckResult;
use Alptis\Wordpress\Healthcheck\Healthchecker;

// Custom check definition
class CustomCheck extends Check
{
    public function __construct()
    {
        parent::__construct('custom_check', 'Custom Check');
    }

    protected function executeCheck(float $startTime): CheckResult
    {
        // Custom check logic here
        $condition = true; // Replace with actual condition

        if ($condition) {
            return CheckResult::success('Custom check passed', $startTime);
        }

        // Details go to the PHP error log, the public response stays generic
        $this->logFailure('Detailed reason, may contain sensitive information');

        return CheckResult::critical('Custom check failed', $startTime);
    }
}

// Register the custom check
Healthchecker::getInstance()
    ->addCheck(new CustomCheck());
```

## Development

```shell
composer install
composer lint      # check coding standards
composer lint:fix  # automatically fix what can be fixed
```

## License

This project is released under the [MIT License](LICENSE).

## Disclaimer

This project is not affiliated with or endorsed by the [WordPress Foundation](https://wordpressfoundation.org/) or [Liip](https://www.liip.ch/). WordPress and LiipMonitorBundle are mentioned only to describe what this package works with.
