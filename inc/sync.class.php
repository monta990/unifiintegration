<?php
/**
 * UniFi Integration — inc/sync.class.php
 *
 * Syncs UniFi data to GLPI:
 *   Sites   → glpi_locations
 *   Hosts   → glpi_networkequipments  (isConsole = true)
 *   Devices → glpi_networkequipments  (isConsole = false)
 *
 * Author: Edwin Elias Alvarez
 * License: GPL v3+
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginUnifiintegrationSync extends CommonGLPI
{
    public static $rightname = 'plugin_unifiintegration_config';

    // --------------------------------------------------------------------------
    // Manual sync (AJAX)
    // --------------------------------------------------------------------------
    public function runManual(): array
    {
        $cfg = PluginUnifiintegrationConfig::getConfig();
        if (empty($cfg['api_key'])) {
            return [
                'success' => false,
                'message' => __('API Key is not configured.', 'unifiintegration'),
            ];
        }

        try {
            $api    = new PluginUnifiintegrationApi($cfg['api_key']);
            $result = $this->doSync($api, $cfg);
            $this->updateLastSync($result, $cfg);
            return $result;
        } catch (RuntimeException $e) {
            $err = ['success' => false, 'message' => $e->getMessage()];
            $this->updateLastSync($err, $cfg);
            return $err;
        }
    }

    // --------------------------------------------------------------------------
    // Cron entry point — GLPI calls cronSyncUnifi() matching task name 'syncUnifi'
    // --------------------------------------------------------------------------
    public static function cronSyncUnifi(CronTask $task): int
    {
        $sync = new self();
        return $sync->runCron($task);
    }

    public function runCron(CronTask $task): int
    {
        $cfg = PluginUnifiintegrationConfig::getConfig();
        if (empty($cfg['api_key'])) {
            $task->addVolume(0);
            return 1;
        }

        // update cron task frequency from config
        if (!empty($cfg['cron_interval'])) {
            CronTask::register(
                'PluginUnifiintegrationSync',
                'sync',
                (int)$cfg['cron_interval'],
                ['state' => CronTask::STATE_WAITING]
            );
        }

        try {
            $api    = new PluginUnifiintegrationApi($cfg['api_key']);
            $result = $this->doSync($api, $cfg);
            $this->updateLastSync($result, $cfg);
            $vol = ($result['devices_synced'] ?? 0)
                 + ($result['sites_synced']   ?? 0)
                 + ($result['hosts_synced']   ?? 0);
            $task->addVolume($vol);
            return 1;
        } catch (RuntimeException $e) {
            $this->updateLastSync(['success' => false, 'message' => $e->getMessage()], $cfg);
            return 0;
        }
    }

    // --------------------------------------------------------------------------
    // Core sync logic
    // --------------------------------------------------------------------------
    private function doSync(PluginUnifiintegrationApi $api, array $cfg): array
    {
        $counters = [
            'success'       => true,
            'sites_synced'  => 0,
            'hosts_synced'  => 0,
            'devices_synced'=> 0,
            'message'       => '',
        ];

        $messages = [];

        // ── Sites → Location ──────────────────────────────────────────────
        if (!empty($cfg['sync_sites'])) {
            $siteMap = $this->syncSites($api);
            $counters['sites_synced'] = count($siteMap);
            $messages[] = sprintf(
                __('%d site(s) synced to Locations', 'unifiintegration'),
                count($siteMap)
            );
        } else {
            $siteMap = $this->loadSiteMap();
        }

        // ── Hosts → NetworkEquipment ───────────────────────────────────────
        if (!empty($cfg['sync_hosts'])) {
            $hostsData = $api->getHosts();
            $hosts     = $hostsData['data'] ?? [];
            foreach ($hosts as $host) {
                $this->syncHost($host, $siteMap);
                $counters['hosts_synced']++;
            }
            $messages[] = sprintf(
                __('%d host(s) synced to NetworkEquipment', 'unifiintegration'),
                $counters['hosts_synced']
            );
        }

        // ── Devices → NetworkEquipment ─────────────────────────────────────
        if (!empty($cfg['sync_devices'])) {
            $devicesData = $api->getDevices();
            $hostGroups  = $devicesData['data'] ?? [];
            foreach ($hostGroups as $group) {
                $hostId  = $group['hostId']  ?? '';
                $devices = $group['devices'] ?? [];
                foreach ($devices as $device) {
                    $device['_hostId'] = $hostId;
                    $this->syncDevice($device, $siteMap);
                    $counters['devices_synced']++;
                }
            }
            $messages[] = sprintf(
                __('%d device(s) synced to NetworkEquipment', 'unifiintegration'),
                $counters['devices_synced']
            );
        }

        $counters['message'] = implode(', ', $messages);
        $this->insertLog($counters);
        return $counters;
    }

    // --------------------------------------------------------------------------
    // Sync helpers
    // --------------------------------------------------------------------------
    private function syncSites(PluginUnifiintegrationApi $api): array
    {
        global $DB;

        $raw     = $api->getSites();
        $sites   = $raw['data'] ?? [];
        $siteMap = [];   // unifi_site_id => glpi_locations_id

        foreach ($sites as $site) {
            $uid  = $site['siteId']   ?? ($site['id']   ?? '');
            $name = $site['siteName'] ?? ($site['name'] ?? '');
            if (!$uid) {
                continue;
            }

            // Find or create GLPI Location
            $locId = $this->findOrCreateLocation($name, $uid);

            // Upsert into our cache
            $existing = $DB->request([
                'FROM'  => 'glpi_plugin_unifiintegration_sites',
                'WHERE' => ['unifi_site_id' => $uid],
                'LIMIT' => 1,
            ]);

            $row = [
                'name'              => $name,
                'meta'              => json_encode($site),
                'glpi_locations_id' => $locId,
                'last_seen'         => date('Y-m-d H:i:s'),
            ];

            if ($existing->count()) {
                $DB->update('glpi_plugin_unifiintegration_sites', $row, ['unifi_site_id' => $uid]);
            } else {
                $DB->insert('glpi_plugin_unifiintegration_sites', array_merge($row, ['unifi_site_id' => $uid]));
            }

            $siteMap[$uid] = $locId;
        }

        return $siteMap;
    }

    private function findOrCreateLocation(string $name, string $comment = ''): int
    {
        global $DB;

        if (!$name) {
            return 0;
        }

        $iter = $DB->request([
            'FROM'   => 'glpi_locations',
            'WHERE'  => ['name' => $name, 'is_deleted' => 0],
            'LIMIT'  => 1,
        ]);

        if ($iter->count()) {
            return (int)$iter->current()['id'];
        }

        $loc = new Location();
        return $loc->add([
            'name'      => $name,
            'comment'   => '[UniFi] ' . $comment,
            'is_recursive' => 1,
        ]);
    }

    private function syncHost(array $host, array $siteMap): void
    {
        global $DB;

        $uid      = $host['id']        ?? '';
        $ip       = $host['ipAddress'] ?? '';
        $state    = $host['reportedState'] ?? [];
        $name     = $host['userData']['name']      ?? ($state['hostname'] ?? $uid);
        $firmware = $state['version']  ?? '';
        $model    = $state['hardware']['shortname'] ?? ($state['model'] ?? '');
        $mac      = $state['mac']      ?? '';
        $status   = $host['lastConnectionStateChange'] ? 'online' : 'unknown';

        // isConsole = true for hosts
        $this->upsertNetworkEquipment([
            'unifi_device_id'  => 'host_' . $uid,
            'unifi_host_id'    => $uid,
            'name'             => $name ?: $uid,
            'mac'              => $mac,
            'ip'               => $ip,
            'firmware_version' => $firmware,
            'firmware_status'  => 'N/A',
            'status'           => $status,
            'model'            => $model,
            'product_line'     => 'console',
            'is_console'       => 1,
            'unifi_site_id'    => '',
        ], $siteMap);
    }

    private function syncDevice(array $device, array $siteMap): void
    {
        $uid    = $device['id']          ?? ($device['mac'] ?? '');
        $hostId = $device['_hostId']     ?? '';
        $siteId = $device['siteId']      ?? '';

        $this->upsertNetworkEquipment([
            'unifi_device_id'  => $uid,
            'unifi_host_id'    => $hostId,
            'unifi_site_id'    => $siteId,
            'name'             => $device['name']             ?? $uid,
            'model'            => $device['model']            ?? '',
            'shortname'        => $device['shortname']        ?? '',
            'mac'              => $device['mac']              ?? '',
            'ip'               => $device['ip']               ?? '',
            'firmware_version' => $device['version']          ?? '',
            'firmware_status'  => $device['firmwareStatus']   ?? '',
            'status'           => $device['status']           ?? '',
            'product_line'     => $device['productLine']      ?? '',
            'is_console'       => (int)($device['isConsole']  ?? 0),
        ], $siteMap);
    }

    private function upsertNetworkEquipment(array $data, array $siteMap): void
    {
        global $DB;

        $uid = $data['unifi_device_id'] ?? '';
        if (!$uid) {
            return;
        }

        $locId = $siteMap[$data['unifi_site_id'] ?? ''] ?? 0;

        // Find or create NetworkEquipment
        $existing = $DB->request([
            'FROM'  => 'glpi_plugin_unifiintegration_devices',
            'WHERE' => ['unifi_device_id' => $uid],
            'LIMIT' => 1,
        ]);

        $cacheRow = array_merge($data, [
            'glpi_networkequipments_id' => 0,
            'last_seen'                 => date('Y-m-d H:i:s'),
        ]);

        $glpiId = 0;

        if ($existing->count()) {
            $old    = $existing->current();
            $glpiId = (int)$old['glpi_networkequipments_id'];
            $DB->update('glpi_plugin_unifiintegration_devices', $cacheRow, ['unifi_device_id' => $uid]);
        } else {
            $DB->insert('glpi_plugin_unifiintegration_devices', $cacheRow);
        }

        // Sync to glpi_networkequipments
        $ne    = new NetworkEquipment();
        $neRow = [
            'name'              => $data['name'],
            'ip'                => $data['ip'],
            'mac'               => $data['mac'],
            'comment'           => '[UniFi] model: ' . ($data['model'] ?? '') . ' | firmware: ' . ($data['firmware_version'] ?? ''),
            'locations_id'      => $locId,
            'is_recursive'      => 1,
            'serial'            => $data['mac'],
        ];

        if ($glpiId && $ne->getFromDB($glpiId)) {
            $ne->update(array_merge($neRow, ['id' => $glpiId]));
        } else {
            $newId = $ne->add($neRow);
            if ($newId) {
                $DB->update(
                    'glpi_plugin_unifiintegration_devices',
                    ['glpi_networkequipments_id' => $newId],
                    ['unifi_device_id' => $uid]
                );
            }
        }
    }

    private function loadSiteMap(): array
    {
        global $DB;

        $iter = $DB->request([
            'SELECT' => ['unifi_site_id', 'glpi_locations_id'],
            'FROM'   => 'glpi_plugin_unifiintegration_sites',
        ]);

        $map = [];
        foreach ($iter as $row) {
            $map[$row['unifi_site_id']] = (int)$row['glpi_locations_id'];
        }
        return $map;
    }

    private function insertLog(array $result): void
    {
        global $DB;

        $DB->insert('glpi_plugin_unifiintegration_synclogs', [
            'date_run'       => date('Y-m-d H:i:s'),
            'status'         => $result['success'] ? 'success' : 'error',
            'devices_synced' => $result['devices_synced'] ?? 0,
            'sites_synced'   => $result['sites_synced']   ?? 0,
            'hosts_synced'   => $result['hosts_synced']   ?? 0,
            'message'        => $result['message']        ?? '',
        ]);

        // Keep only last 100 log entries — delete oldest beyond that
        $keep = $DB->request([
            'SELECT' => 'id',
            'FROM'   => 'glpi_plugin_unifiintegration_synclogs',
            'ORDER'  => ['id DESC'],
            'LIMIT'  => 100,
        ]);
        $keepIds = [];
        foreach ($keep as $row) {
            $keepIds[] = (int)$row['id'];
        }
        if (!empty($keepIds)) {
            $DB->delete(
                'glpi_plugin_unifiintegration_synclogs',
                [
                    'NOT' => ['id' => $keepIds],
                ]
            );
        }
    }

    private function updateLastSync(array $result, array $cfg): void
    {
        PluginUnifiintegrationConfig::saveConfig([
            'last_sync'         => date('Y-m-d H:i:s'),
            'last_sync_status'  => $result['success'] ? 'success' : 'error',
            'last_sync_message' => $result['message'] ?? '',
            // preserve all other fields
            'api_key'           => $cfg['api_key']      ?? '',
            'sync_devices'      => $cfg['sync_devices']  ?? 1,
            'sync_sites'        => $cfg['sync_sites']    ?? 1,
            'sync_hosts'        => $cfg['sync_hosts']    ?? 1,
            'cron_interval'     => $cfg['cron_interval'] ?? 600,
        ]);
    }

    // --------------------------------------------------------------------------
    // Dashboard data helpers
    // --------------------------------------------------------------------------
    public static function getDashboardStats(): array
    {
        global $DB;

        // Devices per status
        $statusIter = $DB->request([
            'SELECT' => [new QueryExpression('`status`'), new QueryExpression('COUNT(*) AS cnt')],
            'FROM'   => 'glpi_plugin_unifiintegration_devices',
            'GROUPBY'=> ['status'],
        ]);
        $byStatus = [];
        foreach ($statusIter as $row) {
            $byStatus[$row['status'] ?: 'unknown'] = (int)$row['cnt'];
        }

        // Devices per firmware status
        $fwIter = $DB->request([
            'SELECT' => [new QueryExpression('`firmware_status`'), new QueryExpression('COUNT(*) AS cnt')],
            'FROM'   => 'glpi_plugin_unifiintegration_devices',
            'GROUPBY'=> ['firmware_status'],
        ]);
        $byFirmware = [];
        foreach ($fwIter as $row) {
            $byFirmware[$row['firmware_status'] ?: 'unknown'] = (int)$row['cnt'];
        }

        // Sites count
        $siteCnt = $DB->request(['FROM' => 'glpi_plugin_unifiintegration_sites', 'COUNT' => 'cnt'])->current()['cnt'] ?? 0;

        // All devices (for table)
        $devIter = $DB->request([
            'SELECT' => [
                'glpi_plugin_unifiintegration_devices.*',
                'glpi_plugin_unifiintegration_sites.name AS site_name',
            ],
            'FROM'   => 'glpi_plugin_unifiintegration_devices',
            'LEFT JOIN' => [
                'glpi_plugin_unifiintegration_sites' => [
                    'ON' => [
                        'glpi_plugin_unifiintegration_devices' => 'unifi_site_id',
                        'glpi_plugin_unifiintegration_sites'   => 'unifi_site_id',
                    ],
                ],
            ],
            'ORDER'  => ['is_console DESC', 'status ASC', 'name ASC'],
        ]);

        $devices = [];
        foreach ($devIter as $row) {
            $devices[] = $row;
        }

        // Recent sync logs
        $logIter = $DB->request([
            'FROM'  => 'glpi_plugin_unifiintegration_synclogs',
            'ORDER' => ['id DESC'],
            'LIMIT' => 10,
        ]);
        $logs = [];
        foreach ($logIter as $row) {
            $logs[] = $row;
        }

        return compact('byStatus', 'byFirmware', 'siteCnt', 'devices', 'logs');
    }
}
