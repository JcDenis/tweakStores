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

class Settings
{
    // Enable plugin pacKman behavior
    public readonly bool $packman;

    // Predictable dcstore url
    public readonly string $file_pattern;

    /**
     * Constructor set up plugin settings
     */
    public function __construct()
    {
        $this->packman      = (bool) (My::settings()->get('packman') ?? false);
        $this->file_pattern = (string) (My::settings()->get('file_pattern') ?? '');
    }

    /**
     * Overwrite a plugin settings (in db)
     *
     * @param   string  $key    The setting ID
     * @param   mixed   $value  The setting value
     *
     * @return  bool True on success
     */
    public function set(string $key, mixed $value): bool
    {
        if (property_exists($this, $key) && settype($value, gettype($this->{$key})) === true) {
            My::settings()->drop($key);
            My::settings()->put($key, $value, gettype($this->{$key}), '', true, true);

            return true;
        }

        return false;
    }

    /**
     * List defined settings keys
     *
     * @return  array   The settings keys
     */
    public function dump(): array
    {
        return get_object_vars($this);
    }
}
