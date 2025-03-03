<?php
/**
 * @file
 * @brief       The plugin tweakStores definition
 * @ingroup     tweakStores
 *
 * @defgroup    tweakStores Plugin tweakStores.
 *
 * Helper to manage external repositories.
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

$this->registerModule(
    'Tweak stores',
    'Helper to manage external repositories',
    'Jean-Christian Denis and Contributors',
    '1.2.1',
    [
        'requires'    => [['core', '2.28']],
        'permissions' => 'My',
        'type'        => 'plugin',
        'support'     => 'https://github.com/JcDenis/' . $this->id . '/issues',
        'details'     => 'https://github.com/JcDenis/' . $this->id . '/',
        'repository'  => 'https://raw.githubusercontent.com/JcDenis/' . $this->id . '/master/dcstore.xml',
        'date'        => '2025-03-03T14:22:17+00:00',
    ]
);
