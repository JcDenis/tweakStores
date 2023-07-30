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
use Dotclear\Core\Process;

class Backend extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::BACKEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
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
