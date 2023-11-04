<?php

declare(strict_types=1);

namespace Dotclear\Plugin\tweakStores;

use Dotclear\App;
use Dotclear\Module\ModuleDefine;
use Dotclear\Interface\Module\ModulesInterface;
use Dotclear\Core\Backend\{
    Notices,
    Page
};
use Dotclear\Helper\File\Files;
use Dotclear\Helper\Html\Form\{
    Checkbox,
    Div,
    Fieldset,
    Hidden,
    Input,
    Label,
    Legend,
    Note,
    Para,
    Password,
    Select,
    Submit,
    Textarea
};
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Html\XmlTag;
use Dotclear\Helper\Text;
use DOMDocument;
use Exception;

/**
 * @brief       tweakStores backend behaviors class.
 * @ingroup     tweakStores
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class BackendBehaviors
{
    /**
     * List of notice messages.
     *
     * @var     array<int, string>  $notice
     */
    private static $notice = [];

    /**
     * List of failed messages.
     *
     * @var     array<int, string>  $failed
     */
    private static $failed = [];

    /**
     * Module settings.
     *
     * @var     Settings    $settings
     */
    private static $settings;

    /**
     * Get settings instance.
     *
     * @return  Settings    Settings helper instance
     */
    private static function settings(): Settings
    {
        if (!(self::$settings instanceof Settings)) {
            self::$settings = new Settings();
        }

        return self::$settings;
    }

    /**
     * Plugin pacKman behavior.
     *
     * Add dcstore.xml file on the fly on pacKman package creation.
     *
     * @param   array<string, string>   $module
     */
    public static function packmanBeforeCreatePackage(array $module): void
    {
        if (self::settings()->packman) {
            return;
        }

        // move from array to dcModuleDefine object
        $modules = $module['type'] == 'theme' ? App::themes() : App::plugins();
        $define  = $modules->getDefine($module['id']);

        self::writeXML($define, self::settings()->file_pattern);
    }

    /**
     * Add list headers and save settings.
     *
     * @param   bool    $is_theme   Is on themes list
     *
     * @return  string  HTML header code
     */
    public static function modulesToolsHeaders(bool $is_theme): string
    {
        //save settings (before page header sent)
        if (!empty($_POST['tweakstore_save'])) {
            try {
                foreach (self::settings()->dump() as $key => $value) {
                    if (!is_string($key)) {
                        continue;
                    }
                    self::settings()->set($key, $_POST['ts_' . $key] ?? $value);
                }

                Notices::addSuccessNotice(
                    __('Configuration successfully updated')
                );
                App::backend()->url()->redirect($is_theme ? 'admin.blog.theme' : 'admin.plugins', ['tab' => My::id()]);
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        return
            Page::jsJson('tweakstore_copied', ['alert' => __('Copied to clipboard')]) .
            My::jsLoad('backend') .
            (
                !App::auth()->prefs()->get('interface')->get('colorsyntax') ? '' :
                Page::jsLoadCodeMirror(App::auth()->prefs()->get('interface')->get('colorsyntax_theme')) .
                My::jsLoad('cms')
            );
    }

    /**
     * Plugins tab.
     */
    public static function pluginsToolsTabsV2(): void
    {
        self::modulesToolsTabs(App::plugins(), (string) App::backend()->url()->get('admin.plugins'));
    }

    /**
     * Themes tab.
     */
    public static function themesToolsTabsV2(): void
    {
        self::modulesToolsTabs(App::themes(), (string) App::backend()->url()->get('admin.blog.theme'));
    }

    /**
     * Modules tab.
     *
     * @param   ModulesInterface    $modules    The modules
     * @param   string              $page_url   The page URL
     */
    private static function modulesToolsTabs(ModulesInterface $modules, string $page_url): void
    {
        // settings
        $page_url .= '#' . My::id();
        $user_ui_colorsyntax       = App::auth()->prefs()->get('interface')->get('colorsyntax');
        $user_ui_colorsyntax_theme = App::auth()->prefs()->get('interface')->get('colorsyntax_theme');
        $file_pattern              = self::settings()->file_pattern;
        $local_content             = $distant_content = '';

        // load module
        $module = $modules->getDefine($_POST['tweakstore_id'] ?? '0');
        $combo  = self::comboModules($modules);

        // execute form actions
        $url = '';
        if (!empty($_POST['tweakstore_do']) && $module->isDefined()) {
            if (empty($module->get('repository'))) {
                $url = __('This module has no repository set in its _define.php file.');
            } else {
                // read distant module xml content
                try {
                    $url = $module->get('repository');
                    if (false === strpos($url, 'dcstore.xml')) {
                        $url .= '/dcstore.xml';
                    }
                    if (function_exists('curl_init')) {
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_REFERER, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $distant_content = (string) curl_exec($ch);
                        curl_close($ch);
                    } else {
                        $distant_content = (string) file_get_contents($url);
                    }
                } catch (Exception $e) {
                    $distant_content = __('Failed to read third party repository');
                }
            }

            // generate local module xml content
            $local_content = self::generateXML($module, self::settings()->file_pattern);

            // write dcstore.xml file
            if (!empty($_POST['tweakstore_write'])) {
                if (empty($_POST['your_pwd']) || !App::auth()->checkPassword($_POST['your_pwd'])) {
                    App::error()->add(__('Password verification failed'));
                } else {
                    self::writeXML($module, self::settings()->file_pattern);
                    if (!empty(self::$failed)) {
                        App::error()->add(implode(' ', self::$failed));
                    }
                }
            }
        }

        // display
        echo
        '<div class="multi-part" id="' . My::id() . '" title="' . My::name() . '">' .
        '<h3>' . __('Tweak third-party repositories') . '</h3>';

        // nothing to display
        if (count($combo) < 2) {
            echo
            '<p class="warning">' . __('There is no module to tweak') . '</p> ' .
            '</div>';

            return;
        }

        echo
        '<form method="post" action="' . $page_url . '" id="tweakstore_form">' .
        (new Para())->class('field')->items([
            (new Label(__('Module to parse:')))->for('tweakstore_id')->class('required'),
            (new Select('tweakstore_id'))->default($module->isDefined() ? Html::escapeHTML($module->getId()) : '0')->items($combo),
        ])->render();

        // distant content
        if (!empty($url)) {
            echo
            '<div class="fieldset">' .
            '<h4>' . __('Contents from distant repositiory') . '</h4>' .
            '<p>' . $url . '</p>' .
            (
                empty($distant_content) ? '' :
                '<pre>' .
                    (new Textarea('distant_content', Html::escapeHTML(self::prettyXML($distant_content))))
                    ->cols(165)
                    ->rows(14)
                    ->readonly(true)
                    ->class('maximal')
                    ->render() .
                '</pre>' .
                (
                    !$user_ui_colorsyntax ? '' :
                    Page::jsRunCodeMirror('editor', 'distant_content', 'dotclear', $user_ui_colorsyntax_theme)
                )
            ) .
            '</div>';
        }

        // local_content
        if (!empty($local_content) || !empty(self::$failed) || !empty(self::$notice)) {
            echo
            '<div class="fieldset">' .
            '<h4>' . __('Contents generated from local module definiton') . '</h4>';

            if (!empty(self::$failed)) {
                echo '<p class="warning">' . sprintf(__('Failed to parse XML code: %s'), implode(', ', self::$failed)) . '</p> ';
            }
            if (!empty(self::$notice)) {
                echo '<p class="warning">' . sprintf(__('Code is not fully filled: %s'), implode(', ', self::$notice)) . '</p> ';
            }
            if (empty(self::$failed) && empty(self::$notice)) {
                if (!empty($_POST['tweakstore_write'])) {
                    echo '<p class="info">' . __('File successfully writed') . '</p> ';
                }
                echo '<p class="info">' . __('Code is complete') . '</p> ';
            }

            echo
            '<pre>' .
                (new Textarea('local_content', Html::escapeHTML(self::prettyXML($local_content))))
                ->cols(165)
                ->rows(14)
                ->readonly(true)
                ->class('maximal')
                ->render() .
            '</pre>' .
            (
                !$user_ui_colorsyntax ? '' :
                Page::jsRunCodeMirror('editor', 'local_content', 'dotclear', $user_ui_colorsyntax_theme)
            );

            if ($module->get('root_writable')
                && App::auth()->isSuperAdmin()
            ) {
                echo
                (new Para())->class('field')->items([
                    (new Label(__('Your password:')))->for('tweakstore_pwd')->class('required'),
                    (new Password(['your_pwd', 'tweakstore_pwd']))->size(20)->maxlength(255)->required(true)->placeholder(__('Password'))->autocomplete('current-password'),
                ])->render() .
                '<p><input type="submit" name="tweakstore_write" id="tweakstore_write" value="' . __('Save to module directory') . '" /> ' .
                '<a class="hidden-if-no-js button" href="#' . My::id() . '" id="tweakstore_copy">' . __('Copy to clipboard') . '</a>' .
                App::nonce()->getFormNonce() . '</p>';
            }

            echo
            '</div>';
        }

        // submit form button (hide by js)
        echo
        (new Para())->items([
            (new Submit('tweakstore_submit'))->value(__('Check')),
            (new Hidden('tweakstore_do', '1')),
            App::nonce()->formNonce(),
        ])->render() .
        '</form>' .

        // settings
        '<form method="post" action="' . $page_url . '" id="tweakstore_setting">' .
        '<div class="fieldset"><h4>' . sprintf(__('%s configuration'), My::name()) . '</h4>' .
        (empty(self::settings()->file_pattern) ? '<p class="warning">' . __('You must configure zip file pattern to complete xml code automatically.') . '</p>' : '') .

        (new Div())->items([
            // s_file_pattern
            (new Para())->items([
                (new Label(__('Predictable URL to zip file on the external repository')))->for('ts_file_pattern'),
                (new Input('ts_file_pattern'))->size(65)->maxlength(255)->class('maximal')->value(self::settings()->file_pattern),
            ]),
            (new Note())->text(__('You can use widcard like %author%, %type%, %id%, %version%.'))->class('form-note'),
            (new Note())->text(__('For example on github https://github.com/MyGitName/%id%/releases/download/v%version%/%type%-%id%.zip'))->class('form-note'),
            (new Note())->text(__('Note: on github, you must create a release and join to it the module zip file.'))->class('form-note'),
            // s_packman
            (new Para())->items([
                (new Checkbox('ts_packman', self::settings()->packman))->value(1),
                (new Label(__('Enable packman behaviors'), Label::OUTSIDE_LABEL_AFTER))->for('ts_packman')->class('classic'),
            ]),
            (new Note())->text(__('If enabled, plugin pacKman will (re)generate on the fly dcstore.xml file at root directory of the module.'))->class('form-note'),
        ])->render() .

        (new Para())->items([
            (new Submit('tweakstore_save'))->value(__('Save')),
            App::nonce()->formNonce(),
        ])->render() .

        '</div>' .
        '</form>' .
        '</div>';
    }

    /**
     * Create list of module for combo and remove official modules.
     *
     * @return  array<string, string>
     */
    private static function comboModules(ModulesInterface $modules): array
    {
        $combo = [];
        foreach ($modules->getDefines() as $module) {
            if ($module->get('distributed')) {
                continue;
            }
            $combo[$module->get('name') . ' ' . $module->get('version')] = $module->get('id');
        }

        uasort($combo, fn ($a, $b) => strtolower($a) <=> strtolower($b));

        return array_merge([__('Select a module') => '0'], $combo);
    }

    /**
     * Parse dcstore content.
     *
     * @param   ModuleDefine    $module         The module
     * @param   string          $file_pattern   The file pattern
     *
     * @return  string  The parsed file content
     */
    private static function parseFilePattern(ModuleDefine $module, string $file_pattern): string
    {
        return Text::tidyURL(str_replace(
            [
                '%type%',
                '%id%',
                '%version%',
                '%author%',
            ],
            [
                $module->get('type'),
                $module->get('id'),
                $module->get('version'),
                $module->get('author'),
            ],
            $file_pattern
        ));
    }

    /**
     * Generate XML content from module.
     *
     * @param   ModuleDefine    $module         The module
     * @param   string          $file_pattern   The file pattern
     *
     * @return  string  The XML content
     */
    private static function generateXML(ModuleDefine $module, string $file_pattern): string
    {
        $rsp = new XmlTag('module');

        self::$notice = [];
        self::$failed = [];

        # id
        if (!$module->isDefined()) {
            self::$failed[] = 'unknow module';
        }
        $rsp->insertAttr('id', $module->get('id'));

        # name
        if (empty($module->get('label'))) {
            self::$failed[] = 'no module name set in _define.php';
        }
        $rsp->insertNode(new XmlTag('name', $module->get('label')));

        # version
        if (empty($module->get('version'))) {
            self::$failed[] = 'no module version set in _define.php';
        }
        $rsp->insertNode(new XmlTag('version', $module->get('version')));

        # author
        if (empty($module->get('author'))) {
            self::$failed[] = 'no module author set in _define.php';
        }
        $rsp->insertNode(new XmlTag('author', $module->get('author')));

        # desc
        if (empty($module->get('desc'))) {
            self::$failed[] = 'no module description set in _define.php';
        }
        $rsp->insertNode(new XmlTag('desc', $module->get('desc')));

        # repository
        if (empty($module->get('repository'))) {
            self::$failed[] = 'no repository set in _define.php';
        }

        # file
        $file_pattern = self::parseFilePattern($module, $file_pattern);
        if (empty($file_pattern)) {
            self::$failed[] = 'no zip file pattern set in Tweak Store configuration';
        }
        $rsp->insertNode(new XmlTag('file', $file_pattern));

        # da dc_min or requires core
        if (!empty($module->get('requires')) && is_array($module->get('requires'))) {
            foreach ($module->get('requires') as $req) {
                if (!is_array($req)) {
                    $req = [$req];
                }
                if ($req[0] == 'core') {
                    $module->set('dc_min', $req[1]);

                    break;
                }
            }
        }
        if (empty($module->get('dc_min'))) {
            self::$notice[] = 'no minimum dotclear version';
        } else {
            $rsp->insertNode(new XmlTag('da:dcmin', $module->get('dc_min')));
        }

        # details
        if (empty($module->get('details'))) {
            self::$notice[] = 'no details URL';
        } else {
            $rsp->insertNode(new XmlTag('da:details', $module->get('details')));
        }

        # section
        if (!empty($module->get('section'))) {
            $rsp->insertNode(new XmlTag('da:section', $module->get('section')));
        }

        # support
        if (empty($module->get('support'))) {
            self::$notice[] = 'no support URL';
        } else {
            $rsp->insertNode(new XmlTag('da:support', $module->get('support')));
        }

        $res = new XmlTag('modules', $rsp);
        $res->insertAttr('xmlns:da', 'http://dotaddict.org/da/');

        return self::prettyXML($res->toXML());
    }

    /**
     * Write XML content to dcstore file.
     *
     * @param   ModuleDefine    $module         The module
     * @param   string          $file_pattern   The file pattern
     *
     * @return  bool    True on success
     */
    private static function writeXML(ModuleDefine $module, string $file_pattern): bool
    {
        self::$failed = [];
        if (!$module->get('root_writable')) {
            return false;
        }
        $content = self::generateXML($module, $file_pattern);
        if (!empty(self::$failed)) {
            return false;
        }

        try {
            Files::putContent($module->get('root') . DIRECTORY_SEPARATOR . 'dcstore.xml', $content);
        } catch (Exception $e) {
            self::$failed[] = $e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * Arrange XML content.
     *
     * @param   string  $str    The content
     *
     * @return  string  The pretty content
     */
    private static function prettyXML(string $str): string
    {
        if (class_exists('DOMDocument')) {
            $dom                     = new DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput       = true;
            $dom->loadXML($str);

            return (string) $dom->saveXML();
        }

        return (string) str_replace('><', ">\n<", $str);
    }
}
