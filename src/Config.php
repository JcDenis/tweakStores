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
use dcNsProcess;
use dcPage;
use Dotclear\Helper\Html\Form\{
    Checkbox,
    Div,
    Fieldset,
    Input,
    Label,
    Legend,
    Note,
    Para
};

/* php ns */
use Exception;

class Config extends dcNsProcess
{
    public static function init(): bool
    {
        static::$init = My::phpCompliant()
            && defined('DC_CONTEXT_ADMIN')
            && defined('DC_CONTEXT_MODULE')
            && dcCore::app()->auth->isSuperAdmin();

        return static::$init;
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        if (empty($_POST['save'])) {
            return true;
        }

        $s = new Settings();

        try {
            foreach ($s->listSettings() as $key) {
                $s->writeSetting($key, $_POST['ts_' . $key] ?? '');
            }

            dcPage::addSuccessNotice(
                __('Configuration successfully updated')
            );
            dcCore::app()->adminurl->redirect(
                'admin.plugins',
                ['module' => My::id(), 'conf' => 1, 'redir' => dcCore::app()->admin->__get('list')->getRedir()]
            );
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }

        return true;
    }

    public static function render(): void
    {
        if (!static::$init) {
            return;
        }

        $s = new Settings();

        echo (new Div())->items([
            (new Fieldset())->class('fieldset')->legend(new Legend(__('Interface')))->fields([
                // s_active
                (new Para())->items([
                    (new Checkbox('ts_active', $s->active))->value(1),
                    (new Label(__('Enable plugin'), Label::OUTSIDE_LABEL_AFTER))->for('ts_active')->class('classic'),
                ]),
                (new Note())->text(__('If enabled, new tab "Tweak stores" allows your to perfom actions relative to third-party repositories.'))->class('form-note'),
                // s_file_pattern
                (new Para())->items([
                    (new Label(__('Predictable URL to zip file on the external repository')))->for('ts_file_pattern'),
                    (new Input('ts_file_pattern'))->size(65)->maxlenght(255)->value($s->file_pattern),
                ]),
                (new Note())->text(__('You can use widcard like %author%, %type%, %id%, %version%.'))->class('form-note'),
                (new Note())->text(__('For example on github https://github.com/MyGitName/%id%/releases/download/v%version%/%type%-%id%.zip'))->class('form-note'),
                (new Note())->text(__('Note: on github, you must create a release and join to it the module zip file.'))->class('form-note'),
            ]),
            (new Fieldset())->class('fieldset')->legend(new Legend(__('Behaviors')))->fields([
                // s_packman
                (new Para())->items([
                    (new Checkbox('ts_packman', $s->packman))->value(1),
                    (new Label(__('Enable packman behaviors'), Label::OUTSIDE_LABEL_AFTER))->for('ts_packman')->class('classic'),
                ]),
                (new Note())->text(__('If enabled, plugin pacKman will (re)generate on the fly dcstore.xml file at root directory of the module.'))->class('form-note'),
            ]),
        ])->render();
    }
}
