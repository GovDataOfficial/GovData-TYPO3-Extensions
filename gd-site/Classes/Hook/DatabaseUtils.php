<?php

namespace GdTypo3Extensions\GdSite\Hook;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ArrayParameterType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Log\Logger;

class DatabaseUtils
{
    /**
     * @var Logger
     */
    private $logger;

    public const VALID_DOKTYPE_MAPPINGS = [1 => 'article', 137 => 'blog'];

    public function __construct()
    {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * Retrieves the ID of the page where the content with the given ID resides.
     *
     * @param $contentId
     * @return int|null the ID of the page where the content with the given ID resides
     * @throws Exception
     */
    public function getPageIdFromContentId($contentId): ?int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        $pageId =
            $queryBuilder
                ->select('pid')
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($contentId, Connection::PARAM_INT))
                )
                ->executeQuery()
                ->fetchOne();

        return $pageId ?: null;
    }

    /**
     * Builds a search index entry for the given content ID.
     *
     * This method retrieves the pageId on which the content with the given ID resides and calls
     * buildSearchIndexEntryFromPageId to build the actual search index entry.
     *
     * @param int $contentId The ID of the content to be indexed.
     * @return array|null the search index entry, or null if indexing is not performed.
     *
     * @throws Exception
     * @see buildSearchIndexEntryFromPageId
     */
    public function buildSearchIndexEntryFromContentId(int $contentId): ?array
    {
        $pageId = $this->getPageIdFromContentId($contentId);
        return $pageId ? $this->buildSearchIndexEntryFromPageId($pageId) : null;
    }

    /**
     * Builds a search index entry for the given page ID.
     *
     * This method fetches the data for the page with the given ID, processes the content for indexing,
     * and constructs an entry suitable for a search index.
     *
     * @param int $pageId The ID of the page to be indexed.
     * @return array|null the search index entry, or null if indexing is not performed.
     *
     * @throws Exception
     */
    public function buildSearchIndexEntryFromPageId(int $pageId): ?array
    {
        // Seite abrufen, zu der der Inhalt gehört
        $record = $this->getPageWithContents($pageId);

        if (!$record) {
            // Do nothing!
            return null;
        }

        // For Index Field 'preamble'
        $contentBodyTextArray = array();
        $contentTimeStamps = array();
        foreach ($record['contents'] as $content) {
            // It seems we don't need this check as hidden contents are not revealed by the database.
            // But let's be sure anyhow.
            if (!$content['hidden']) {
                // Remove HTML-Tags from bodytext and add it
                $contentBodyTextArray[] = strip_tags($content['bodytext']);
                $contentTimeStamps[] = $content['tstamp'];
            }
        }

        if (!empty($contentTimeStamps)) {
            sort($contentTimeStamps);
            $lastUpdatedTs = end($contentTimeStamps);
        } else {
            $lastUpdatedTs = $record['page']['tstamp'];
        }
        $formattedTimestamp = date('Y-m-d\TH:i:s', $lastUpdatedTs);
        $record['page']['modified'] = $formattedTimestamp;

        if (isset(self::VALID_DOKTYPE_MAPPINGS[intval($record['page']['doktype'])])) {
            $record['page']['type'] = self::VALID_DOKTYPE_MAPPINGS[intval($record['page']['doktype'])];
        }

        // Join  all array elements for 'preamble'
        $contentBodyTextArrayConcat = implode(' ', $contentBodyTextArray);

        $document = [
            'id' => $pageId,
            'title' => $record['page']['title'],
            'preamble' => $contentBodyTextArrayConcat,
            'targetlink' => $record['page']['slug'],
            // buildMetadataSource(SearchIndexEntry entry): metadata field must be a JSON String
            'metadata' => json_encode($record['page'])
        ];

        return [[
            "indexName" => SearchIndexService::INDEX_NAME,
            "document" => $document
        ]];
    }


    /**
     * Retrieves a page and its associated content elements by page ID.
     *
     * This method fetches the page data from the 'pages' table and its content elements
     * from the 'tt_content' table, returning them as an associative array. If the page
     * has the 'no_search' flag set, it returns null. If the page has neither visible contents nor an abstract,
     * it returns null.
     *
     * @param int $pageId The ID of the page to retrieve.
     * @return array|null An associative array containing the page data and its contents,
     * or null if the page should not be indexed.
     *
     * @throws Exception
     */
    public function getPageWithContents(int $pageId): ?array
    {
        // Seitendaten abrufen
        $page = $this->getPageData($pageId);
        if (!$page) {
            return null;
        }

        // Inhalte der Seite abrufen
        $contentQueryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        $contents = $contentQueryBuilder
            ->select('*')
            ->from('tt_content')
            ->where(
                $contentQueryBuilder->expr()->eq('pid', $contentQueryBuilder->createNamedParameter($pageId, Connection::PARAM_INT))
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        if (count($contents) > 0 || $page['abstract']) {
            return [
                'page' => $page,
                'contents' => $contents
            ];
        }

        return null;
    }

    /**
     * Retrieves the data of the page with the given ID from the database.
     *
     * @param int $pageId
     * @return mixed|null
     * @throws Exception
     */
    public function getPageData(int $pageId): ?array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $page = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!$page) {
            $this->logger->debug('Page with id ' . $pageId . ' not found, probably because it has been marked as disabled.');
            $page = null;
        } else {
            if ($page['no_search']) {
                $this->logger->debug('Page with id ' . $pageId . ' is not supposed to be included in search.');
                $page = null;
            } else if (!array_key_exists(intval($page['doktype']), self::VALID_DOKTYPE_MAPPINGS)) {
                $this->logger->debug('Page with id ' . $pageId . ' has unsupported doktype: ' . $page['doktype'] . ' and is not going to be indexed.');
                $page = null;
            }
        }

        return $page;
    }

    /**
     * Retrieves all IDs of pages which aren't marked as "not no be included in search".
     * If there are none, returns null.
     *
     * @throws Exception
     */
    public function getAllIndexablePageIds(): ?array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');

        $result = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->in('doktype', $queryBuilder->createNamedParameter(array_keys(self::VALID_DOKTYPE_MAPPINGS), ArrayParameterType::INTEGER)),
                $queryBuilder->expr()->eq('no_search', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        if ($result) {
            return array_map(function ($item) {
                return $item['uid'];
            }, $result);
        }

        return null;
    }

}