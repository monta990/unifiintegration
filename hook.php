<?php
/**
 * UniFi Integration — hook.php
 *
 * Author: Edwin Elias Alvarez
 * License: GPL v3+
 */

// --------------------------------------------------------------------------
// Install
// --------------------------------------------------------------------------
function plugin_unifiintegration_install(): bool
{
    global $DB;

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

    // -- configs -------------------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_unifiintegration_configs')) {
        $DB->queryOrDie(
            "CREATE TABLE `glpi_plugin_unifiintegration_configs` (
                `id`                  int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `api_key`             varchar(500) DEFAULT NULL,
                `sync_devices`        tinyint(1) NOT NULL DEFAULT 1,
                `sync_sites`          tinyint(1) NOT NULL DEFAULT 1,
                `sync_hosts`          tinyint(1) NOT NULL DEFAULT 1,
                `cron_interval`       int NOT NULL DEFAULT 600,
                `devices_network_type` varchar(100) DEFAULT NULL,
                `hosts_network_type`   varchar(100) DEFAULT NULL,
                `last_sync`           datetime DEFAULT NULL,
                `last_sync_status`    varchar(20) DEFAULT NULL,
                `last_sync_message`   text DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
              COLLATE={$default_collation};",
            'Cannot create glpi_plugin_unifiintegration_configs'
        );

        // seed single config row
        $DB->insert('glpi_plugin_unifiintegration_configs', [
            'id'            => 1,
            'sync_devices'  => 1,
            'sync_sites'    => 1,
            'sync_hosts'    => 1,
            'cron_interval' => 600,
        ]);
    }

    // -- sites cache ---------------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_unifiintegration_sites')) {
        $DB->queryOrDie(
            "CREATE TABLE `glpi_plugin_unifiintegration_sites` (
                `id`                  int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `unifi_site_id`       varchar(255) NOT NULL,
                `name`                varchar(255) DEFAULT NULL,
                `description`         text DEFAULT NULL,
                `meta`                longtext DEFAULT NULL,
                `glpi_locations_id`   int NOT NULL DEFAULT 0,
                `last_seen`           datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unifi_site_id` (`unifi_site_id`(191))
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
              COLLATE={$default_collation};",
            'Cannot create glpi_plugin_unifiintegration_sites'
        );
    }

    // -- devices cache -------------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_unifiintegration_devices')) {
        $DB->queryOrDie(
            "CREATE TABLE `glpi_plugin_unifiintegration_devices` (
                `id`                      int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `unifi_device_id`         varchar(255) NOT NULL,
                `unifi_host_id`           varchar(500) DEFAULT NULL,
                `unifi_site_id`           varchar(255) DEFAULT NULL,
                `name`                    varchar(255) DEFAULT NULL,
                `model`                   varchar(100) DEFAULT NULL,
                `shortname`               varchar(100) DEFAULT NULL,
                `mac`                     varchar(20) DEFAULT NULL,
                `ip`                      varchar(45) DEFAULT NULL,
                `firmware_version`        varchar(100) DEFAULT NULL,
                `firmware_status`         varchar(50) DEFAULT NULL,
                `status`                  varchar(20) DEFAULT NULL,
                `product_line`            varchar(50) DEFAULT NULL,
                `is_console`              tinyint(1) NOT NULL DEFAULT 0,
                `glpi_networkequipments_id` int NOT NULL DEFAULT 0,
                `last_seen`               datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unifi_device_id` (`unifi_device_id`(191))
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
              COLLATE={$default_collation};",
            'Cannot create glpi_plugin_unifiintegration_devices'
        );
    }

    // -- sync logs -----------------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_unifiintegration_synclogs')) {
        $DB->queryOrDie(
            "CREATE TABLE `glpi_plugin_unifiintegration_synclogs` (
                `id`              int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `date_run`        datetime DEFAULT NULL,
                `status`          varchar(20) DEFAULT NULL,
                `devices_synced`  int NOT NULL DEFAULT 0,
                `sites_synced`    int NOT NULL DEFAULT 0,
                `hosts_synced`    int NOT NULL DEFAULT 0,
                `message`         text DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
              COLLATE={$default_collation};",
            'Cannot create glpi_plugin_unifiintegration_synclogs'
        );
    }

    // -- rights --------------------------------------------------------------
    $rights = [
        'plugin_unifiintegration_dashboard' => [
            'short_label'    => 'Dashboard',
            'long_label'     => 'UniFi Dashboard',
            'default'        => 'allstandardright',
        ],
        'plugin_unifiintegration_config' => [
            'short_label'    => 'Config',
            'long_label'     => 'UniFi Config',
            'default'        => 'allstandardright',
        ],
    ];

    foreach ($rights as $right => $opts) {
        ProfileRight::addProfileRights([$right]);
    }
    Profile::executeRightsProfilemigrations([]);

    return true;
}

// --------------------------------------------------------------------------
// Uninstall
// --------------------------------------------------------------------------
function plugin_unifiintegration_uninstall(): bool
{
    global $DB;

    $tables = [
        'glpi_plugin_unifiintegration_configs',
        'glpi_plugin_unifiintegration_sites',
        'glpi_plugin_unifiintegration_devices',
        'glpi_plugin_unifiintegration_synclogs',
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->queryOrDie("DROP TABLE IF EXISTS `{$table}`", "Cannot drop {$table}");
        }
    }

    ProfileRight::deleteProfileRights([
        'plugin_unifiintegration_dashboard',
        'plugin_unifiintegration_config',
    ]);

    // remove cron task
    $cron = new CronTask();
    $cron->deleteByCriteria(['itemtype' => 'PluginUnifiintegrationSync']);

    return true;
}
