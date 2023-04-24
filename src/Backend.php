<?php
/**
 * @brief tweakStores, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugin
 *
 * @author Jean-Christian Denis and Contributors
 *
 * @copyright Jean-Christian Denis
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\tweakStores;

use dcCore;
use dcNsProcess;

class Backend extends dcNsProcess
{
    public static function init(): bool
    {
        static::$init = defined('DC_CONTEXT_ADMIN')
            && !is_null(dcCore::app()->auth) && !is_null(dcCore::app()->blog)
            && My::phpCompliant()
            && dcCore::app()->auth->isSuperAdmin()
            && dcCore::app()->blog->settings->get(My::id())->get('active');

        return static::$init;
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        dcCore::app()->addBehaviors([
            // addd some js
            'pluginsToolsHeadersV2' => [BackendBehaviors::class, 'modulesToolsHeaders'],
            'themesToolsHeadersV2'  => [BackendBehaviors::class, 'modulesToolsHeaders'],
            // admin modules page tab
            'pluginsToolsTabsV2' => [BackendBehaviors::class, 'pluginsToolsTabsV2'],
            'themesToolsTabsV2'  => [BackendBehaviors::class, 'themesToolsTabsV2'],
            // add to plugin pacKman
            'packmanBeforeCreatePackage' => [BackendBehaviors::class, 'packmanBeforeCreatePackage'],
        ]);

        return true;
    }
}
