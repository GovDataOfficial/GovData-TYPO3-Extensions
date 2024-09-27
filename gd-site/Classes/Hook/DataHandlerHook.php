<?php

namespace GdTypo3Extensions\GdSite\Hook;

use Doctrine\DBAL\Exception;
use GdTypo3Extensions\GdSite\Hook\DatabaseUtils;
use GdTypo3Extensions\GdSite\Hook\SearchIndexService;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DataHandlerHook
{
    /**
     * @var Logger
     */
    private $logger;

    private $searchIndexService;

    private $databaseUtils;

    private static array $contentDeleteOperationsForPage;

    public function __construct()
    {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->searchIndexService = GeneralUtility::makeInstance(SearchIndexService::class);
        $this->databaseUtils = GeneralUtility::makeInstance(DatabaseUtils::class);
        self::$contentDeleteOperationsForPage = array();
    }

    /**
     * This hook takes care of creating/updating hidden content.
     */
    public function processCmdmap_afterFinish(DataHandler $dataHandler) {
        if (isset($dataHandler->datamap['tt_content'])) {
            foreach ($dataHandler->datamap['tt_content'] as $key => $value) {
                if ($value['hidden']) {
                    $this->logger->debug('DataHandlerHook afterFinish triggered.');
                    if (str_starts_with($key, 'NEW')) {
                        // new content
                        $pageId = intval($dataHandler->datamap['tt_content'][$key]['pid']);
                        if  ($pageId < 0) {
                            $pageId = $dataHandler->resolvePid('tt_content', $pageId);
                        }
                    } else {
                        // existing content
                        $pageId = $dataHandler->checkValue_currentRecord['pid'];
                    }
                    $this->buildAndHandleSearchIndexEntry($pageId,
                        'Deleting possible entry for page with ID ' . $pageId);
                }
            }
        }
    }

    /**
     * This hook creates or updates index entries for new or modified pages or contents (except for hidden content
     * which is handled in processCmdmap_afterFinish.
     *
     * @see processCmdmap_afterFinish
     */
    public function processDatamap_afterDatabaseOperations($status, $table, $id, $fieldArray, DataHandler $dataHandler)
    {
        $this->logger->debug('DataHandlerHook afterDatabaseOperations triggered', [
            'status' => $status,
            'table' => $table,
            'id' => $id,
            'fieldArray' => $fieldArray
        ]);

        if ($table === 'pages') {
            if (isset($fieldArray['no_search']) && $fieldArray['no_search']) {
                // page has been marked as not to be included in search
                $this->deleteSearchIndexEntry($id,
                    'Page with ID ' . $id . ' has been marked as not to be included in search. Deleting entry.');
            } else {
                // In case of a page with content which has been re-marked as "included in search" again, the entry is saved
                // In case of a disabled (hidden) page (disabled/hidden manually), a possible index entry is going to be deleted
                // in case of a new page, we don't need to do anything as it is hidden initially and needs to be enabled afterwards manually
                if (!isset($dataHandler->substNEWwithIDs[$id])) {
                    $this->buildAndHandleSearchIndexEntry($id, 'Deleting possible entry for page with ID ' . $id);
                }
            }
        }
        elseif ($table === 'tt_content') {
            // special case of updating entries when content is hidden is handled in processCmdmap_afterFinish hook
            if ((isset($fieldArray['hidden']) && !$fieldArray['hidden']) || !isset($fieldArray['hidden'])) {
                $contentId = $dataHandler->substNEWwithIDs[$id] ?? $id;
                try {
                    $entry = $this->databaseUtils->buildSearchIndexEntryFromContentId($contentId);
                    if ($entry) {
                        $this->searchIndexService->save($entry);
                    }
                } catch (Exception $e) {
                    $this->logger->error(
                        'Error while updating content with ID ' . $contentId . ': ' . $e->getMessage()
                    );
                }
            }
        }
    }

    /**
     * This hook takes care of either deleting or updating the index entry of a page in case a page or a content
     * has been deleted.
     * In case of deleted content, it's according pageId has been stored temporarily
     * by the hook processCmdmap_deleteAction, which is processed here.
     *
     * @see processCmdmap_deleteAction
     */
    public function processCmdmap_postProcess($command, $table, $id, $value, DataHandler $dataHandler)
    {
        if ($command === 'delete') {
            $this->logger->debug('DataHandlerHook postProcess triggered', [
                'command' => $command,
                'table' => $table,
                'id' => $id,
                'value' => $value
            ]);

            if ($table === 'pages') {
                // delete the page
                $this->deleteSearchIndexEntry($id, 'Deleting entry for page with ID ' . $id);
            } elseif ($table === 'tt_content') {
                // save content entry or delete page entry if there is no more (visible) content on the page
                $contentId = $dataHandler->substNEWwithIDs[$id] ?? $id;
                $pageId = self::$contentDeleteOperationsForPage[$contentId];

                if ($pageId) {
                    // save updated record
                    if ($this->buildAndHandleSearchIndexEntry($pageId,
                    'There is no more visible content on the page with ID ' . $pageId . '. Deleting entry.')) {
                        unset(self::$contentDeleteOperationsForPage[$contentId]);
                    }
                }
            }
        }
    }

    /**
     * This hook saves the pageId of the content which is about to be deleted. We need to to this before the content is
     * deleted because afterwards, we cannot find the content entry in the database any more
     * (because it has been deleted). The actual update of the index entry is then handled in the hook
     * processCmdmap_postProcess.
     *
     * @see processCmdmap_postProcess
     */
    public function processCmdmap_deleteAction($table, $id, array $record, &$recordWasDeleted, DataHandler $dataHandler)
    {
        $this->logger->debug('DataHandlerHook deleteAction triggered', [
            'table' => $table,
            'id' => $id,
            'record' => $record,
            'recordWasDeleted' => $recordWasDeleted
        ]);

        if ($table === 'tt_content' && !$record['hidden']) {
            // Info: deletion of a hidden content does not need to be forwarded to the search index
            // as the index has already been updated when the content was hidden
            $contentId = $dataHandler->substNEWwithIDs[$id] ?? $id;
            try {
                $pageId = $this->databaseUtils->getPageIdFromContentId($contentId);
                $page = $this->databaseUtils->getPageData($pageId);
                if ($page && !$page['no_search']) {
                    // save the page id so that the delete action can be post-processed in the post hook later
                    self::$contentDeleteOperationsForPage[$contentId] = $pageId;
                }
            } catch (Exception $e) {
                $this->logger->error(
                    'Error while marking content with ID ' . $contentId . ' for deletion: ' . $e->getMessage()
                );
            }
        }
    }

    private function buildAndHandleSearchIndexEntry(int $pageId, string $deletionLoggingMessage): bool
    {
        try {
            $entry = $this->databaseUtils->buildSearchIndexEntryFromPageId($pageId);
        } catch (Exception $e) {
            $this->logger->error(
                'Error while building the search index entry for page with ID ' . $pageId . ': ' . $e->getMessage()
            );
            return false;
        }

        if ($entry) {
            $this->searchIndexService->save($entry);
        } else {
            $this->deleteSearchIndexEntry($pageId, $deletionLoggingMessage);
        }

        return true;
    }

    private function deleteSearchIndexEntry(int $pageId, string $loggingMessage): void
    {
        $this->logger->info($loggingMessage);
        $entry = [[
            "indexName" => SearchIndexService::INDEX_NAME,
            "document" => [
                'id' => $pageId
            ]
        ]];
        $this->searchIndexService->delete($entry, $pageId);
    }

}