<?php

namespace GdTypo3Extensions\GdSite\Tests\Unit\Command;

use GdTypo3Extensions\GdSite\Command\Reindex;
use GdTypo3Extensions\GdSite\Hook\DatabaseUtils;
use GdTypo3Extensions\GdSite\Hook\SearchIndexService;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

require_once __DIR__ . "/../../../Command/Reindex.php";
require_once __DIR__ . "/../../../Classes/Hook/DatabaseUtils.php";
require_once __DIR__ . "/../../../Classes/Hook/SearchIndexService.php";

class ReindexTest extends UnitTestCase
{
    public ?Reindex $subject = null;
    private $databaseUtilsMock;
    private $searchIndexServiceMock;
    private $inputMock;
    private $outputMock;


    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->searchIndexServiceMock = $this->createMock(SearchIndexService::class);
        $this->databaseUtilsMock = $this->createMock(DatabaseUtils::class);
        GeneralUtility::addInstance(SearchIndexService::class, $this->searchIndexServiceMock);
        GeneralUtility::addInstance(DatabaseUtils::class, $this->databaseUtilsMock);

        $this->inputMock = $this->createMock(InputInterface::class);
        $this->outputMock = $this->createMock(OutputInterface::class);

        $this->subject = new Reindex();
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    public static function booleans(): array
    {
        return [[true], [false]];
    }

    /**
     * Should reindex the given page ids. If there's a db error while querying the indexable page ids, don't do anything.
     * @throws Exception
     */
    #[Test]
    #[DataProvider('booleans')]
    public function execute(bool $dbError)
    {
        if ($dbError) {
            $this->searchIndexServiceMock->method('deleteAllEntries');
            $this->databaseUtilsMock->method('getAllIndexablePageIds')->willThrowException(new \Doctrine\DBAL\Exception());
            $this->databaseUtilsMock->expects(self::never())->method('buildSearchIndexEntryFromPageId');
            $this->searchIndexServiceMock->expects(self::never())->method('save');
        } else {
            $this->searchIndexServiceMock
                ->expects($this->once())
                ->method('deleteAllEntries')
                ->willReturn(new Response());

            $indexablePageIds = [1, 2, 3];

            $this->databaseUtilsMock
                ->expects($this->once())
                ->method('getAllIndexablePageIds')
                ->willReturn($indexablePageIds);

            // Attention: I couldn't get this test running with buildSearchIndexEntryFromPageId expecting each time
            // a different argument and returning a different searchIndexEntry for each page Id
            $searchIndexEntry = [['indexName' => 'govdata-liferay-da'], 'document' => ['id' => 1, 'title' => 'Tittel']];
            $this->databaseUtilsMock
                ->expects($this->exactly(3))
                ->method('buildSearchIndexEntryFromPageId')
                ->willReturn($searchIndexEntry);

            $this->searchIndexServiceMock
                ->expects($this->exactly(3))
                ->method('save')
                ->with($searchIndexEntry);

            $this->subject->execute($this->inputMock, $this->outputMock);
        }
    }
}
