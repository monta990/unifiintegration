<?php
/**
 * UniFi Integration — front/ajax.php
 *
 * AJAX endpoint — returns JSON.
 * Actions: test_connection, sync
 *
 * Author: Edwin Elias Alvarez
 * License: GPL v3+
 */

include('../../../inc/includes.php');

header('Content-Type: application/json; charset=utf-8');

Session::checkLoginUser();

$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        // ── Test connection ──────────────────────────────────────────
        case 'test_connection':
            Session::checkRight('plugin_unifiintegration_config', READ);
            Html::checkCSRF($_POST);

            $apiKey = trim($_POST['api_key'] ?? '');
            if (!$apiKey) {
                echo json_encode(['success' => false, 'error' => __('No API Key provided.', 'unifiintegration')]);
                exit;
            }

            $api    = new PluginUnifiintegrationApi($apiKey);
            $result = $api->testConnection();
            echo json_encode($result);
            break;

        // ── Manual sync ──────────────────────────────────────────────
        case 'sync':
            Session::checkRight('plugin_unifiintegration_config', UPDATE);

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
