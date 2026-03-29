<?php
/**
 * UniFi Integration — front/ajax.php
 * Author: Edwin Elias Alvarez
 * License: GPL v3+
 */
global $CFG_GLPI;

header('Content-Type: application/json; charset=utf-8');

Session::checkLoginUser();

$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        case 'test_connection':
            Session::checkRight('config', READ);
            $apiKey = trim($_POST['api_key'] ?? '');
            $ts = date('Y-m-d H:i:s');

            if (!$apiKey) {
                // Try using saved key
                $cfg = PluginUnifiintegrationConfig::getConfig();
                $apiKey = $cfg['api_key'] ?? '';
            }

            if (!$apiKey) {
                PluginUnifiintegrationUtils::log("[{$ts}] Test connection — no API Key");
                echo json_encode(['success' => false, 'error' => __('No API Key provided.', 'unifiintegration')]);
                exit;
            }

            PluginUnifiintegrationUtils::log("[{$ts}] Test connection — testing api.ui.com");
            $api    = new PluginUnifiintegrationApi($apiKey);
            $result = $api->testConnection();

            if ($result['success']) {
                PluginUnifiintegrationUtils::log("[{$ts}] Test connection OK — {$result['hosts']} host(s)");
            } else {
                PluginUnifiintegrationUtils::log("[{$ts}] Test connection FAILED — " . ($result['error'] ?? ''));
            }
            echo json_encode($result);
            break;

        case 'sync':
            Session::checkRight('config', UPDATE);
            $sync   = new PluginUnifiintegrationSync();
            $result = $sync->runManual();
            echo json_encode($result);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    PluginUnifiintegrationUtils::log('[' . date('Y-m-d H:i:s') . '] AJAX error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
