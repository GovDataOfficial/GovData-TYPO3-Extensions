<?php
$GLOBALS['TYPO3_CONF_VARS']['LOG']['GdTypo3Extensions']['GdSite']['Hook']['DataHandlerHook']['writerConfiguration'] = [
    \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
        // configuration for DEBUG level log entries
        \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
            'logFile' => \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('typo3temp/var/log/gd-site.log')
        ]
    ],
];

$GLOBALS['TYPO3_CONF_VARS']['LOG']['GdTypo3Extensions']['GdSite']['Hook']['SearchIndexService']['writerConfiguration'] = [
    \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
        // configuration for DEBUG level log entries
        \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
            'logFile' => \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('typo3temp/var/log/gd-site.log')
        ]
    ],
];

$GLOBALS['TYPO3_CONF_VARS']['LOG']['GdTypo3Extensions']['GdSite']['Hook']['DatabaseUtils']['writerConfiguration'] = [
    \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
        // configuration for DEBUG level log entries
        \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
            'logFile' => \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('typo3temp/var/log/gd-site.log')
        ]
    ],
];

//CREATE,UPDATE
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \GdTypo3Extensions\GdSite\Hook\DataHandlerHook::class;
//REMOVE
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = \GdTypo3Extensions\GdSite\Hook\DataHandlerHook::class;

$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['gd-site'] = [
    'servicefacade_url' => 'localhost:9070',
    'servicefacade_username' => 'kermit',
    'servicefacade_password' => 'kermit'
];

