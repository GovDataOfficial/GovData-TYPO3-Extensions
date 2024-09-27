<?php

namespace GdTypo3Extensions\GdSite\Tests\Unit\Hook;

use GdTypo3Extensions\GdSite\Hook\DatabaseUtils;
use GdTypo3Extensions\GdSite\Hook\DataHandlerHook;
use GdTypo3Extensions\GdSite\Hook\SearchIndexService;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

require_once __DIR__ . "/../../../Classes/Hook/DatabaseUtils.php";
require_once __DIR__ . "/../../../Classes/Hook/DataHandlerHook.php";
require_once __DIR__ . "/../../../Classes/Hook/SearchIndexService.php";

class DataHandlerHookTest extends UnitTestCase
{
    public ?DataHandlerHook $subject = null;
    private $databaseUtilsMock;
    private $loggerMock;
    private $searchIndexServiceMock;
    private $dataHandlerMock;
    private int $pageId = 10;
    private $searchIndexEntry;

    public static function booleans(): array
    {
        return [[true], [false]];
    }

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->dataHandlerMock = $this->createMock(DataHandler::class);
        $this->loggerMock = $this->createMock(Logger::class);
        $this->searchIndexServiceMock = $this->createMock(SearchIndexService::class);
        $this->databaseUtilsMock = $this->createMock(DatabaseUtils::class);
        GeneralUtility::addInstance(DatabaseUtils::class, $this->databaseUtilsMock);
        GeneralUtility::addInstance(SearchIndexService::class, $this->searchIndexServiceMock);

        $this->searchIndexEntry = [['indexName' => 'govdata-liferay-da'], 'document' => ['id' => $this->pageId, 'title' => 'Tittel']];

