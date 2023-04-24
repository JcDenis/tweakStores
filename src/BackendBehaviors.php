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
use dcModuleDefine;
use dcModules;
use dcPage;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\Html\Form\{
    Hidden,
    Label,
    Para,
    Password,
    Select,
    Textarea
};
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Html\XmlTag;
use Dotclear\Helper\Text;
use DOMDocument;
use Exception;

class BackendBehaviors
{
    /** @var array List of notice messages */
    private static $notice = [];

    /** @var array List of failed messages */
    private static $failed = [];

    public static function packmanBeforeCreatePackage(array $module): void
    {
        if (is_null(dcCore::app()->blog) || !dcCore::app()->blog->settings->get(My::id())->get('packman')) {
            return;
        }

        // move from array to dcModuleDefine object
        $modules = $module['type'] == 'theme' ? dcCore::app()->themes : dcCore::app()->plugins;
        $define  = $modules->getDefine($module['id']);

        self::writeXML($define, dcCore::app()->blog->settings->get(My::id())->get('file_pattern'));
    }

    public static function modulesToolsHeaders(bool $is_plugin): string
    {
        if (is_null(dcCore::app()->auth) || is_null(dcCore::app()->auth->user_prefs)) {
            return '';
        }

        return
            dcPage::jsJson('ts_copied', ['alert' => __('Copied to clipboard')]) .
            dcPage::jsModuleLoad(My::id() . '/js/backend.js') .
            (
                !dcCore::app()->auth->user_prefs->get('interface')->get('colorsyntax') ? '' :
                dcPage::jsLoadCodeMirror(dcCore::app()->auth->user_prefs->get('interface')->get('colorsyntax_theme')) .
                dcPage::jsModuleLoad(My::id() . '/js/cms.js')
            );
    }

    public static function pluginsToolsTabsV2(): void
    {
        self::modulesToolsTabs(dcCore::app()->plugins, explode(',', DC_DISTRIB_PLUGINS), (string) dcCore::app()->adminurl?->get('admin.plugins'));
    }

    public static function themesToolsTabsV2(): void
    {
        self::modulesToolsTabs(dcCore::app()->themes, explode(',', DC_DISTRIB_THEMES), (string) dcCore::app()->adminurl?->get('admin.blog.theme'));
    }

