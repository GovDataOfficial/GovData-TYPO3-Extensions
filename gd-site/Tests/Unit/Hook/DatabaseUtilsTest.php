<?php

namespace GdTypo3Extensions\GdSite\Tests\Unit\Hook;

use Doctrine\DBAL\Result;
use GdTypo3Extensions\GdSite\Hook\DatabaseUtils;
use GdTypo3Extensions\GdSite\Hook\SearchIndexService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

require_once __DIR__ . "/../../../Classes/Hook/DatabaseUtils.php";
require_once __DIR__ . "/../../../Classes/Hook/SearchIndexService.php";

class DatabaseUtilsTest extends UnitTestCase
{
    public ?DatabaseUtils $subject = null;
    private $pagesQueryBuilderMock;
    private $contentQueryBuilderMock;
    private $pageResultStatementMock;
    private $contentResultStatementMock;
    private $pagesConnectionPoolMock;
    private $contentConnectionPoolMock;
    private array $pageData;
    private array $pageContents;
    private int $pageId = 1;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new DatabaseUtils();

        $this->pagesQueryBuilderMock = $this->createMock(QueryBuilder::class);
        $this->contentQueryBuilderMock = $this->createMock(QueryBuilder::class);
        $this->pagesConnectionPoolMock = $this->createMock(ConnectionPool::class);
        $this->contentConnectionPoolMock = $this->createMock(ConnectionPool::class);
        GeneralUtility::addInstance(ConnectionPool::class, $this->pagesConnectionPoolMock);
        GeneralUtility::addInstance(ConnectionPool::class, $this->contentConnectionPoolMock);
        $this->pageResultStatementMock = $this->createMock(Result::class);
        $this->contentResultStatementMock = $this->createMock(Result::class);

        $this->pageData = array();
        $this->pageData['uid'] = $this->pageId;
        $this->pageData['title'] = 'Title';
        $this->pageData['slug'] = '/informationen/back-to-the-future';
        $this->pageData['doktype'] = 1;
        $this->pageData['no_search'] = 0;
        $this->pageData['abstract'] = null;

        $nowTs = strtotime('now');
        $yesterdayTs = strtotime('- 1 day');
        $body1 = 'body1';
        $body2 = 'body2';

        $this->pageContents =
            [[  'uid' => '1',
                'bodytext' => '<p>' . $body1 . '</p>',
                'tstamp' => $nowTs,
                'hidden' => 0
            ],
            [  'uid' => '2',
                'bodytext' => '<p>' . $body2 . '</p>',
                'tstamp' => $yesterdayTs,
                'hidden' => 0
            ]];
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    #[Test]
    public function buildSearchIndexEntryFromPageId_pageExcludedFromSearch(): void
    {
        // setup
        $this->pageData['no_search'] = 1;
        $this->mockDBPageQueryBehaviour($this->pageData);
        $this->mockDBContentQueryBehaviour($this->pageContents);

        // execute && verify
        self::assertNull($this->subject->buildSearchIndexEntryFromPageId($this->pageId));
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    #[Test]
    #[DataProvider('generateDokTypes')]
    public function buildSearchIndexEntryFromPageId_dokTypes(int $dokType): void
    {
        // setup
        $this->pageData['doktype'] = $dokType;
        $this->mockDBPageQueryBehaviour($this->pageData);
        $this->mockDBContentQueryBehaviour($this->pageContents);

        // execute
        $result = $this->subject->buildSearchIndexEntryFromPageId($this->pageId);
        if (!array_key_exists($dokType, DatabaseUtils::VALID_DOKTYPE_MAPPINGS)) {
            self::assertNull($result);
        } else {
            self::assertNotNull($result);
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    #[Test]
    public function buildSearchIndexEntryFromPageId_noContents(): void
    {
        // setup
        $this->mockDBPageQueryBehaviour($this->pageData);
        $this->mockDBContentQueryBehaviour([]);

        // execute && verify
        self::assertNull($this->subject->buildSearchIndexEntryFromPageId($this->pageId));
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    #[Test]
    public function buildSearchIndexEntryFromPageId(): void
    {
        // setup
        $this->mockDBPageQueryBehaviour($this->pageData);
        $this->mockDBContentQueryBehaviour($this->pageContents);

        // execute
        $result = $this->subject->buildSearchIndexEntryFromPageId($this->pageId);


        self::assertEquals(SearchIndexService::INDEX_NAME, $result[0]['indexName']);
        self::assertEquals($this->pageId, $result[0]['document']['id']);
        self::assertEquals($this->pageData['title'], $result[0]['document']['title']);
        self::assertEquals($this->pageData['slug'], $result[0]['document']['targetlink']);
        self::assertEquals('body1 body2', $result[0]['document']['preamble']);

        $jsonDecodedMetadata = json_decode($result[0]['document']['metadata']);
        $metaData = (array) $jsonDecodedMetadata;

        // assert that page modified field is equal to the formatted tstamp of content 1 as it is the most current one.
        self::assertEquals(date('Y-m-d\TH:i:s', $this->pageContents[0]['tstamp']), $metaData['modified']);
        self::assertEquals('article', $metaData['type']);
    }

    public static function generateDokTypes(): array
    {
        $validTypes = array_keys(DatabaseUtils::VALID_DOKTYPE_MAPPINGS);
        $validTypes[] = 99;
        return array_map(function($doktype) {
            return [$doktype];
        }, $validTypes);
    }

    private function mockDBPageQueryBehaviour($valueToReturn): void
    {
        $this->pagesConnectionPoolMock->method('getQueryBuilderForTable')->with('pages')->willReturn($this->pagesQueryBuilderMock);
        $this->pagesQueryBuilderMock->method('select')->with('*')->willReturnSelf();
        $this->pagesQueryBuilderMock->method('from')->with(self::anything())->willReturnSelf();
        $this->pagesQueryBuilderMock->method('where')->with(self::anything())->willReturnSelf();
        $this->pagesQueryBuilderMock->method('executeQuery')->willReturn($this->pageResultStatementMock);
        $this->pageResultStatementMock->method('fetchAssociative')->willReturn($valueToReturn);
    }

    private function mockDBContentQueryBehaviour($valueToReturn): void
    {
        $this->contentConnectionPoolMock->method('getQueryBuilderForTable')->with('tt_content')->willReturn($this->contentQueryBuilderMock);
        $this->contentQueryBuilderMock->method('select')->with('*')->willReturnSelf();
        $this->contentQueryBuilderMock->method('from')->with('tt_content')->willReturnSelf();
        $this->contentQueryBuilderMock->method('where')->with(self::anything())->willReturnSelf();
        $this->contentQueryBuilderMock->method('orderBy')->with('sorting')->willReturnSelf();
        $this->contentQueryBuilderMock->method('executeQuery')->willReturn($this->contentResultStatementMock);
        $this->contentResultStatementMock->method('fetchAllAssociative')->willReturn($valueToReturn);
    }
}
