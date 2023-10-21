<?php

declare(strict_types=1);

namespace Dotclear\Plugin\tweakStores;

use Dotclear\App;
use Dotclear\Module\MyPlugin;

/**
 * @brief       tweakStores My helper.
 * @ingroup     tweakStores
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class My extends MyPlugin
{
    public static function checkCustomContext(int $context): ?bool
    {
        return match ($context) {
            self::MODULE => App::auth()->isSuperAdmin(),
            default      => null,
        };
    }
}
