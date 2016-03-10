<?php
/**
 * ownCloud
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @copyright (C) 2014 ownCloud, Inc.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Search_Elastic\Controller;

use Elastica\Exception\Connection\HttpException;
use Elastica\Index;
use Elastica\Type;
use OC\AppFramework\Http;
use OCA\Search_Elastic\Db\StatusMapper;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\ApiController;

class AdminSettingsController extends APIController {

	const SERVERS = 'servers';
	const SCAN_EXTERNAL_STORAGE = 'scanExternalStorages';
	/**
	 * @var IConfig
	 */
	var $config;
	/**
	 * @var Index
	 */
	var $index;
	/**
	 * @var Index
	 */
	var $contentExtractionIndex;
	/**
	 * @var StatusMapper
	 */
	var $mapper;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 */
	public function __construct($appName, IRequest $request, IConfig $config, Index $index, Index $contentExtractionIndex, StatusMapper $mapper) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->index = $index;
		$this->contentExtractionIndex = $contentExtractionIndex;
		$this->mapper = $mapper;
	}

	/**
	 * @return JSONResponse
	 */
	public function loadServers() {
		$servers = $this->config->getAppValue($this->appName, self::SERVERS, 'localhost:9200');
		return new JSONResponse(array(self::SERVERS => $servers) );
	}

	/**
	 * @param string $servers
	 * @return JSONResponse
	 */
	public function saveServers($servers) {
		$this->config->setAppValue($this->appName, self::SERVERS, $servers);
		return new JSONResponse();
	}

	/**
	 * @return JSONResponse
	 */
	public function getScanExternalStorages() {
		$scanExternalStorages = $this->config->getAppValue($this->appName, self::SCAN_EXTERNAL_STORAGE, true);
		return new JSONResponse(array(self::SCAN_EXTERNAL_STORAGE => $scanExternalStorages) );
	}

	/**
	 * @param bool $scanExternalStorages
	 * @return JSONResponse
	 */
	public function setScanExternalStorages($scanExternalStorages) {
		$this->config->setAppValue($this->appName, self::SCAN_EXTERNAL_STORAGE, $scanExternalStorages);
		return new JSONResponse();
	}

	/**
	 * @return JSONResponse
	 */
	public function checkStatus() {
		try {
			if (!$this->index->exists()) {
				return new JSONResponse(array('message' => 'Index not set up'), Http::STATUS_EXPECTATION_FAILED);
			}
			if (!$this->contentExtractionIndex->exists()) {
				return new JSONResponse(array('message' => 'Content extraction index not set up'), Http::STATUS_EXPECTATION_FAILED);
			}
			$mapping = $this->contentExtractionIndex->getMapping();
			if (!isset($mapping['file']['properties']['file']['type']) ||
				$mapping['file']['properties']['file']['type'] !== 'attachment'
			) {
				return new JSONResponse(array('message' => 'Content extraction index requires attachment type. Did you install the elasticsearch mapper attachments plugin?'), Http::STATUS_EXPECTATION_FAILED);
			}
		} catch (HttpException $ex) {
			$servers = $this->config->getAppValue($this->appName, self::SERVERS, 'localhost:9200');
			return new JSONResponse(array('message' => 'Elasticsearch Server unreachable at '.$servers), Http::STATUS_SERVICE_UNAVAILABLE);
		}
		$stats = $this->index->getStats()->getData();
		$instanceId = \OC::$server->getSystemConfig()->getValue('instanceid', '');
		return new JSONResponse(['stats' => [
			'_all'    => $stats['_all'],
			'_shards' => $stats['_shards'],
			'oc_index'   => $stats['indices']["oc-$instanceId"],
		]]);
	}

	/**
	 * @return JSONResponse
	 */
	public function setup() {
		try {
			$this->setUpIndex();
			$this->setUpContentExtractionIndex();
			$this->mapper->clear();
		} catch (\Exception $e) {
			// TODO log exception
			return new JSONResponse(array('message' => $e->getMessage()), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return $this->checkStatus();
	}

	/**
	 * @return JSONResponse
	 */
	public function rescan() {
		/*
		 * FIXME we need to iterate over all files. how do we access users files in external storages?
		 * It would make more sense to iterate over all storages.
		 * For now the index will be filled by the cronjob
		// we use our own fs setup code to also set the user in the session
		$folder = $container->query('FileUtility')->setUpUserHome($userId);

		if ($folder) {

			$fileIds = $container->query('StatusMapper')->getUnindexed();

			$logger->debug('background job indexing '.count($fileIds).' files for '.$userId );

			$container->query('Client')->indexFiles($fileIds);

		}
		*/
	}

	/**
	 * WARNING: will delete the index if it exists
	 */
	function setUpIndex() {
		// the number of shards and replicas should be adjusted as necessary outside of owncloud
		$this->index->create(array('index' => array('number_of_shards' => 1, 'number_of_replicas' => 0),), true);

		$type = new Type($this->index, 'file');

		$mapping = new Type\Mapping($type, array(
			'content' => array(
				'type' => 'string',
				'term_vector' => 'with_positions_offsets',
				'store' => 'yes',
			),
			'title' => array(
				'type' => 'string',
				'term_vector' => 'with_positions_offsets',
				'store' => 'yes',
			),
			'date' => array(
				'type' => 'string',
				'store' => 'yes',
			),
			'author' => array(
				'type' => 'string',
				'store' => 'yes',
			),
			'keywords' => array(
				'type' => 'string',
				'store' => 'yes',
			),
			'content_type' => array(
				'type' => 'string',
				'store' => 'yes',
			),
			'content_length' => array(
				'type' => 'long',
				'store' => 'yes',
			),
			'language' => array(
				'type' => 'string',
				'store' => 'yes',
			),
		));
		$type->setMapping($mapping);
	}

	/**
	 * WARNING: will delete the index if it exists
	 */
	function setUpContentExtractionIndex() {
		// the number of shards and replicas should be adjusted as necessary outside of owncloud
		$this->contentExtractionIndex->create(array('index' => array('number_of_shards' => 1, 'number_of_replicas' => 0),), true);

		$type = new Type($this->contentExtractionIndex, 'file');

		$mapping = new Type\Mapping($type, array(
			'file' => array(
				'type' => 'attachment',
				'fields' => [
					'content' => array(
						'type' => 'string',
						'store' => true,
					),
					'title' => array(
						'store' => 'yes',
					),
					'date' => array(
						'store' => 'yes',
					),
					'author' => array(
						'store' => 'yes',
					),
					'keywords' => array(
						'store' => 'yes',
					),
					'content_type' => array(
						'store' => 'yes',
					),
					'content_length' => array(
						'store' => 'yes',
					),
					'language' => array(
						'store' => 'yes',
					),
				],
			),
		));
		// do not store file in es
		$mapping->setParam('_source', array('excludes' => array('file.content')));
		$type->setMapping($mapping);
	}
}