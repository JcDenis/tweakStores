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

$prepend = implode('\\', ['Dotclear', 'Plugin', basename(__DIR__), 'Prepend']);
if (!class_exists($prepend)) {
    require implode(DIRECTORY_SEPARATOR, [__DIR__, 'inc', 'Prepend.php']);

    if ($prepend::init()) {
        $prepend::process();
    }
}
