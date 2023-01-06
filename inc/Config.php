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

/* dotclear ns */
use dcCore;
use dcPage;

/* clearbricks ns */
use form;
use http;

/* php ns */
use Exception;

class Config
{
    protected static $init = false;

    public static function init(): bool
    {
        if (defined('DC_CONTEXT_ADMIN') && defined('DC_CONTEXT_MODULE')) {
            dcPage::checkSuper();
            self::$init = true;
        }

        return self::$init;
    }

    public static function process(): ?bool
    {
        if (!self::$init) {
            return false;
        }

        if (empty($_POST['save'])) {
            return null;
        }

        try {
            $s = dcCore::app()->blog->settings->get(basename(__NAMESPACE__));
            $s->put('active', !empty($_POST['s_active']));
            $s->put('packman', !empty($_POST['s_packman']));
            $s->put('file_pattern', $_POST['s_file_pattern']);

            dcPage::addSuccessNotice(
                __('Configuration successfully updated')
            );
            http::redirect(
                dcCore::app()->admin->__get('list')->getURL('module=' . basename(__NAMESPACE__) . '&conf=1&redir=' . dcCore::app()->admin->__get('list')->getRedir())
            );

            return true;
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }

        return null;
    }

    public static function render(): void
    {
        $s = dcCore::app()->blog->settings->get(basename(__NAMESPACE__));

        echo '
        <div class="fieldset">
        <h4>' . dcCore::app()->plugins->moduleInfo(basename(__NAMESPACE__), 'name') . '</h4>

        <p><label class="classic" for="s_active">' .
        form::checkbox('s_active', 1, (bool) $s->get('active')) . ' ' .
        __('Enable plugin') . '</label></p>
        <p class="form-note">' . __('If enabled, new tab "Tweak stores" allows your to perfom actions relative to third-party repositories.') . '</p>

        <p><label class="classic" for="s_packman">' .
        form::checkbox('s_packman', 1, (bool) $s->get('packman')) . ' ' .
        __('Enable packman behaviors') . '</label></p>
        <p class="form-note">' . __('If enabled, plugin pacKman will (re)generate on the fly dcstore.xml file at root directory of the module.') . '</p>

        <p><label class="classic" for="s_file_pattern">' . __('Predictable URL to zip file on the external repository') .
        form::field('s_file_pattern', 65, 255, (string) $s->get('file_pattern'), 'maximal') . ' 
        </label></p>
        <p class="form-note">' .
        __('You can use widcard like %author%, %type%, %id%, %version%.') . '<br /> ' .
        __('For example on github https://github.com/MyGitName/%id%/releases/download/v%version%/%type%-%id%.zip') . '<br />' .
        __('Note: on github, you must create a release and join to it the module zip file.') . '
        </p>

        </div>';
    }
}
