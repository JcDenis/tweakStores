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

class Settings
{
    // Enable this plugin
    public readonly bool $active;

    // Enable plugin pacKman behavior
    public readonly bool $packman;

    // Predictable dcstore url
    public readonly string $file_pattern;

    /**
     * Constructor set up plugin settings
     */
    public function __construct()
    {
        $s = dcCore::app()->blog->settings->get(My::id());

        $this->active       = (bool) ($s->get('active') ?? false);
        $this->packman      = (bool) ($s->get('packman') ?? false);
        $this->file_pattern = (string) ($s->get('file_pattern') ?? '');
    }

    public function getSetting(string $key): mixed
    {
        return $this->{$key} ?? null;
    }

    /**
     * Overwrite a plugin settings (in db)
     *
     * @param   string  $key    The setting ID
     * @param   mixed   $value  The setting value
     *
     * @return  bool True on success
     */
    public function writeSetting(string $key, mixed $value): bool
    {
        if (property_exists($this, $key) && settype($value, gettype($this->{$key})) === true) {
            dcCore::app()->blog->settings->get(My::id())->drop($key);
            dcCore::app()->blog->settings->get(My::id())->put($key, $value, gettype($this->{$key}), '', true, true);

            return true;
        }

        return false;
    }

    /**
     * List defined settings keys
     *
     * @return  array   The settings keys
     */
    public function listSettings(): array
    {
        return array_keys(get_class_vars(Settings::class));
    }
}
