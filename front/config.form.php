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
        'api_key'       => $_POST['api_key']       ?? '',
        'sync_devices'  => isset($_POST['sync_devices'])  ? 1 : 0,
        'sync_sites'    => isset($_POST['sync_sites'])    ? 1 : 0,
        'sync_hosts'    => isset($_POST['sync_hosts'])    ? 1 : 0,
        'cron_interval' => (int)($_POST['cron_interval'] ?? 600),
    ]);

    global $DB;
    $DB->update(
        CronTask::getTable(),
        ['frequency' => (int)($_POST['cron_interval'] ?? 600), 'mode' => CronTask::MODE_EXTERNAL],
        ['itemtype' => 'PluginUnifiintegrationSync', 'name' => 'syncUnifi']
    );

    Session::addMessageAfterRedirect(__('Configuration saved.', 'unifiintegration'), true, INFO);
    Html::redirect('/plugins/unifiintegration/front/config.form.php');
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
