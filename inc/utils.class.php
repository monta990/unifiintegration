<?php
/**
 * UniFi Integration — PluginUnifiintegrationUtils
 *
 * Logging has two tiers:
 *   - MINIMAL  (default) — only errors and key sync results
 *   - VERBOSE  (debug on) — full API responses, all requests
 *
 * Author: Edwin Elias Alvarez
 * License: GPL v3+
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginUnifiintegrationUtils
{
    /**
     * Returns true when verbose logging should be written.
     */
    public static function isDebug(): bool
    {
        if (isset($_SESSION['glpi_use_mode']) && (int)$_SESSION['glpi_use_mode'] === Session::DEBUG_MODE) {
            return true;
        }
        if (!isset($_SESSION['_unifi_debug'])) {
            try {
                $cfg = PluginUnifiintegrationConfig::getConfig();
                $_SESSION['_unifi_debug'] = !empty($cfg['debug_logging']) ? 1 : 0;
            } catch (\Throwable $e) {
                $_SESSION['_unifi_debug'] = 0;
            }
        }
        return (bool)$_SESSION['_unifi_debug'];
    }

    /**
     * Write a log entry to files/log/unifiintegration.log
     * @param bool $verbose  If true, only written in debug mode.
     */
    public static function log(string $message, bool $verbose = false): void
    {
        if ($verbose && !self::isDebug()) {
            return;
        }
        Toolbox::logInFile('unifiintegration', $message . PHP_EOL);
    }

    /**
     * Shorthand — write only in debug/verbose mode.
     */
    public static function debug(string $message): void
    {
        self::log($message, true);
    }

    public static function encrypt(string $value): string
    {
        return (new GLPIKey())->encrypt($value);
    }

    public static function decrypt(string $value): string
    {
        try {
            return (new GLPIKey())->decrypt($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }
}
