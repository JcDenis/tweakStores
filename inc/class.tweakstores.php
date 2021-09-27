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
        $rsp = new xmlTag('module');

        self::$notice = [];
        self::$failed = [];

        # id
        if (empty($module['id'])) {
            self::$failed[] = 'unknow module';
        }
        $rsp->id = $module['id'];

        # name
        if (empty($module['name'])) {
            self::$failed[] = 'no module name set in _define.php';
        }
        $rsp->name($module['oname']);

        # version
        if (empty($module['version'])) {
            self::$failed[] = 'no module version set in _define.php';
        }
        $rsp->version($module['version']);

        # author
        if (empty($module['author'])) {
            self::$failed[] = 'no module author set in _define.php';

        }
        $rsp->author($module['author']);

        # desc
        if (empty($module['desc'])) {
            self::$failed[] = 'no module description set in _define.php';
        }
        $rsp->desc($module['desc']);

        # repository
        if (empty($module['repository'])) {
            self::$failed[] = 'no repository set in _define.php';
        }

        # file
        $file_pattern = self::parseFilePattern($id, $module, $file_pattern);
        if (empty($file_pattern)) {
            self::$failed[] = 'no zip file pattern set in Tweak Store configuration';
        }
        $rsp->file($file_pattern);

        # da dc_min or requires core
        if (!empty($module['requires']) && is_array($module['requires'])) {
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
            $rsp->insertNode(new xmlTag('da:dcmin', $module['dc_min']));
        }

        # details
        if (empty($module['details'])) {
            self::$notice[] = 'no details URL';
        } else {
            $rsp->insertNode(new xmlTag('da:details', $module['details']));
        }

        # section
        if (!empty($module['section'])) {
            $rsp->insertNode(new xmlTag('da:section', $module['section']));
        }

        # support
        if (empty($module['support'])) {
            self::$notice[] = 'no support URL';
        } else {
            $rsp->insertNode(new xmlTag('da:support', $module['support']));
        }

        $res = new xmlTag('modules', $rsp);
        $res->insertAttr('xmlns:da', 'http://dotaddict.org/da/');

        return $res->toXML();
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
            files::putContent($module['root'] . '/dcstore.xml', str_replace('><', ">\n<", $content));
        } catch(Exception $e) {
            self::$failed[] = $e->getMessage();
            return false;
        }
        return true;
    }
}