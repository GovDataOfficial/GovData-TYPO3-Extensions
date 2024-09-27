<?php
namespace GdTypo3Extensions\GdSite\Hook;

use GuzzleHttp\Client;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Log\Logger;

class SearchIndexService {

    public const PATH_INDEX_QUEUE = "/index-queue";

    public const INDEX_NAME = "govdata-cms-de";
    public const CONTENT_TYPE_HEADER_NAME =  "Content-Type";
    public const CONTENT_TYPE = "application/json";
    public const MANDANT_HEADER_NAME = "X-SP-Mandant";
    public const INDEX_MANDANT = "1";

    private $logger;
    private $serviceFacadeUrl;
    private $client;

    public function __construct()
    {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->serviceFacadeUrl = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['gd-site']['servicefacade_url'];
        $this->username = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['gd-site']['servicefacade_username'];
        $this->password = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['gd-site']['servicefacade_password'];

        $this->client = new Client([
            'base_uri' => $this->serviceFacadeUrl,
            'auth' => [$this->username, $this->password],
            'headers' => [
                self::CONTENT_TYPE_HEADER_NAME => self::CONTENT_TYPE,
                self::MANDANT_HEADER_NAME => self::INDEX_MANDANT
            ]
        ]);
    }

    public function save(array $data)
    {
        $this->logger->debug('save(): Start');

        $response = $this->client->post(self::PATH_INDEX_QUEUE, [
            'json' => $data
        ]);

        if ($response->getStatusCode() === 201) {
            $this->logger->debug('Successfully saved document: ', $data);
        }
        else {
            $this->logger->error('Could not save document: ', [
                'id' => $data['id'],
                'status' => $response->getStatusCode(),
                'response' => $response->getBody()->getContents()
            ]);
        }
        $this->logger->debug('save(): End');
        return $response;
    }

    public function delete(array $data, $pageId)
    {
        $this->logger->debug('delete(): Start');

        $url = self::PATH_INDEX_QUEUE . '/' . $pageId;

        $response = $this->client->delete($url, [
            'json' => $data
        ]);

        if ($response->getStatusCode() === 204) {
            $this->logger->debug('Successfully deleted document: ', $data);
        }
        else {
            $this->logger->error('Could not delete document: ', [
                'id' => $data['id'],
                'status' => $response->getStatusCode(),
                'response' => $response->getBody()->getContents()
            ]);
        }
        $this->logger->debug('delete(): End');
        return $response;
    }

    public function deleteAllEntries()
    {
        $this->logger->debug('deleteAllEntries(): Start');

        $url = self::PATH_INDEX_QUEUE . '/delete-all-entries/' . self::INDEX_NAME;

        $response = $this->client->delete($url);

        if ($response->getStatusCode() === 204) {
            $this->logger->debug('Successfully deleted all entries from index: ' . self::INDEX_NAME);
        }
        else {
            $this->logger->error('Could not delete all entries from index ' . self::INDEX_NAME . ':' , [
                'status' => $response->getStatusCode(),
                'response' => $response->getBody()->getContents()
            ]);
        }
        $this->logger->debug('deleteAllEntries(): End');
        return $response;
    }
}