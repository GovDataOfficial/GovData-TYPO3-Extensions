<?php

declare(strict_types=1);

namespace GdTypo3Extensions\GdSite\Command;

use Doctrine\DBAL\Exception;
use GdTypo3Extensions\GdSite\Hook\SearchIndexService;
use GdTypo3Extensions\GdSite\Hook\DatabaseUtils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Reindex extends Command
{
    private SearchIndexService $searchIndexService;

    private DatabaseUtils $databaseUtils;

    public function __construct()
    {
        parent::__construct();
        $this->searchIndexService = GeneralUtility::makeInstance(SearchIndexService::class);
        $this->databaseUtils = GeneralUtility::makeInstance(DatabaseUtils::class);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        echo "reindex command has been triggered\n";

        $this->searchIndexService->deleteAllEntries();

        try {
            $indexablePageIds = $this->databaseUtils->getAllIndexablePageIds();
        } catch (Exception $e) {
            echo "Error while querying the database for IDs of indexable pages: " . $e->getMessage() .  "\n";
            return 0;
        }

        if ($indexablePageIds) {
            foreach ($indexablePageIds as $idx => $pageId) {
                echo "Re-indexing page with ID " . $pageId . " [page " . $idx + 1 . " of " . count($indexablePageIds) . "]\n";
                try {
                    $entry = $this->databaseUtils->buildSearchIndexEntryFromPageId($pageId);
                    if ($entry) {
                        $this->searchIndexService->save($entry);
                    }
                } catch (Exception $e) {
                    echo "Error while re-indexing page with ID " . $pageId . ". Continuing with the next page. Error was:" . $e->getMessage() . "\n";
                }
            }
        } else {
            echo "There aren\'t any pages which are supposed to be included in search. Search index will be empty!\n";
        }

        return 0;
    }
}
