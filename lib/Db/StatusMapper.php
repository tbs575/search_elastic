<?php
/**
 * ownCloud
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @copyright (C) 2014-2016 ownCloud, Inc.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Search_Elastic\Db;

use OCA\Files_Sharing\SharedStorage;
use OCA\Search_Elastic\SearchElasticConfigService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\Mapper;
use OCP\Files\Folder;
use OCP\IDb;
use OCP\ILogger;

class StatusMapper extends Mapper {

	/**
	 * @var ILogger
	 */
	private $logger;

	/**
	 * @var SearchElasticConfigService
	 */
	private $config;

	/**
	 * StatusMapper constructor.
	 *
	 * @param IDb $db
	 * @param SearchElasticConfigService $config
	 * @param ILogger $logger
	 */
	public function __construct(
		IDb $db,
		SearchElasticConfigService $config,
		ILogger $logger
	) {
		parent::__construct($db, 'search_elastic_status', '\OCA\Search_Elastic\Db\Status');
		$this->logger = $logger;
		$this->config = $config;
	}

	/**
	 * Deletes a status from the table
	 * @param Entity $status the status that should be deleted
	 * @return Entity the deleted entity
	 */
	public function delete(Entity $status) {
		$sql = 'DELETE FROM `' . $this->tableName . '` WHERE `fileid` = ?';
		$stmt = $this->execute($sql, [$status->getFileId()]);
		$stmt->closeCursor();
		return $status;
	}
	/**
	 * Deletes a status from the table
	 * @param array $ids the fileids whose status should be deleted
	 * @return int the number of affected rows
	 */
	public function deleteIds(array $ids) {
		if (empty($ids)) {
			return 0;
		}
		$values = '?';
		for ($i = 1; $i < \count($ids); $i++) {
			$values .= ',?';
		}

		$sql = "DELETE FROM `{$this->tableName}` WHERE `fileid` IN ($values)";
		$stmt = $this->execute($sql, $ids);

		return $stmt->rowCount();
	}

	/**
	 * Clears all status entries from the table
	 */
	public function clear() {
		$this->execute('DELETE FROM `' . $this->tableName . '`');
	}

	/**
	 * Creates a new entry in the db from an entity
	 * @param Entity $entity the entity that should be created
	 * @return Entity the saved entity with the set id
	 */
	public function insert(Entity $entity) {
		// get updated fields to save, fields have to be set using a setter to
		// be saved
		$properties = $entity->getUpdatedFields();
		$values = '';
		$columns = '';
		$params = [];

		// build the fields
		$i = 0;
		foreach ($properties as $property => $updated) {
			$column = $entity->propertyToColumn($property);
			$getter = 'get' . \ucfirst($property);

			$columns .= '`' . $column . '`';
			$values .= '?';

			// only append colon if there are more entries
			if ($i < \count($properties)-1) {
				$columns .= ',';
				$values .= ',';
			}

			\array_push($params, $entity->$getter());
			$i++;
		}

		$sql = 'INSERT INTO `' . $this->tableName . '`(' .
			$columns . ') VALUES(' . $values . ')';

		$this->execute($sql, $params);

		return $entity;
	}

	/**
	 * Updates an entry in the db from a status
	 * @param Entity $entity the status that should be created
	 * @return Entity|null
	 * @throws \InvalidArgumentException if entity has no id
	 */
	public function update(Entity $entity) {
		// if entity wasn't changed it makes no sense to run a db query
		$properties = $entity->getUpdatedFields();
		if (\count($properties) === 0) {
			return $entity;
		}

		// entity needs an id
		$fileId = $entity->getFileId();
		if ($fileId === null) {
			throw new \InvalidArgumentException(
				'Entity which should be updated has no fileId');
		}

		// get updated fields to save, fields have to be set using a setter to
		// be saved
		// don't update the fileId field
		unset($properties['fileId']);

		$columns = '';
		$params = [];

		// build the fields
		$i = 0;
		foreach ($properties as $property => $updated) {
			$column = $entity->propertyToColumn($property);
			$getter = 'get' . \ucfirst($property);

			$columns .= '`' . $column . '` = ?';

			// only append colon if there are more entries
			if ($i < \count($properties)-1) {
				$columns .= ',';
			}

			\array_push($params, $entity->$getter());
			$i++;
		}

		$sql = 'UPDATE `' . $this->tableName . '` SET ' .
			$columns . ' WHERE `fileid` = ?';
		\array_push($params, $fileId);

		$stmt = $this->execute($sql, $params);
		$stmt->closeCursor();

		return $entity;
	}

	/**
	 * get the list of all files that need a metadata reindexing
	 * @param Folder $home the home folder used to deduce the storages
	 *
	 * @return array
	 */
	public function findFilesWhereMetadataChanged(Folder $home) {
		$sql = "
			SELECT `*PREFIX*filecache`.`fileid`
			FROM `*PREFIX*filecache`
			LEFT JOIN `{$this->tableName}`
			ON `*PREFIX*filecache`.`fileid` = `{$this->tableName}`.`fileid`
			WHERE `storage` = ?
			AND `status` = ?
		";
		return $this->findNodesWithStatus($home, $sql, Status::STATUS_METADATA_CHANGED);
	}
	/**
	 * get the list of all files that need a full reindexing with content extraction
	 * @param Folder $home the home folder used to deduce the storages
	 *
	 * @return array
	 */
	public function findFilesWhereContentChanged(Folder $home) {
		$sql = "
			SELECT `*PREFIX*filecache`.`fileid`
			FROM `*PREFIX*filecache`
			LEFT JOIN `{$this->tableName}`
			ON `*PREFIX*filecache`.`fileid` = `{$this->tableName}`.`fileid`
			WHERE `storage` = ?
			AND ( `status` IS NULL OR `status` = ? )
		";
		return $this->findNodesWithStatus($home, $sql, Status::STATUS_NEW);
	}
	/**
	 * get the list of all unindexed files for the user
	 * @param Folder $home the home folder used to deduce the storages
	 * @param string $sql
	 * @param string $status
	 *
	 * @return array
	 */
	public function findNodesWithStatus(Folder $home, $sql, $status) {
		$home->getMountPoint();
		$mounts = \OC::$server->getMountManager()->findIn($home->getPath());
		$mount = $home->getMountPoint();
		$files = [];
		if (!\in_array($mount, $mounts)) {
			$mounts[] = $mount;
		}

		// should we ORDER BY `mtime` DESC to index recent files first?
		// how will they affect query time for large filecaches?

		$query = $this->db->prepareQuery($sql);

		foreach ($mounts as $mount) {
			$storage = $mount->getStorage();

			// skip shared storages, they must be indexed in the context of
			// their owner to prevent marking files as vanished
			if ($storage->instanceOfStorage(SharedStorage::class)) {
				continue;
			}

			//only index external files if the admin enabled it

			if ($this->config->getScanExternalStorageFlag() || $storage->isLocal()) {
				$cache = $storage->getCache();
				$numericId = $cache->getNumericStorageId();

				$result = $query->execute([$numericId, $status]);

				while ($row = $result->fetchRow()) {
					$files[] = $row['fileid'];
				}
			}
		}
		return $files;
	}

	public function countIndexed() {
		$sql = "
			SELECT count(*) AS `count_indexed` FROM `*PREFIX*search_elastic_status` WHERE `status` = ?
		";
		$query = $this->db->prepareQuery($sql);
		$result = $query->execute([Status::STATUS_INDEXED]);
		$row = $result->fetchRow();
		return (int)$row['count_indexed'];
	}

	/**
	 * @param int $fileId
	 * @return Entity
	 */
	public function getOrCreateFromFileId($fileId) {
		$sql = '
			SELECT `fileid`, `status`, `message`
			FROM ' . $this->tableName . '
			WHERE `fileid` = ?
		';
		try {
			return $this->findEntity($sql, [$fileId]);
		} catch (DoesNotExistException $e) {
			$status = new Status($fileId, Status::STATUS_NEW);
			return $this->insert($status);
		}
	}

	public function markNew(Entity $status) {
		$status->setStatus(Status::STATUS_NEW);
		return $this->update($status);
	}

	public function markMetadataChanged(Entity $status) {
		$status->setStatus(Status::STATUS_METADATA_CHANGED);
		return $this->update($status);
	}

	public function markIndexed(Entity $status) {
		$status->setStatus(Status::STATUS_INDEXED);
		return $this->update($status);
	}

	public function markSkipped(Entity $status, $message = null) {
		$status->setStatus(Status::STATUS_SKIPPED);
		$status->setMessage($message);
		return $this->update($status);
	}

	public function markUnIndexed(Entity $status) {
		$status->setStatus(Status::STATUS_UNINDEXED);
		return $this->update($status);
	}

	public function markVanished(Entity $status) {
		$status->setStatus(Status::STATUS_VANISHED);
		return $this->update($status);
	}

	public function markError(Entity $status, $message = null) {
		$status->setStatus(Status::STATUS_ERROR);
		$status->setMessage($message);
		return $this->update($status);
	}

	/**
	 * @return int[]
	 */
	public function getDeleted() {
		$files = [];

		$query = $this->db->prepareQuery('
			SELECT `' . $this->tableName . '`.`fileid`
			FROM `' . $this->tableName . '`
			LEFT JOIN `*PREFIX*filecache`
				ON `*PREFIX*filecache`.`fileid` = `' . $this->tableName . '`.`fileid`
			WHERE `*PREFIX*filecache`.`fileid` IS NULL
		');

		$result = $query->execute();

		while ($row = $result->fetchRow()) {
			$files[] = $row['fileid'];
		}

		return $files;
	}
}
