<?php
/**
 * UniFi Integration — front/config.form.php
 * Author: Edwin Elias Alvarez
 * License: GPL v3+
 */
global $CFG_GLPI;

Session::checkLoginUser();
Session::checkRight('config', UPDATE);

if (isset($_POST['save_config'])) {
    // GLPI 11: CSRF validated automatically by Symfony CheckCsrfListener
    PluginUnifiintegrationConfig::saveConfig([
        'api_key'          => $_POST['api_key']          ?? '',
        'sync_devices'     => isset($_POST['sync_devices'])  ? 1 : 0,
        'sync_sites'       => isset($_POST['sync_sites'])    ? 1 : 0,
        'sync_hosts'       => isset($_POST['sync_hosts'])    ? 1 : 0,
        'cron_interval'    => (int)($_POST['cron_interval']    ?? 600),
        'refresh_interval' => max(60, (int)($_POST['refresh_interval'] ?? 600)),
        'debug_logging'    => isset($_POST['debug_logging']) ? 1 : 0,
    ]);

    global $DB;
    $DB->update(
        CronTask::getTable(),
        ['frequency' => (int)($_POST['cron_interval'] ?? 600), 'mode' => CronTask::MODE_EXTERNAL],
        ['itemtype' => 'PluginUnifiintegrationSync', 'name' => 'syncUnifi']
    );

    unset($_SESSION['_unifi_debug']);
    Session::addMessageAfterRedirect(__('Configuration saved.', 'unifiintegration'), true, INFO);
    Html::back();
}

Html::header(
    __('UniFi Configuration', 'unifiintegration'),
    '',
    'config',
    'PluginUnifiintegrationConfig'
);

$config = new PluginUnifiintegrationConfig();
$config->showConfigForm();

Html::footer();
