<?php
/**
 * UniFi Integration — hook.php
 *
 * Author: Edwin Elias Alvarez
 * License: GPL v3+
 *
 * GLPI 11 rules:
 *  - DDL:        $DB->doQueryOrDie()
 *  - Dates:      TIMESTAMP (not DATETIME)
 *  - Int keys:   int {$sign} = INT UNSIGNED
 *  - Cron mode:  MODE_EXTERNAL (CLI cron picks it up)
 *  - Rights:     deleteProfileRights() before addProfileRights() — idempotent
 */

function plugin_unifiintegration_install(): bool
{
    global $DB;

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $sign      = DBConnection::getDefaultPrimaryKeySignOption();

    // ── configs ────────────────────────────────────────────────────────────
    if (!$DB->tableExists('glpi_plugin_unifiintegration_configs')) {
        $DB->doQueryOrDie(
            "CREATE TABLE `glpi_plugin_unifiintegration_configs` (
                `id`                   int {$sign} NOT NULL AUTO_INCREMENT,
                `api_key`              varchar(500) NOT NULL DEFAULT '',
                `sync_devices`         tinyint(1)   NOT NULL DEFAULT '1',
                `sync_sites`           tinyint(1)   NOT NULL DEFAULT '1',
                `sync_hosts`           tinyint(1)   NOT NULL DEFAULT '1',
                `cron_interval`        int {$sign}  NOT NULL DEFAULT '600',
                `devices_network_type` varchar(100) DEFAULT NULL,
                `hosts_network_type`   varchar(100) DEFAULT NULL,
                `last_sync`            timestamp    NULL DEFAULT NULL,
                `last_sync_status`     varchar(20)  DEFAULT NULL,
                `last_sync_message`    text         DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}"
        );

        $DB->insert('glpi_plugin_unifiintegration_configs', [
            'id'            => 1,
            'sync_devices'  => 1,
            'sync_sites'    => 1,
            'sync_hosts'    => 1,
            'cron_interval' => 600,
        ]);
    }

    // ── sites ──────────────────────────────────────────────────────────────
    if (!$DB->tableExists('glpi_plugin_unifiintegration_sites')) {
        $DB->doQueryOrDie(
            "CREATE TABLE `glpi_plugin_unifiintegration_sites` (
                `id`                int {$sign} NOT NULL AUTO_INCREMENT,
                `unifi_site_id`     varchar(255) NOT NULL DEFAULT '',
                `name`              varchar(255) DEFAULT NULL,
                `description`       text         DEFAULT NULL,
                `meta`              longtext     DEFAULT NULL,
                `glpi_locations_id` int {$sign}  NOT NULL DEFAULT '0',
                `last_seen`         timestamp    NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unifi_site_id` (`unifi_site_id`(191))
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}"
        );
    }

    // ── devices ────────────────────────────────────────────────────────────
    if (!$DB->tableExists('glpi_plugin_unifiintegration_devices')) {
        $DB->doQueryOrDie(
            "CREATE TABLE `glpi_plugin_unifiintegration_devices` (
                `id`                        int {$sign} NOT NULL AUTO_INCREMENT,
                `unifi_device_id`           varchar(255) NOT NULL DEFAULT '',
                `unifi_host_id`             varchar(500) DEFAULT NULL,
                `unifi_site_id`             varchar(255) DEFAULT NULL,
                `name`                      varchar(255) DEFAULT NULL,
                `model`                     varchar(100) DEFAULT NULL,
                `shortname`                 varchar(100) DEFAULT NULL,
                `mac`                       varchar(20)  DEFAULT NULL,
                `ip`                        varchar(45)  DEFAULT NULL,
                `firmware_version`          varchar(100) DEFAULT NULL,
                `firmware_status`           varchar(50)  DEFAULT NULL,
                `status`                    varchar(20)  DEFAULT NULL,
                `product_line`              varchar(50)  DEFAULT NULL,
                `is_console`                tinyint(1)   NOT NULL DEFAULT '0',
                `glpi_networkequipments_id` int {$sign}  NOT NULL DEFAULT '0',
                `last_seen`                 timestamp    NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unifi_device_id` (`unifi_device_id`(191))
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}"
        );
    }

    // ── synclogs ───────────────────────────────────────────────────────────
    if (!$DB->tableExists('glpi_plugin_unifiintegration_synclogs')) {
        $DB->doQueryOrDie(
            "CREATE TABLE `glpi_plugin_unifiintegration_synclogs` (
                `id`             int {$sign} NOT NULL AUTO_INCREMENT,
                `date_run`       timestamp   NULL DEFAULT NULL,
                `status`         varchar(20) DEFAULT NULL,
                `devices_synced` int {$sign} NOT NULL DEFAULT '0',
                `sites_synced`   int {$sign} NOT NULL DEFAULT '0',
                `hosts_synced`   int {$sign} NOT NULL DEFAULT '0',
                `message`        text        DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}"
        );
    }

    // No custom plugin rights needed — front files use standard 'config' right

    // ── Cron — MODE_EXTERNAL so the CLI cron daemon picks it up ───────────
    CronTask::unregister('unifiintegration');
    CronTask::register(
        'PluginUnifiintegrationSync',
        'syncUnifi',
        10 * MINUTE_TIMESTAMP,
        [
            'comment'   => 'Synchronize UniFi devices, sites and hosts to GLPI NetworkEquipment',
            'mode'      => CronTask::MODE_EXTERNAL,
            'state'     => CronTask::STATE_WAITING,
            'logs_days' => 30,
        ]
    );
    $DB->update(
        CronTask::getTable(),
        [
            'frequency' => 10 * MINUTE_TIMESTAMP,
            'mode'      => CronTask::MODE_EXTERNAL,
            'state'     => CronTask::STATE_WAITING,
            'comment'   => 'Synchronize UniFi devices, sites and hosts to GLPI NetworkEquipment',
        ],
        ['itemtype' => 'PluginUnifiintegrationSync', 'name' => 'syncUnifi']
    );

    return true;
}

function plugin_unifiintegration_uninstall(): bool
{
    global $DB;

    foreach ([
        'glpi_plugin_unifiintegration_configs',
        'glpi_plugin_unifiintegration_sites',
        'glpi_plugin_unifiintegration_devices',
        'glpi_plugin_unifiintegration_synclogs',
    ] as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQueryOrDie("DROP TABLE `{$table}`");
        }
    }

    CronTask::unregister('unifiintegration');

    return true;
}