    private static function modulesToolsTabs(dcModules $modules, array $excludes, string $page_url): void
    {
        if (is_null(dcCore::app()->adminurl) || is_null(dcCore::app()->auth) || is_null(dcCore::app()->auth->user_prefs)) {
            return;
        }

        $page_url .= '#' . My::id();
        $user_ui_colorsyntax       = dcCore::app()->auth->user_prefs->get('interface')->get('colorsyntax');
        $user_ui_colorsyntax_theme = dcCore::app()->auth->user_prefs->get('interface')->get('colorsyntax_theme');
        $file_pattern              = (new Settings())->file_pattern;

        $module = $modules->getDefine($_POST['ts_id'] ?? '-');
        $combo  = self::comboModules($modules, $excludes);
        $form   = (new Para())->class('field')->items([
            (new Label(__('Module to parse:')))->for('ts_id')->class('required'),
            (new Select('ts_id'))->default($module->isDefined() ? Html::escapeHTML($module->get('id')) : '-')->items($combo),
        ])->render();

        # check dcstore repo
        $url = '';
        if (!empty($_POST['check_xml']) && $module->isDefined()) {
            if (empty($module->get('repository'))) {
                $url = __('This module has no repository set in its _define.php file.');
            } else {
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
                        $file_content = (string) curl_exec($ch);
                        curl_close($ch);
                    } else {
                        $file_content = (string) file_get_contents($url);
                    }
                } catch (Exception $e) {
                    $file_content = __('Failed to read third party repository');
                }
            }
        }

        # generate xml code
        if (!empty($_POST['build_xml']) && $module->isDefined()) {
            $xml_content = self::generateXML($module, $file_pattern);
        }

        # write dcstore.xml file
        if (!empty($_POST['write_xml'])) {
            if (empty($_POST['your_pwd']) || !dcCore::app()->auth->checkPassword($_POST['your_pwd'])) {
                dcCore::app()->error->add(__('Password verification failed'));
            } else {
                $ret = self::writeXML($module, $file_pattern);
                if (!empty(self::$failed)) {
                    dcCore::app()->error->add(implode(' ', self::$failed));
                }
            }
        }
        echo
        '<div class="multi-part" id="' . My::id() . '" title="' . My::name() . '">' .
        '<h3>' . __('Tweak third-party repositories') . '</h3>';

        if (!empty($_POST['write_xml'])) {
            if (dcCore::app()->error->flag()) {
                echo dcCore::app()->error->toHTML();
            } else {
                echo '<p class="success">' . __('File successfully written') . '</p>';
            }
        }
        if (count($combo) < 2) {
            echo
            '<div class="info">' . __('There is no module to tweak') . '</div>' .
            '</div>';

            return;
        }

        echo
        '<form method="post" action="' . $page_url . '" id="checkxml" class="fieldset">' .
        '<h4>' . __('Check repository') . '</h4>' .
        '<p>' . __('This checks if dcstore.xml file is present on third party repository.') . '</p>' .
        $form .
        '<p><input type="submit" name="check_xml" value="' . __('Check') . '" />' .
        dcCore::app()->formNonce() . '</p>' .
        '</form>';

        if (!empty($url)) {
            echo
            '<div class="fieldset">' .
            '<h4>' . __('Repositiory contents') . '</h4>' .
            '<p>' . $url . '</p>' .
            (
                empty($file_content) ? '' :
                '<pre>' .
                    (new Textarea('file_xml', Html::escapeHTML(self::prettyXML($file_content))))
                    ->cols(165)
                    ->rows(14)
                    ->readonly(true)
                    ->class('maximal')
                    ->render() .
                '</pre>' .
                (
                    !$user_ui_colorsyntax ? '' :
                    dcPage::jsRunCodeMirror('editor', 'file_xml', 'dotclear', $user_ui_colorsyntax_theme)
                )
            ) .
            '</div>';
        }

        if (empty($file_pattern)) {
            echo sprintf(
                '<div class="fieldset"><h4>' . __('Generate xml code') . '</h4><p class="info"><a href="%s">%s</a></p></div>',
                dcCore::app()->adminurl->get('admin.plugins', ['module' => My::id(), 'conf' => 1, 'redir' => $page_url]),
                __('You must configure zip file pattern to complete xml code automatically.')
            );
        } else {
            echo
            '<form method="post" action="' . $page_url . '" id="buildxml" class="fieldset">' .
            '<h4>' . __('Generate xml code') . '</h4>' .
            '<p>' . __('This helps to generate content of dcstore.xml for seleted module.') . '</p>' .
            $form .
            '<p><input type="submit" name="build_xml" value="' . __('Generate') . '" />' .
            dcCore::app()->formNonce() . '</p>' .
            '</form>';
        }
        if (!empty($_POST['build_xml'])) {
            echo
            '<form method="post" action="' . $page_url . '" id="writexml" class="fieldset">' .
            '<h4>' . sprintf(__('Generated code for module: %s'), Html::escapeHTML($module->get('id'))) . '</h4>';

            if (!empty(self::$failed)) {
                echo '<p class="info">' . sprintf(__('Failed to parse XML code: %s'), implode(', ', self::$failed)) . '</p> ';
            }
            if (!empty(self::$notice)) {
                echo '<p class="info">' . sprintf(__('Code is not fully filled: %s'), implode(', ', self::$notice)) . '</p> ';
            }
            if (!empty($xml_content)) {
                if (empty(self::$failed) && empty(self::$notice)) {
                    echo '<p class="info">' . __('Code is complete') . '</p>';
                }
                echo
                '<pre>' .
                    (new Textarea('gen_xml', Html::escapeHTML(self::prettyXML($xml_content))))
                    ->cols(165)
                    ->rows(14)
                    ->readonly(true)
                    ->class('maximal')
                    ->render() .
                '</pre>' .
                (
                    !$user_ui_colorsyntax ? '' :
                    dcPage::jsRunCodeMirror('editor', 'gen_xml', 'dotclear', $user_ui_colorsyntax_theme)
                );

                if (empty(self::$failed)
                    && $module->get('root_writable')
                    && dcCore::app()->auth->isSuperAdmin()
                ) {
                    echo
                    (new Para())->class('field')->items([
                        (new Label(__('Your password:')))->for('your_pwd2')->class('required'),
                        (new Password(['your_pwd', 'your_pwd2']))->size(20)->maxlenght(255)->required(true)->placeholder(__('Password'))->autocomplete('current-password'),
                    ])->render() .
                    '<p><input type="submit" name="write_xml" value="' . __('Save to module directory') . '" /> ' .
                    '<a class="hidden-if-no-js button" href="#' . My::id() . '" id="ts_copy_button">' . __('Copy to clipboard') . '</a>' .
                    (new Hidden('ts_id', $_POST['ts_id']))->render() .
                    dcCore::app()->formNonce() . '</p>';
                }
                echo sprintf(
                    '<p class="info"><a href="%s">%s</a></p>',
                    dcCore::app()->adminurl->get('admin.plugins', ['module' => My::id(), 'conf' => 1, 'redir' => $page_url]),
                    __('You can edit zip file pattern from configuration page.')
                );
            }
            echo
            '</form>';
        }
        echo
        '</div>';
    }

    # create list of module for combo and remove official modules
    private static function comboModules(dcModules $modules, array $excludes): array
    {
        $combo = [__('Select a module') => '0'];
        foreach ($modules->getDefines() as $module) {
            if (in_array($module->get('id'), $excludes)) {
                continue;
            }
            $combo[$module->get('name') . ' ' . $module->get('version')] = $module->get('id');
        }

        return $combo;
    }

    private static function parseFilePattern(dcModuleDefine $module, string $file_pattern): string
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

    private static function generateXML(dcModuleDefine $module, string $file_pattern): string
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
        if (empty($module->get('name'))) {
            self::$failed[] = 'no module name set in _define.php';
        }
        $rsp->insertNode(new XmlTag('name', $module->get('name')));

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

    private static function writeXML(dcModuleDefine $module, string $file_pattern): bool
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
