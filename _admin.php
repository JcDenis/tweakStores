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
if (!defined('DC_CONTEXT_ADMIN')) {
    return null;
}

# only superadmin
if (!$core->auth->isSuperAdmin()) {
    return null;
}

# only if activated
$core->blog->settings->addNamespace('tweakStores');
if (!$core->blog->settings->tweakStores->active) {
    return null;
}

# admin behaviors
if ($core->blog->settings->tweakStores->packman) {
    $core->addBehavior('packmanBeforeCreatePackage', ['tweakStoresBehaviors', 'packmanBeforeCreatePackage']);
}
$core->addBehavior('pluginsToolsHeaders', ['tweakStoresBehaviors', 'modulesToolsHeaders']);
$core->addBehavior('themesToolsHeaders', ['tweakStoresBehaviors', 'modulesToolsHeaders']);
$core->addBehavior('pluginsToolsTabs', ['tweakStoresBehaviors', 'pluginsToolsTabs']);
$core->addBehavior('themesToolsTabs', ['tweakStoresBehaviors', 'themesToolsTabs']);

class tweakStoresBehaviors
{
    # create dcstore.xml file on the fly when pack a module
    public static function packmanBeforeCreatePackage(dcCore $core, $module)
    {
        tweakStores::writeXML($module['id'], $module, $core->blog->settings->tweakStores->file_pattern);
    }

    # addd some js
    public static function modulesToolsHeaders(dcCore $core, $plugin)
    {
        return
            dcPage::jsVars(['dotclear.ts_copied' => __('Copied to clipboard')]) .
            dcPage::jsLoad(dcPage::getPF('tweakStores/js/admin.js'));
    }

    # admin plugins page tab
    public static function pluginsToolsTabs(dcCore $core)
    {
        self::modulesToolsTabs($core, $core->plugins->getModules(), explode(',', DC_DISTRIB_PLUGINS), $core->adminurl->get('admin.plugins') . '#tweakStores');
    }

    # admin themes page tab
    public static function themesToolsTabs(dcCore $core)
    {
        self::modulesToolsTabs($core, $core->themes->getModules(), explode(',', DC_DISTRIB_THEMES), $core->adminurl->get('admin.blog.theme') . '#tweakStores');
    }

    # generic page tab
    protected static function modulesToolsTabs(dcCore $core, $modules, $excludes, $page_url)
    {
        $combo = self::comboModules($modules, $excludes);

        # zip file url pattern
        $file_pattern = $core->blog->settings->tweakStores->file_pattern;

        # check dcstore repo
        $file_content = '';
        if (!empty($_POST['checkxml_id']) && in_array($_POST['checkxml_id'], $combo)) {
            if (empty($modules[$_POST['checkxml_id']]['repository'])) {
                $file_content = __('This module has no repository set in its _define.php file.');
            } else {
                try {
                    if (function_exists('curl_init')) {
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_URL, $modules[$_POST['checkxml_id']]['repository']);
                        curl_setopt($ch, CURLOPT_REFERER, $modules[$_POST['checkxml_id']]['repository']);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $file_content = curl_exec($ch);
                        curl_close($ch);
                    } else {
                        $file_content = file_get_contents($modules[$_POST['checkxml_id']]['repository']);
                    }
                } catch (Exception $e) {
                    $file_content = __('Failed to read third party repository');
                }
            }
        }

        # generate xml code
        if (!empty($_POST['buildxml_id']) && in_array($_POST['buildxml_id'], $combo)) {
            $xml_content = tweakStores::generateXML($_POST['buildxml_id'], $modules[$_POST['buildxml_id']], $file_pattern);
        }

