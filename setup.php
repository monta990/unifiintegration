<?php
/**
 * UniFi Integration — setup.php
 *
 * Author: Edwin Elias Alvarez
 * License: GPL v3+
 * GLPI 11.0+
 */

define('PLUGIN_UNIFIINTEGRATION_VERSION',  '1.0.0');
define('PLUGIN_UNIFIINTEGRATION_MIN_GLPI', '11.0.0');
define('PLUGIN_UNIFIINTEGRATION_MAX_GLPI', '12.0.0');

define('PLUGIN_UNIFIINTEGRATION_RIGHT_DASHBOARD', 'dashboard');
define('PLUGIN_UNIFIINTEGRATION_RIGHT_CONFIG',    'config');

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
// Init — autoloader + menu + cron
// --------------------------------------------------------------------------
function plugin_unifiintegration_init(): void
{
    Plugin::registerClass('PluginUnifiintegrationConfig',  ['classname' => 'PluginUnifiintegrationConfig']);
    Plugin::registerClass('PluginUnifiintegrationApi',     ['classname' => 'PluginUnifiintegrationApi']);
    Plugin::registerClass('PluginUnifiintegrationSync',    ['classname' => 'PluginUnifiintegrationSync']);
}

// --------------------------------------------------------------------------
// Menu entry
// --------------------------------------------------------------------------
function plugin_unifiintegration_getMenuContent(): array
{
    $menu = [];

    if (Session::haveRight('plugin_unifiintegration_dashboard', READ)) {
        $menu['title'] = 'UniFi Integration';
        $menu['page']  = '/plugins/unifiintegration/front/dashboard.php';
        $menu['icon']  = 'ti ti-wifi';

        $menu['options']['dashboard'] = [
            'title' => __('Dashboard', 'unifiintegration'),
            'page'  => '/plugins/unifiintegration/front/dashboard.php',
            'icon'  => 'ti ti-layout-dashboard',
        ];
        $menu['options']['config'] = [
            'title' => __('Configuration', 'unifiintegration'),
            'page'  => '/plugins/unifiintegration/front/config.form.php',
            'icon'  => 'ti ti-settings',
        ];
    }

    return $menu;
}

// --------------------------------------------------------------------------
// Cron tasks
// --------------------------------------------------------------------------
function plugin_unifiintegration_getAddSearchOptions(string $itemtype): array
{
    return [];
}

function cron_unifiintegration_sync(CronTask $task): int
{
    $sync = new PluginUnifiintegrationSync();
    return $sync->runCron($task);
}

function plugin_unifiintegration_getCronTasks(): array
{
    return [
        [
            'itemtype'  => 'PluginUnifiintegrationSync',
            'name'      => 'sync',
            'frequency' => 600,           // 10 min default
            'param'     => null,
            'state'     => CronTask::STATE_WAITING,
            'mode'      => CronTask::MODE_INTERNAL,
            'allowmode' => CronTask::MODE_INTERNAL | CronTask::MODE_EXTERNAL,
            'comment'   => 'Synchronize UniFi devices, sites and hosts to GLPI',
        ],
    ];
}
