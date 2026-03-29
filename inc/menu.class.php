<?php
/**
 * UniFi Integration — inc/menu.class.php
 * Author: Edwin Elias Alvarez
 * License: GPL v3+
 */

class PluginUnifiintegrationMenu extends CommonGLPI
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0): string
    {
        return __('UniFi Integration', 'unifiintegration');
    }

    public static function getMenuContent(): array
    {
        $menu = [];

        if (Session::haveRight('config', READ)) {
            $menu['title'] = __('UniFi Integration', 'unifiintegration');
            $menu['page']  = '/plugins/unifiintegration/front/dashboard.php';
            $menu['icon']  = 'ti ti-wifi';

            $menu['options']['dashboard']['title'] = __('Dashboard', 'unifiintegration');
            $menu['options']['dashboard']['page']  = '/plugins/unifiintegration/front/dashboard.php';
            $menu['options']['dashboard']['icon']  = 'ti ti-layout-dashboard';

            $menu['options']['config']['title'] = __('Configuration', 'unifiintegration');
            $menu['options']['config']['page']  = '/plugins/unifiintegration/front/config.form.php';
            $menu['options']['config']['icon']  = 'ti ti-settings';
        }

        return $menu;
    }

    public static function getMenuName(): string
    {
        return __('UniFi Integration', 'unifiintegration');
    }

    public static function displayMenuContent(): bool
    {
        return true;
    }
}
