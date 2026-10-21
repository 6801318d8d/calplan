<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 6801318d8d
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Install step: users of CalPlan, the app SuperCalPlan is a fork of, keep
 * their settings. Each user's CalPlan preferences (working hours, limits,
 * exclusions, plan state...) are copied to SuperCalPlan, unless SuperCalPlan
 * already has preferences for that user. CalPlan's own values stay as they
 * are. Tasks and blocks need no copy: both apps use the same data.
 */

namespace OCA\SuperCalPlan\Migration;

use OCA\SuperCalPlan\AppInfo\Application;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class ImportCalPlanSettings implements IRepairStep {
	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	public function getName(): string {
		return 'Copy the user settings of CalPlan to SuperCalPlan';
	}

	public function run(IOutput $output): void {
		$copied = 0;
		foreach ($this->calPlanUsers() as $userId) {
			if ($this->config->getUserKeys($userId, Application::APP_ID) !== []) {
				continue;
			}
			foreach ($this->config->getUserKeys($userId, Application::CALPLAN_APP_ID) as $key) {
				$value = $this->config->getUserValue($userId, Application::CALPLAN_APP_ID, $key);
				$this->config->setUserValue($userId, Application::APP_ID, $key, (string)$value);
			}
			$copied++;
		}
		$output->info('Copied the CalPlan settings of ' . $copied . ' user(s)');
	}

	/** @return string[] the users with CalPlan preferences */
	private function calPlanUsers(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('userid')
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::CALPLAN_APP_ID)));
		$result = $qb->executeQuery();
		$users = [];
		while (($row = $result->fetch()) !== false) {
			$users[] = (string)$row['userid'];
		}
		$result->closeCursor();
		return $users;
	}
}
