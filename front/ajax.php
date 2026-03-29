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
            if (!$apiKey) {
                echo json_encode(['success' => false, 'error' => __('No API Key provided.', 'unifiintegration')]);
                exit;
            }
            $api    = new PluginUnifiintegrationApi($apiKey);
            $result = $api->testConnection();
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
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