        # write dcstore.xml file
        if (!empty($_POST['write_xml'])) {
            if (empty($_POST['your_pwd']) || !$core->auth->checkPassword($_POST['your_pwd'])) {
                $core->error->add(__('Password verification failed'));
            } else {
                $ret = tweakStores::writeXML($_POST['buildxml_id'], $modules[$_POST['buildxml_id']], $file_pattern);
                if (!empty(tweakStores::$failed)) {
                    $core->error->add(implode(' ', tweakStores::$failed));
                }
            }
        }
        echo
        '<div class="multi-part" id="tweakStores" title="' . __('Tweak stores') . '">' .
        '<h3>' . __('Tweak third-party repositories') . '</h3>';

        if (!empty($_POST['write_xml'])) {
            if ($core->error->flag()) {
                echo '<p class="error">' . implode(' ', $core->error->getErrors()) . '</p>';
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
        $core->formNonce() . '</p>' .
        '</form>';

        if (!empty($file_content)) {
            echo
            '<div class="fieldset">' .
            '<h4>' . __('Repositiory contents') . '</h4>' .
            '<pre>' . form::textArea('file_xml', 165, 14, [
                'default'    => html::escapeHTML(str_replace('><', ">\n<", $file_content)),
                'class'      => 'maximal',
                'extra_html' => 'readonly="true"'
            ]) . '</pre>' .
            '</div>';
        }

        if (empty($file_pattern)) {
            echo sprintf(
                '<div class="fieldset"><h4>' . __('Generate xml code') . '</h4><p class="info"><a href="%s">%s</a></p></div>',
                $core->adminurl->get('admin.plugins', ['module' => 'tweakStores', 'conf' => 1, 'redir' => $page_url]),
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
            $core->formNonce() . '</p>' .
            '</form>';
        }
        if (!empty($_POST['buildxml_id'])) {
            echo
            '<form method="post" action="' . $page_url . '" id="writexml" class="fieldset">' .
            '<h4>' . sprintf(__('Generated code for module: %s'), html::escapeHTML($_POST['buildxml_id'])) . '</h4>';

            if (!empty(tweakStores::$failed)) {
                echo '<p class="info">' . sprintf(__('Failed to parse XML code: %s'), implode(', ', tweakStores::$failed)) . '</p> ';
            }
            if (!empty(tweakStores::$notice)) {
                echo '<p class="info">' . sprintf(__('Code is not fully filled: %s'), implode(', ', tweakStores::$notice)) . '</p> ';
            }
            if (!empty($xml_content)) {
                if (empty(tweakStores::$failed) && empty(tweakStores::$notice)) {
                    echo '<p class="info">' . __('Code is complete') . '</p>';
                }
                echo
                '<pre>' . form::textArea('gen_xml', 165, 14, [
                    'default'    => html::escapeHTML(str_replace('><', ">\n<", $xml_content)),
                    'class'      => 'maximal',
                    'extra_html' => 'readonly="true"'
                ]) . '</pre>';

                if (empty(tweakStores::$failed)
                    && $modules[$_POST['buildxml_id']]['root_writable']
                    && $core->auth->isSuperAdmin()
                ) {
                    echo
                    '<p class="field"><label for="your_pwd2" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label> ' .
                    form::password(
                        ['your_pwd', 'your_pwd2'],
                        20,
                        255,
                        [
                            'extra_html'   => 'required placeholder="' . __('Password') . '"',
                            'autocomplete' => 'current-password'
                        ]
                    ) . '</p>' .
                    '<p><input type="submit" name="write_xml" value="' . __('Save to module directory') . '" /> ' .
                    '<a class="hidden-if-no-js button" href="#tweakStores" id="ts_copy_button">' . __('Copy to clipboard') . '</a>' .
                    form::hidden('buildxml_id', $_POST['buildxml_id']) .
                    $core->formNonce() . '</p>';
                }
                echo sprintf(
                    '<p class="info"><a href="%s">%s</a></p>',
                    $core->adminurl->get('admin.plugins', ['module' => 'tweakStores', 'conf' => 1, 'redir' => $page_url]),
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
    protected static function comboModules($modules, array $excludes)
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
