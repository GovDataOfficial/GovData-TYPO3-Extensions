<?php

/**
 * Extension Manager/Repository config file for ext "gd-site".
 */
$EM_CONF[$_EXTKEY] = [
    'title' => 'gd-site',
    'version' => '1.0.0',
    'description' => 'Initial page structure for GovData',
    'category' => 'distribution',
    'constraints' => [
        'depends' => [
            'typo3' => '12',
            'fluid_styled_content' => '12',
            'rte_ckeditor' => '12',
            'headless' => '4.2.7'
        ],
        'conflicts' => [],
    ],
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'author' => 'GovData Team',
    'author_email' => 'govdata@seitenbau.com',
    'author_company' => 'SEITENBAU GmbH',
];
