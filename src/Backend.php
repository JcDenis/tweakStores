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
use html;

/* php ns */
use Exception;

class Admin
{
    private static $pid    = '';
    protected static $init = false;

    public static function init(): bool
    {
        if (defined('DC_CONTEXT_ADMIN')
         && dcCore::app()->auth->isSuperAdmin()
         && dcCore::app()->blog->settings->get(basename(__NAMESPACE__))->get('active')
        ) {
            dcCore::app()->auth->user_prefs->addWorkspace('interface');
            self::$pid  = basename(dirname(__DIR__));
            self::$init = true;
        }

        return self::$init;
    }

    public static function process(): ?bool
    {
        if (!self::$init) {
            return false;
        }

        if (dcCore::app()->blog->settings->get(self::$pid)->get('packman')) {
            // create dcstore.xml file on the fly when plugin packman pack a module
            dcCore::app()->addBehavior('packmanBeforeCreatePackage', function (array $module): void {
                Core::writeXML($module['id'], $module, dcCore::app()->blog->settings->get(self::$pid)->get('file_pattern'));
            });
        }

        dcCore::app()->addBehaviors([
            // addd some js
            'pluginsToolsHeadersV2' => [self::class, 'modulesToolsHeaders'],
            'themesToolsHeadersV2'  => [self::class, 'modulesToolsHeaders'],
            // admin plugins page tab
            'pluginsToolsTabsV2'    => function (): void {
                self::modulesToolsTabs(dcCore::app()->plugins->getModules(), explode(',', DC_DISTRIB_PLUGINS), dcCore::app()->adminurl->get('admin.plugins'));
            },
            // admin themes page tab
            'themesToolsTabsV2'     => function (): void {
                self::modulesToolsTabs(dcCore::app()->themes->getModules(), explode(',', DC_DISTRIB_THEMES), dcCore::app()->adminurl->get('admin.blog.theme'));
            },
        ]);

        return true;
    }

    public static function modulesToolsHeaders(bool $is_plugin): string
    {
        return
            dcPage::jsJson('ts_copied', ['alert' => __('Copied to clipboard')]) .
            dcPage::jsModuleLoad(self::$pid . '/js/admin.js') .
            (
                !dcCore::app()->auth->user_prefs->interface->colorsyntax ? '' :
                dcPage::jsLoadCodeMirror(dcCore::app()->auth->user_prefs->interface->colorsyntax_theme) .
                dcPage::jsModuleLoad(self::$pid . '/js/cms.js')
            );
    }

    protected static function modulesToolsTabs(array $modules, array $excludes, string $page_url): void
    {
        $page_url .= '#' . self::$pid;
        $user_ui_colorsyntax       = dcCore::app()->auth->user_prefs->interface->colorsyntax;
        $user_ui_colorsyntax_theme = dcCore::app()->auth->user_prefs->interface->colorsyntax_theme;
        $combo                     = self::comboModules($modules, $excludes);
        $file_pattern              = dcCore::app()->blog->settings->get(self::$pid)->get('file_pattern');

        # check dcstore repo
        $url = '';
        if (!empty($_POST['checkxml_id']) && in_array($_POST['checkxml_id'], $combo)) {
            if (empty($modules[$_POST['checkxml_id']]['repository'])) {
                $url = __('This module has no repository set in its _define.php file.');
            } else {
                try {
                    $url = $modules[$_POST['checkxml_id']]['repository'];
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
                        $file_content = curl_exec($ch);
                        curl_close($ch);
                    } else {
                        $file_content = file_get_contents($url);
                    }
                } catch (Exception $e) {
                    $file_content = __('Failed to read third party repository');
                }
            }
        }

        # generate xml code
        if (!empty($_POST['buildxml_id']) && in_array($_POST['buildxml_id'], $combo)) {
            $xml_content = Core::generateXML($_POST['buildxml_id'], $modules[$_POST['buildxml_id']], $file_pattern);
        }

