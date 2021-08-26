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

if (!defined('DC_CONTEXT_MODULE')) {
    return null;
}

$redir = empty($_REQUEST['redir']) ? 
    $list->getURL() . '#plugins' : $_REQUEST['redir'];

# -- Get settings --
$core->blog->settings->addNamespace('tweakStores');
$s = $core->blog->settings->tweakStores;

$tweakStores_active = $s->active;
$tweakStores_packman = $s->packman;
$tweakStores_file_pattern = $s->file_pattern;

# -- Set settings --
if (!empty($_POST['save'])) {
    try {
        $tweakStores_active = !empty($_POST['tweakStores_active']);
        $tweakStores_packman = !empty($_POST['tweakStores_packman']);
        $tweakStores_file_pattern = $_POST['tweakStores_file_pattern'];

        $s->put('active', $tweakStores_active);
        $s->put('packman', $tweakStores_packman);
        $s->put('file_pattern', $tweakStores_file_pattern);

        dcPage::addSuccessNotice(
            __('Configuration has been successfully updated.')
        );
        http::redirect(
            $list->getURL('module=tweakStores&conf=1&redir=' .
            $list->getRedir())
        );
    } catch (Exception $e) {
        $core->error->add($e->getMessage());
    }
}

# -- Display form --
echo '
<div class="fieldset">
<h4>' . __('Tweak store') . '</h4>

<p><label class="classic" for="tweakStores_active">'.
form::checkbox('tweakStores_active', 1, $tweakStores_active) . ' ' .
__('Enable plugin') . '</label></p>
<p class="form-note">' . __('If enabled, new tab "Tweak stores" allows your to perfom actions relative to third-party repositories.') .'</p>

<p><label class="classic" for="tweakStores_packman">'.
form::checkbox('tweakStores_packman', 1, $tweakStores_packman) . ' ' .
__('Enable packman behaviors') . '</label></p>
<p class="form-note">' . __('If enabled, plugin pacKman (re)generate dcstore.xml at root directory of the plugin.') .'</p>

<p><label class="classic" for="tweakStores_file_pattern">'. __('Predictable URL to zip file on the external repository') .
form::field('tweakStores_file_pattern', 65, 255, $tweakStores_file_pattern, 'maximal') . ' 
</label></p>
<p class="form-note">' . 
__('You can use widcard like %author%, %type%, %id%, %version%.') . '<br /> ' .
__('For exemple on github https://github.com/MyGitName/%id%/releases/download/v%version%/%type%-%id%.zip') . '<br />' .
__('Note on github, you must create a release and join to it the module zip file.') . '
</p>

</div>';