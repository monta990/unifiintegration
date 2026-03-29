<?php
/**
 * UniFi Integration — setup.php
 *
 * Author: Edwin Elias Alvarez
 * License: GPL v3+
 * GLPI 11.0+
 */

use Glpi\Plugin\Hooks;

define('PLUGIN_UNIFIINTEGRATION_VERSION',  '1.0.0');
define('PLUGIN_UNIFIINTEGRATION_MIN_GLPI', '11.0.0');
define('PLUGIN_UNIFIINTEGRATION_MAX_GLPI', '12.0.0');

// --------------------------------------------------------------------------
// Plugin info
// --------------------------------------------------------------------------
function plugin_version_unifiintegration(): array
{
    return [
        'name'         => 'UniFi Integration',
        'version'      => PLUGIN_UNIFIINTEGRATION_VERSION,
        'author'       => 'Edwin Elias Alvarez',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/monta990/unifiintegration',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_UNIFIINTEGRATION_MIN_GLPI,
                'max' => PLUGIN_UNIFIINTEGRATION_MAX_GLPI,
            ],
        ],
    ];
}

// --------------------------------------------------------------------------
// Check prerequisites
// --------------------------------------------------------------------------
function plugin_unifiintegration_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_UNIFIINTEGRATION_MIN_GLPI, 'lt')) {
        echo sprintf(
            __('This plugin requires GLPI %s or higher.', 'unifiintegration'),
            PLUGIN_UNIFIINTEGRATION_MIN_GLPI
        );
        return false;
    }
    if (!extension_loaded('curl')) {
        echo __('This plugin requires the PHP cURL extension.', 'unifiintegration');
        return false;
    }
    return true;
}

// --------------------------------------------------------------------------
// Check config
// --------------------------------------------------------------------------
function plugin_unifiintegration_check_config(bool $verbose = false): bool
{
    return true;
}

// --------------------------------------------------------------------------
// Init — hooks, menu, config gear
// --------------------------------------------------------------------------
function plugin_init_unifiintegration(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['unifiintegration'] = true;
    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['unifiintegration']    = 'front/config.form.php';

    if (Session::getLoginUserID()) {
        $PLUGIN_HOOKS[Hooks::MENU_TOADD]['unifiintegration'] = [
            'tools' => 'PluginUnifiintegrationMenu',
        ];
        Plugin::registerClass('PluginUnifiintegrationConfig');
        Plugin::registerClass('PluginUnifiintegrationSync');
        Plugin::registerClass('PluginUnifiintegrationUtils');
    }
}

// --------------------------------------------------------------------------
// Cron dispatcher — GLPI calls cron{TaskName}() on the itemtype
// Task 'syncUnifi' → cronSyncUnifi() must exist in PluginUnifiintegrationSync
// --------------------------------------------------------------------------
function cron_unifiintegration_syncUnifi(CronTask $task): int
{
    $sync = new PluginUnifiintegrationSync();
    return $sync->runCron($task);
}
