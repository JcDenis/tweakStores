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

if (!$core->auth->isSuperAdmin()) {
    return null;
}

$core->blog->settings->addNamespace('tweakStores');

if (!$core->blog->settings->tweakStores->active) {
    return null;
}

# Admin behaviors
if ($core->blog->settings->tweakStores->packman) {
    $core->addBehavior('packmanBeforeCreatePackage', ['tweakStoresBehaviors', 'packmanBeforeCreatePackage']);
}
$core->addBehavior('pluginsToolsTabs', ['tweakStoresBehaviors', 'pluginsToolsTabs']);

class tweakStoresBehaviors
{
    public static function packmanBeforeCreatePackage(dcCore $core, $module)
    {
        tweakStores::writeXML($module['id'], $module, $core->blog->settings->tweakStores->file_pattern);
    }

    public static function pluginsToolsTabs(dcCore $core)
    {
        $file_pattern = $core->blog->settings->tweakStores->file_pattern;
        $distributed_modules = explode(',', DC_DISTRIB_PLUGINS);
        $plugins = [ __('Select a plugin') => '0'];
        $modules = $core->plugins->getModules();
        foreach ($modules as $id => $module) {
            if (is_array($distributed_modules) && in_array($id, $distributed_modules)) {
                unset($modules[$id]);
                continue;
            }
            $plugins[$module['name'] . ' '. $module['version']] = $id;
        }

        if (!empty($_POST['build_xml']) && !empty($_POST['buildxml_id']) && in_array($_POST['buildxml_id'], $plugins)) {
            $xml_content = tweakStores::generateXML($_POST['buildxml_id'], $modules[$_POST['buildxml_id']], $file_pattern);
        }

        echo
        '<div class="multi-part" id="tweakStores" title="' . __('Tweak stores') . '">' .
        '<h3>' . __('Tweak third-party repositories') . '</h3>' .

        '<form method="post" action="' . $core->adminurl->get('admin.plugins') . '#tweakStores" id="fetchxml" class="fieldset">' .
        '<h4>' . __('Update an existing plugin') . '</h4>' .
        '<p>' . __('Put URL to a dcstore.xml file for selected plugin to update it.') . '</p>' . 
        '<p class="field"><label for="xml_id" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Plugin to update:') . '</label> ' .
        form::combo('xml_id', $plugins) .
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
        '</form>' .

        '<form method="post" action="' . $core->adminurl->get('admin.plugins') . '#tweakStores" id="buildxml" class="fieldset">' .
        '<h4>' . __('Generate xml code') . '</h4>' .
        '<p>' . __('This help to generate content of dcstore.xml for seleted plugin.') . '</p>' .
        '<p class="field"><label for="buildxml_id" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Plugin to parse:') . '</label> ' .
        form::combo('buildxml_id', $plugins, empty($_POST['buildxml_id']) ? '-' : html::escapeHTML($_POST['buildxml_id'])) .
        '</p>' .
        '<p><input type="submit" name="build_xml" value="' . __('Generate') . '" />' .
        $core->formNonce() . '</p>';

        if (!empty($_POST['buildxml_id'])) {
            echo '<h5>' . sprintf(__('Generated code for module : %s'), html::escapeHTML($_POST['buildxml_id'])) . '</h5>';

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
                    echo 
                    '<p class="field"><label for="your_pwd2" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label> ' .
                    form::password(['your_pwd', 'your_pwd2'], 20, 255,
                        [
                            'extra_html'   => 'required placeholder="' . __('Password') . '"',
                            'autocomplete' => 'current-password'
                        ]
                    ) . '</p>' .
                    '<p><input type="submit" name="write_xml" value="' . __('Save to plugin directory') . '" />';
                }
            }
        }
        if (empty($file_pattern)) {
            echo '<p><a href="' . $core->adminurl->get('admin.plugins', ['module' => 'tweakStores', 'conf' => 1]) .'">' .
            __('You must configure zip file pattern to complete xml code automaticaly.') . '</a></p>';
        }

        echo 
        '</form>' .
        '</div>';
    }
}