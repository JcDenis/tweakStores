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

if (!defined('DC_RC_PATH')) {
    return null;
}

$this->registerModule(
    'Tweak stores',
    'Helper to manage external repositories',
    'Jean-Christian Denis and Contributors',
    '0.0.2',
    [
        'permissions' => null,
        'type' => 'plugin',
        'dc_min' => '2.19',
        'support' => 'https://github.com/JcDenis/tweakStores',
        'details' => 'https://plugins.dotaddict.org/dc2/details/tweakStores',
        'repository' => 'https://raw.githubusercontent.com/JcDenis/tweakStores/master/'
    ]
);