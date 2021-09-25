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

class tweakStores
{
    public static $notice = [];
    public static $failed = [];

    # taken from lib.moduleslist.php
    public static function sanitizeModule($id, $module)
    {
        $label = empty($module['label']) ? $id : $module['label'];
        $name  = __(empty($module['name']) ? $label : $module['name']);
        $oname  = empty($module['name']) ? $label : $module['name'];

        return array_merge(
            # Default values
            [
                'desc'              => '',
                'author'            => '',
                'version'           => 0,
                'current_version'   => 0,
                'root'              => '',
                'root_writable'     => false,
                'permissions'       => null,
                'parent'            => null,
                'priority'          => 1000,
                'standalone_config' => false,
                'support'           => '',
                'section'           => '',
                'tags'              => '',
                'details'           => '',
                'sshot'             => '',
                'score'             => 0,
                'type'              => null,
                'requires'          => [],
                'settings'          => [],
                'repository'        => '',
                'dc_min'            => 0
            ],
            # Module's values
            $module,
            # Clean up values
            [
                'id'    => $id,
                'sid'   => self::sanitizeString($id),
                'label' => $label,
                'name'  => $name,
                'oname' => $oname,
                'sname' => self::sanitizeString($name)
            ]
        );
    }

    # taken from lib.moduleslist.php
    public static function sanitizeString($str)
    {
        return preg_replace('/[^A-Za-z0-9\@\#+_-]/', '', strtolower($str));
    }

    public static function parseFilePattern($id, $module, $file_pattern)
    {
        $module = self::sanitizeModule($id, $module);
        return text::tidyURL(str_replace(
            [
                '%type%',
                '%id%',
                '%version%',
                '%author%'
            ],
            [
                $module['type'],
                $module['id'],
                $module['version'],
                $module['author']
            ],
            $file_pattern
        ));
    }

    public static function generateXML($id, $module, $file_pattern)
    {
        if (!is_array($module) || empty($module)) {
            return false;
        }
        $module = self::sanitizeModule($id, $module);

        self::$notice = [];
        self::$failed = [];
        $xml = ['<modules xmlns:da="http://dotaddict.org/da/">'];

        # id
        if (empty($module['id'])) {
            self::$failed[] = 'unknow module';
        }
        $xml[] = sprintf('<module id="%s">', html::escapeHTML($module['id']));

        # name
        if (empty($module['name'])) {
            self::$failed[] = 'no module name set in _define.php';
        }
        $xml[] = sprintf('<name>%s</name>', html::escapeHTML($module['oname']));

        # version
        if (empty($module['version'])) {
            self::$failed[] = 'no module version set in _define.php';
        }
        $xml[] = sprintf('<version>%s</version>', html::escapeHTML($module['version']));

        # author
        if (empty($module['author'])) {
            self::$failed[] = 'no module author set in _define.php';

        }
        $xml[] = sprintf('<author>%s</author>', html::escapeHTML($module['author']));

        # desc
        if (empty($module['desc'])) {
            self::$failed[] = 'no module description set in _define.php';
        }
        $xml[] = sprintf('<desc>%s</desc>', html::escapeHTML($module['desc']));

        # repository
        if (empty($module['repository'])) {
            self::$failed[] = 'no repository set in _define.php';
        }

        # file
        $file_pattern = self::parseFilePattern($id, $module, $file_pattern);
        if (empty($file_pattern)) {
            self::$failed[] = 'no zip file pattern set in Tweak Store configuration';
        }
        $xml[] = sprintf('<file>%s</file>', html::escapeHTML($file_pattern));

        # da dc_min or requires core
        if (!empty($>module['requires']) && is_array($module['requires'])) {
            foreach ($module['requires'] as $req) {
                if (!is_array($req)) {
                    $req = [$req];
                }
                if ($req[0] == 'core') {
                    $module['dc_min'] = $req[1];
                    break;
                }
            }
        }
        if (empty($module['dc_min'])) {
            self::$notice[] = 'no minimum dotclear version';
        } else {
            $xml[] = sprintf('<da:dcmin>%s</da:dcmin>', html::escapeHTML($module['dc_min']));
        }

        # details
        if (empty($module['details'])) {
            self::$notice[] = 'no details URL';
        }
        $xml[] = sprintf('<da:details>%s</da:details>', html::escapeHTML($module['details']));

        # section
        $xml[] = sprintf('<da:section>%s</da:section>', html::escapeHTML($module['section']));

        # support
        if (empty($module['support'])) {
            self::$notice[] = 'no support URL';
        }
        $xml[] = sprintf('<da:support>%s</da:support>', html::escapeHTML($module['support']));

        $xml[] = '</module>';
        $xml[] = '</modules>';

        return implode("\n", $xml);
    }

    public static function writeXML($id, $module, $file_pattern)
    {
        self::$failed = [];
        if (!$module['root_writable']) {
            return false;
        }
        $content = self::generateXML($id, $module, $file_pattern);
        if (!empty(self::$failed)) {
            return false;
        }
        try {
            files::putContent($module['root'] . '/dcstore.xml', $content);
        } catch(Exception $e) {
            self::$failed[] = $e->getMessage();
            return false;
        }
        return true;
    }
}