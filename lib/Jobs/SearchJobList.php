<?php
/**
 * ownCloud
 *
 * @author Michael Barz <mbarz@owncloud.com>
 * @copyright (C) 2019 ownCloud GmbH
 * @license ownCloud Commercial License
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see
 * <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Search_Elastic\Jobs;

use OC\BackgroundJob\JobList;
use OCP\AppFramework\QueryException;
use OCP\AutoloadNotAllowedException;
use OCP\BackgroundJob\IJob;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Class SearchJobList
 *
 * @package OCA\Search_Elastic\Jobs
 */
class SearchJobList extends JobList {
	/**
	 * Get the next job in the list
	 * and filter by class name
	 *
	 * @return IJob | null
	 */
	public function getNext() {
		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('jobs')
			->where(
				$query->expr()->lte(
					'reserved_at',
					$query->createNamedParameter(
						$this->timeFactory->getTime() - 12 * 3600,
						IQueryBuilder::PARAM_INT
					)
				)
			)
			->andWhere(
				$query->expr()->in(
					'class', [
						$query->expr()->literal(UpdateContent::class),
						$query->expr()->literal(UpdateMetadata::class)
					]
				)
			)
			->orderBy('last_checked', 'ASC')
			->setMaxResults(1);

		$update = $this->connection->getQueryBuilder();
		$update->update('jobs')
			->set(
				'reserved_at',
				$update->createNamedParameter(
					$this->timeFactory->getTime()
				)
			)
			->set(
				'last_checked',
				$update->createNamedParameter($this->timeFactory->getTime())
			)
			->where(
				$update->expr()->eq(
					'id', $update->createParameter('jobid')
				)
			)
			->andWhere(
				$update->expr()->eq(
					'reserved_at',
					$update->createParameter('reserved_at')
				)
			)
			->andWhere(
				$update->expr()->eq(
					'last_checked',
					$update->createParameter('last_checked')
				)
			);

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row) {
			$update->setParameter('jobid', $row['id']);
			$update->setParameter('reserved_at', $row['reserved_at']);
			$update->setParameter('last_checked', $row['last_checked']);
			$count = $update->execute();

			if ($count === 0) {
				// Background job already executed elsewhere, try again.
				return $this->getNext();
			}
			$job = $this->buildJob($row);

			if ($job === null) {
				// Background job from disabled app, try again.
				return $this->getNext();
			}

			return $job;
		}

		return null;
	}

	/**
	 * get the job object from a row in the db
	 *
	 * @param array $row
	 *
	 * @return IJob | null
	 */
	private function buildJob($row) {
		try {
			try {
				// Try to load the job as a service
				/**
				 * @var IJob $job
				 */
				$job = \OC::$server->query($row['class']);
			} catch (QueryException $e) {
				if (\class_exists($row['class'])) {
					$class = $row['class'];
					$job = new $class();
				} else {
					// job from disabled app or old version of an app, no need to do anything
					return null;
				}
			}

			$job->setId($row['id']);
			$job->setLastRun($row['last_run']);
			$job->setArgument(\json_decode($row['argument'], true));

			return $job;
		} catch (AutoloadNotAllowedException $e) {
			// job is from a disabled app, ignore
			return null;
		}
	}
}
