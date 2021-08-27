<?php
# -- BEGIN LICENSE BLOCK ----------------------------------
#
# This file is part of tweakStores, a plugin for Dotclear 2.
# 
# Copyright (c) 2009-2021 Jean-Christian Denis and contributors
# 
# Licensed under the GPL version 2.0 license.
# A copy of this license is available in LICENSE file or at
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK ------------------------------------

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
$core->addBehavior('pluginsToolsTabs', ['tweakStoresBehaviors', 'pluginsToolsTabs']);
$core->addBehavior('themesToolsTabs', ['tweakStoresBehaviors', 'themesToolsTabs']);

class tweakStoresBehaviors
{
    # create dcstore.xml file on the fly when pack a module
    public static function packmanBeforeCreatePackage(dcCore $core, $module)
    {
        tweakStores::writeXML($module['id'], $module, $core->blog->settings->tweakStores->file_pattern);
    }

    # admin plugins page tab
    public static function pluginsToolsTabs(dcCore $core)
    {
        self::modulesToolsTabs($core, $core->plugins->getModules(), explode(',', DC_DISTRIB_PLUGINS), $core->adminurl->get('admin.plugins').'#tweakStores');
    }

    # admin themes page tab
    public static function themesToolsTabs(dcCore $core)
    {
        self::modulesToolsTabs($core, $core->themes->getModules(), explode(',', DC_DISTRIB_THEMES), $core->adminurl->get('admin.blog.theme').'#tweakStores');
    }

    # generic page tab
    protected static function modulesToolsTabs(dcCore $core, $modules, $excludes, $page_url)
    {
        $file_pattern = $core->blog->settings->tweakStores->file_pattern;
        $modules = new ArrayObject($modules);
        $combo = self::comboModules($modules, $excludes);

        # generate xml code
        if (!empty($_POST['buildxml_id']) && $modules->offsetExists($_POST['buildxml_id'])) {
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

        if (count($combo) < 2) {
            echo 
            '<div class="info">' . __('There are no module to tweak') . '</div>' .
            '</div>';

            return;
        }
/*
        echo 
        '<form method="post" action="' . $page_url . '" id="fetchxml" class="fieldset">' .
        '<h4>' . __('Update an existing plugin') . '</h4>' .
        '<p>' . __('Put URL to a dcstore.xml file for selected plugin to update it.') . '</p>' . 
        '<p class="field"><label for="xml_id" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Plugin to update:') . '</label> ' .
        form::combo('xml_id', $combo) .
        '</p>' .
        '<p class="field"><label for="xml_url" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('XML file URL:') . '</label> ' .
        form::field('xml_url', 40, 255, [
            'extra_html' => 'required placeholder="' . __('URL') . '"'
        ]) .
        '</p>' .
        '<p class="field"><label for="your_pwd1" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label> ' .
        form::password(['your_pwd', 'your_pwd1'], 20, 255,
            [
                'extra_html'   => 'required placeholder="' . __('Password') . '"',
                'autocomplete' => 'current-password'
            ]
        ) . '</p>' .
        '<p><input type="submit" name="fetch_xml" value="' . __('Update') . '" />' .
        $core->formNonce() . '</p>' .
        '</form>';
//*/
        echo 
        '<form method="post" action="' . $page_url . '" id="buildxml" class="fieldset">' .
        '<h4>' . __('Generate xml code') . '</h4>' .
        '<p>' . __('This help to generate content of dcstore.xml for seleted module.') . '</p>' .
        '<p class="field"><label for="buildxml_id" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Module to parse:') . '</label> ' .
        form::combo('buildxml_id', $combo, empty($_POST['buildxml_id']) ? '-' : html::escapeHTML($_POST['buildxml_id'])) .
        '</p>' .
        '<p><input type="submit" name="build_xml" value="' . __('Generate') . '" />' .
        $core->formNonce() . '</p>' .
        '</form>';

        if (!empty($_POST['buildxml_id'])) {
            echo 
            '<form method="post" action="' . $page_url . '" id="writexml" class="fieldset">' .
            '<h4>' . sprintf(__('Generated code for module : %s'), html::escapeHTML($_POST['buildxml_id'])) . '</h4>';

            if (!empty(tweakStores::$failed)) {
                echo sprintf('<div class="warn">' . __('Failed to parse XML code : %s') . '</div>', implode(', ', tweakStores::$failed));
            }
            if (!empty(tweakStores::$notice)) {
                echo sprintf('<div class="info">' . __('Code is not fully filled : %s') . '</div>', implode(', ', tweakStores::$notice));
            }
            if (!empty($xml_content)) {
                if (empty(tweakStores::$failed) && empty(tweakStores::$notice)) {
                    echo '<div class="success">' . __('Code is complete') . '</div>';
                }
                echo
                '<pre>' . form::textArea('gen_xml', 165, 14, html::escapeHTML($xml_content), 'maximal') . '</pre>';

                if (!empty($file_pattern) && $modules[$_POST['buildxml_id']]['root_writable'] && $core->auth->isSuperAdmin()) {
                    if ($core->error->flag()) {
                        echo '<div class="error">' . implode(' ', $core->error->getErrors()) . '</div>';
                    } elseif (!empty($_POST['write_xml'])) {
                        echo '<div class="success">' . __('File successfully write') . '</div>';
                    }
                    echo 
                    '<p class="field"><label for="your_pwd2" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label> ' .
                    form::password(['your_pwd', 'your_pwd2'], 20, 255,
                        [
                            'extra_html'   => 'required placeholder="' . __('Password') . '"',
                            'autocomplete' => 'current-password'
                        ]
                    ) . '</p>' .
                    '<p><input type="submit" name="write_xml" value="' . __('Save to module directory') . '" />' .
                    form::hidden('buildxml_id', $_POST['buildxml_id']);
                }
            }
            echo
            '<p>' . $core->formNonce() . '</p>' .
            '</form>';
        }
        echo 
        '<p><a href="' . $core->adminurl->get('admin.plugins', ['module' => 'tweakStores', 'conf' => 1, 'redir' => $page_url]) .'">' .
        (empty($file_pattern) ?
            __('You must configure zip file pattern to complete xml code automaticaly.') :
            __('You can edit zip file pattern from configuration page.')
        ). '</a></p>' .
        '</div>';
    }

    # create list of module for combo and remove official modules
    protected static function comboModules(arrayObject $modules, $excludes)
    {
        $combo = [ __('Select a module') => '0'];
        foreach ($modules as $id => $module) {
            if (is_array($excludes) && in_array($id, $excludes)) {
                $modules->offsetUnset($id);
                continue;
            }
            $combo[$module['name'] . ' '. $module['version']] = $id;
        }
        return $combo;
    }
}