        # write dcstore.xml file
        if (!empty($_POST['write_xml'])) {
            if (empty($_POST['your_pwd']) || !dcCore::app()->auth->checkPassword($_POST['your_pwd'])) {
                dcCore::app()->error->add(__('Password verification failed'));
            } else {
                $ret = Core::writeXML($_POST['buildxml_id'], $modules[$_POST['buildxml_id']], $file_pattern);
                if (!empty(Core::$failed)) {
                    dcCore::app()->error->add(implode(' ', Core::$failed));
                }
            }
        }
        echo
        '<div class="multi-part" id="' . self::$pid . '" title="' . dcCore::app()->plugins->moduleInfo(self::$pid, 'name') . '">' .
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
        '<p class="field"><label for="buildxml_id" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Module to parse:') . '</label> ' .
        form::combo('checkxml_id', $combo, empty($_POST['checkxml_id']) ? '-' : html::escapeHTML($_POST['checkxml_id'])) .
        '</p>' .
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
                '<pre>' . form::textArea('file_xml', 165, 14, [
                    'default'    => html::escapeHTML(Core::prettyXML($file_content)),
                    'class'      => 'maximal',
                    'extra_html' => 'readonly="true"',
                ]) . '</pre>' .
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
                dcCore::app()->adminurl->get('admin.plugins', ['module' => self::$pid, 'conf' => 1, 'redir' => $page_url]),
                __('You must configure zip file pattern to complete xml code automatically.')
            );
        } else {
            echo
            '<form method="post" action="' . $page_url . '" id="buildxml" class="fieldset">' .
            '<h4>' . __('Generate xml code') . '</h4>' .
            '<p>' . __('This helps to generate content of dcstore.xml for seleted module.') . '</p>' .
            '<p class="field"><label for="buildxml_id" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Module to parse:') . '</label> ' .
            form::combo('buildxml_id', $combo, empty($_POST['buildxml_id']) ? '-' : html::escapeHTML($_POST['buildxml_id'])) .
            '</p>' .
            '<p><input type="submit" name="build_xml" value="' . __('Generate') . '" />' .
            dcCore::app()->formNonce() . '</p>' .
            '</form>';
        }
        if (!empty($_POST['buildxml_id'])) {
            echo
            '<form method="post" action="' . $page_url . '" id="writexml" class="fieldset">' .
            '<h4>' . sprintf(__('Generated code for module: %s'), html::escapeHTML($_POST['buildxml_id'])) . '</h4>';

            if (!empty(Core::$failed)) {
                echo '<p class="info">' . sprintf(__('Failed to parse XML code: %s'), implode(', ', Core::$failed)) . '</p> ';
            }
            if (!empty(Core::$notice)) {
                echo '<p class="info">' . sprintf(__('Code is not fully filled: %s'), implode(', ', Core::$notice)) . '</p> ';
            }
            if (!empty($xml_content)) {
                if (empty(Core::$failed) && empty(Core::$notice)) {
                    echo '<p class="info">' . __('Code is complete') . '</p>';
                }
                echo
                '<pre>' . form::textArea('gen_xml', 165, 14, [
                    'default'    => html::escapeHTML(Core::prettyXML($xml_content)),
                    'class'      => 'maximal',
                    'extra_html' => 'readonly="true"',
                ]) . '</pre>' .
                (
                    !$user_ui_colorsyntax ? '' :
                    dcPage::jsRunCodeMirror('editor', 'gen_xml', 'dotclear', $user_ui_colorsyntax_theme)
                );

                if (empty(Core::$failed)
                    && $modules[$_POST['buildxml_id']]['root_writable']
                    && dcCore::app()->auth->isSuperAdmin()
                ) {
                    echo
                    '<p class="field"><label for="your_pwd2" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label> ' .
                    form::password(
                        ['your_pwd', 'your_pwd2'],
                        20,
                        255,
                        [
                            'extra_html'   => 'required placeholder="' . __('Password') . '"',
                            'autocomplete' => 'current-password',
                        ]
                    ) . '</p>' .
                    '<p><input type="submit" name="write_xml" value="' . __('Save to module directory') . '" /> ' .
                    '<a class="hidden-if-no-js button" href="#' . self::$pid . '" id="ts_copy_button">' . __('Copy to clipboard') . '</a>' .
                    form::hidden('buildxml_id', $_POST['buildxml_id']) .
                    dcCore::app()->formNonce() . '</p>';
                }
                echo sprintf(
                    '<p class="info"><a href="%s">%s</a></p>',
                    dcCore::app()->adminurl->get('admin.plugins', ['module' => self::$pid, 'conf' => 1, 'redir' => $page_url]),
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
    protected static function comboModules(array $modules, array $excludes): array
    {
        $combo = [__('Select a module') => '0'];
        foreach ($modules as $id => $module) {
            if (in_array($id, $excludes)) {
                continue;
            }
            $combo[$module['name'] . ' ' . $module['version']] = $id;
        }

        return $combo;
    }
}
