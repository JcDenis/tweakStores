<?php

declare(strict_types=1);

namespace Dotclear\Plugin\tweakStores;

use Dotclear\App;
use Dotclear\Core\Process;

/**
 * @brief       tweakStores backend class.
 * @ingroup     tweakStores
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
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

        App::behavior()->addBehaviors([
            // addd some js
            'pluginsToolsHeadersV2' => BackendBehaviors::modulesToolsHeaders(...),
            'themesToolsHeadersV2'  => BackendBehaviors::modulesToolsHeaders(...),
            // admin modules page tab
            'pluginsToolsTabsV2' => BackendBehaviors::pluginsToolsTabsV2(...),
            'themesToolsTabsV2'  => BackendBehaviors::themesToolsTabsV2(...),
            // add to plugin pacKman
            'packmanBeforeCreatePackage' => BackendBehaviors::packmanBeforeCreatePackage(...),
        ]);

        return true;
    }
}
