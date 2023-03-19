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

/**
 * Plugin definitions
 */
class My
{
    /** @var string Required php version */
    public const PHP_MIN = '8.1';

    /**
     * This module id
     */
    public static function id(): string
    {
        return basename(dirname(__DIR__));
    }

    /**
     * This module name
     */
    public static function name(): string
    {
        return __((string) dcCore::app()->plugins->moduleInfo(self::id(), 'name'));
    }

    /**
     * Check php version
     */
    public static function phpCompliant(): bool
    {
        return version_compare(phpversion(), self::PHP_MIN, '>=');
    }
}
