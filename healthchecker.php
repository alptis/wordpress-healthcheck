<?php

namespace Alptis\Wordpress\Healthcheck;

/**
 * Plugin Name: alptis-wordpress-healthcheck
 * Description: Healthcheck endpoints for WordPress sites, compatible with LiipMonitorBundle.
 * Version: 1.0.0
 * Requires PHP: 8.0
 * Author: Alptis
 * Author URI: https://www.alptis.org
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: alptis-wordpress-healthcheck
 */

if (!defined('WPINC')) {
    die;
}
if (!class_exists(__NAMESPACE__ . '\\Healthchecker')) {
    function autoload_classes($class_name)
    {
        $prefix = __NAMESPACE__ . '\\';
        $base_dir = __DIR__ . '/src/';
        if (str_starts_with($class_name, $prefix)) {
            $relative_class = str_replace($prefix, '', $class_name);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    spl_autoload_register(function ($class_name) {
        autoload_classes($class_name);
    });
}



$healthchecker = Healthchecker::getInstance();
$healthchecker
    ->addCheck(new WordpressDatabaseCheck())
    ->addCheck(new WordpressUploadDirCheck())
;
$healthchecker->start();
