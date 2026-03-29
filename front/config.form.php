<?php
/**
 * UniFi Integration — front/config.form.php
 *
 * Author: Edwin Elias Alvarez
 * License: GPL v3+
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_unifiintegration_config', READ);

// Handle POST save
if (isset($_POST['save_config'])) {
    Session::checkRight('plugin_unifiintegration_config', UPDATE);
    Html::checkCSRF($_POST);

    PluginUnifiintegrationConfig::saveConfig([
        'api_key'       => $_POST['api_key']       ?? '',
        'sync_devices'  => isset($_POST['sync_devices'])  ? 1 : 0,
        'sync_sites'    => isset($_POST['sync_sites'])    ? 1 : 0,
        'sync_hosts'    => isset($_POST['sync_hosts'])    ? 1 : 0,
        'cron_interval' => (int)($_POST['cron_interval'] ?? 600),
    ]);

    // update cron task frequency
    CronTask::register(
        'PluginUnifiintegrationSync',
        'sync',
        (int)($_POST['cron_interval'] ?? 600),
        ['state' => CronTask::STATE_WAITING]
    );

    Session::addMessageAfterRedirect(__('Configuration saved.', 'unifiintegration'), true, INFO);
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/unifiintegration/front/config.form.php');
}

Html::header(
    __('UniFi Integration — Configuration', 'unifiintegration'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'unifiintegration'
);

$config = new PluginUnifiintegrationConfig();
$config->showConfigForm();

Html::footer();