        $this->subject = new DataHandlerHook();
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function processCmdmap_afterFinish_updateHiddenContent(): void
    {

        $this->dataHandlerMock->datamap = ['tt_content' => [4 => ['hidden' => 1]]];
        $this->dataHandlerMock->checkValue_currentRecord = ['pid' => $this->pageId];
        $this->databaseUtilsMock->method('buildSearchIndexEntryFromPageId')->with($this->pageId)->willReturn($this->searchIndexEntry);
        $this->searchIndexServiceMock->method('save')->with($this->searchIndexEntry)->willReturn(new Response());

        $this->subject->processCmdmap_afterFinish($this->dataHandlerMock);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function processCmdmap_afterFinish_newHiddenContent(): void
    {
        $this->dataHandlerMock->datamap = ['tt_content' => [
            'NEWxyz' => ['hidden' => 1, 'pid' => $this->pageId]
        ]];
        $this->databaseUtilsMock->method('buildSearchIndexEntryFromPageId')->with($this->pageId)->willReturn($this->searchIndexEntry);
        $this->searchIndexServiceMock->method('save')->with($this->searchIndexEntry)->willReturn(new Response());

        $this->subject->processCmdmap_afterFinish($this->dataHandlerMock);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function processCmdmap_afterFinish_updateHiddenContent_dbError(): void
    {

        $this->dataHandlerMock->datamap = ['tt_content' => [4 => ['hidden' => 1]]];
        $this->dataHandlerMock->checkValue_currentRecord = ['pid' => $this->pageId];
        $this->databaseUtilsMock->method('buildSearchIndexEntryFromPageId')->with($this->pageId)->willThrowException(new \Doctrine\DBAL\Exception());
        $this->searchIndexServiceMock->expects(self::never())->method(self::anything());

        $this->subject->processCmdmap_afterFinish($this->dataHandlerMock);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function processCmdmap_afterFinish_updateHiddenContent_noMoreContentOnPage(): void
    {

        $this->dataHandlerMock->datamap = ['tt_content' => [4 => ['hidden' => 1]]];
        $this->dataHandlerMock->checkValue_currentRecord = ['pid' => $this->pageId];
        $this->databaseUtilsMock->method('buildSearchIndexEntryFromPageId')->with($this->pageId)->willReturn(null);
        $entry = [[
            "indexName" => SearchIndexService::INDEX_NAME,
            "document" => [
                'id' => $this->pageId
            ]
        ]];
        $this->searchIndexServiceMock->method('delete')->with($entry);
        $this->subject->processCmdmap_afterFinish($this->dataHandlerMock);

        $this->searchIndexServiceMock->expects(self::never())->method('save');
    }

    /**
     * New Page - don't do anything as it is hidden by default
     * @throws Exception
     */
    #[Test]
    public function processDatamap_afterDatabaseOperations_page_new() {
        $newId = 'NEW123456';
        $this->dataHandlerMock->substNEWwithIDs = [$newId => 123_456];

        $this->subject->processDatamap_afterDatabaseOperations('new', 'pages', $newId, ['hidden' => 1], $this->dataHandlerMock);

        $this->searchIndexServiceMock->expects(self::never())->method(self::anything());
        $this->databaseUtilsMock->expects(self::never())->method(self::anything());
    }

    /**
     * Page has been marked as not to be included in search -> delete entry
     * @throws Exception
     */
    #[Test]
    public function processDatamap_afterDatabaseOperations_page_noSearch() {
        $this->subject->processDatamap_afterDatabaseOperations('update', 'pages', $this->pageId, ['no_search' => 1], $this->dataHandlerMock);

        $entry = [[
            "indexName" => SearchIndexService::INDEX_NAME,
            "document" => [
                'id' => $this->pageId
            ]
        ]];
        $this->searchIndexServiceMock->method('delete')->with($entry);
        $this->databaseUtilsMock->expects(self::never())->method(self::anything());
    }

    /**
     * Page has been updated (e.g. re-marked as to be included in search again) -> save entry
     * @throws Exception
     */
    #[Test]
    public function processDatamap_afterDatabaseOperations_page_update() {
        $this->databaseUtilsMock->method('buildSearchIndexEntryFromPageId')->with($this->pageId)->willReturn($this->searchIndexEntry);
        $this->searchIndexServiceMock->method('save')->with($this->searchIndexEntry)->willReturn(new Response());

        $this->subject->processDatamap_afterDatabaseOperations('update', 'pages', $this->pageId, ['hidden' => 0], $this->dataHandlerMock);
    }

    /**
     * Content has been hidden -> don't do anything, special case is handled and tested in processCmdmap_afterFinish
     * @throws Exception
     */
    #[Test]
    public function processDatamap_afterDatabaseOperations_content_hidden() {
        $this->subject->processDatamap_afterDatabaseOperations('update', 'tt_content', '3', ['hidden' => 1], $this->dataHandlerMock);

        $this->searchIndexServiceMock->expects(self::never())->method(self::anything());
        $this->databaseUtilsMock->expects(self::never())->method(self::anything());
    }

    /**
     * New (visible) content behaves the same way updated content does -> save the page entry.
     * @throws Exception
     */
    #[Test]
    public function processDatamap_afterDatabaseOperations_content_new() {
        $newId = 'NEW123456';
        $contentId = 123_456;
        $this->dataHandlerMock->substNEWwithIDs = [$newId => $contentId];
        $this->databaseUtilsMock->method('buildSearchIndexEntryFromContentId')->with($contentId)->willReturn($this->searchIndexEntry);
        $this->searchIndexServiceMock->method('save')->with($this->searchIndexEntry);

        $this->subject->processDatamap_afterDatabaseOperations('new', 'tt_content', $newId, [], $this->dataHandlerMock);

    }

    /**
     * In case of an error caused by the database, don't update the index.
     * @throws Exception
     */
    #[Test]
    public function processDatamap_afterDatabaseOperations_content_dbError() {
        $contentId = 3;
        $this->databaseUtilsMock->method('buildSearchIndexEntryFromContentId')->with($contentId)->willThrowException(new \Doctrine\DBAL\Exception());

        $this->subject->processDatamap_afterDatabaseOperations('update', 'tt_content', $contentId, [], $this->dataHandlerMock);

        $this->searchIndexServiceMock->expects(self::never())->method(self::anything());
    }

    /**
     * If the according page is searchable, should put an entry in the contentDeleteOperationsForPage array.
     */
    #[Test]
    #[DataProvider('booleans')]
    public function processCmdmap_deleteAction(bool $pageIsSearchable) {
        $recordWasDeleted = false;
        $contentId = 3;

        $this->databaseUtilsMock->method('getPageIdFromContentId')->with($contentId)->willReturn($this->pageId);
        $this->databaseUtilsMock->method('getPageData')->with($this->pageId)
            ->willReturn($pageIsSearchable ? ['no_search' => 0] : ['no_search' => 1]);

        $this->subject->processCmdmap_deleteAction('tt_content', $contentId, ['hidden' => 0], $recordWasDeleted, $this->dataHandlerMock);

        $contentDeleteOperationsForPage = new \ReflectionProperty(DataHandlerHook::class, 'contentDeleteOperationsForPage');
        $contentDeleteOperations = $contentDeleteOperationsForPage->getValue();
        if ($pageIsSearchable) {
            self::assertEquals([$contentId => $this->pageId], $contentDeleteOperations);
        } else {
            self::assertEmpty($contentDeleteOperations);
        }
        $this->searchIndexServiceMock->expects(self::never())->method(self::anything());
    }

    /**
     * We don't need to delete a hidden record from the index as it has been deleted already when it was hidden.
     * @throws Exception
     */
    #[Test]
    public function processCmdmap_deleteAction_hiddenContent() {
        $recordWasDeleted = false;
        $this->subject->processCmdmap_deleteAction('tt_content', 3, ['hidden' => 1], $recordWasDeleted, $this->dataHandlerMock);

        $this->searchIndexServiceMock->expects(self::never())->method(self::anything());
        $this->databaseUtilsMock->expects(self::never())->method(self::anything());
    }

    /**
     * If there is an exception thrown during DB interactions, should not put an entry in the contentDeleteOperationsForPage array.
     * @throws Exception
     */
    #[Test]
    public function processCmdmap_deleteAction_dbError() {
        $recordWasDeleted = false;
        $contentId = 3;
        $this->databaseUtilsMock->method('getPageIdFromContentId')->with($contentId)->willThrowException(new \Doctrine\DBAL\Exception());

        $this->subject->processCmdmap_deleteAction('tt_content', $contentId, ['hidden' => 0], $recordWasDeleted, $this->dataHandlerMock);

        $contentDeleteOperationsForPage = new \ReflectionProperty(DataHandlerHook::class, 'contentDeleteOperationsForPage');
        $contentDeleteOperations = $contentDeleteOperationsForPage->getValue();
        self::assertEmpty($contentDeleteOperations);

        $this->databaseUtilsMock->expects(self::never())->method('getPageData');
        $this->searchIndexServiceMock->expects(self::never())->method(self::anything());
    }

    /**
     * Delete search index entry, triggered by page delete command.
     */
    #[Test]
    public function processCmdmap_postProcess_delete_page() {
        $this->subject->processCmdmap_postProcess('delete', 'pages', $this->pageId, "1", $this->dataHandlerMock);
        
        $entry = [[
            "indexName" => SearchIndexService::INDEX_NAME,
            "document" => [
                'id' => $this->pageId
            ]
        ]];
        $this->searchIndexServiceMock->method('delete')->with($entry);
    }

    /**
     * Update search index entry, triggered by content delete command.
     */
    #[Test]
    #[DataProvider('booleans')]
    public function processCmdmap_postProcess_delete_content(bool $errorDuringDBInteraction) {
        $contentId = 3;
        $reflection = new \ReflectionClass(DataHandlerHook::class);
        $reflection->setStaticPropertyValue('contentDeleteOperationsForPage', [$contentId => $this->pageId]);

        if (!$errorDuringDBInteraction) {
            $this->databaseUtilsMock->method('buildSearchIndexEntryFromPageId')->with($this->pageId)->willReturn($this->searchIndexEntry);
            $this->searchIndexServiceMock->method('save')->with($this->searchIndexEntry)->willReturn(new Response());
        } else {
            $this->databaseUtilsMock->method('buildSearchIndexEntryFromPageId')->with($this->pageId)->willThrowException(new \Doctrine\DBAL\Exception());
            $this->searchIndexServiceMock->expects(self::never())->method(self::anything());
        }

        $this->subject->processCmdmap_postProcess('delete', 'tt_content', $contentId, "1", $this->dataHandlerMock);

        if (!$errorDuringDBInteraction) {
            self::assertEmpty($reflection->getStaticPropertyValue('contentDeleteOperationsForPage'));
        } else {
            self::assertEquals([$contentId => $this->pageId], $reflection->getStaticPropertyValue('contentDeleteOperationsForPage'));
        }
    }

}
