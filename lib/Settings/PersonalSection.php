<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\SuperCalPlan\Settings;

use OCA\SuperCalPlan\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class PersonalSection implements IIconSection {
	public function __construct(private IURLGenerator $urlGenerator) {
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'supercalplan-sidebar-icon.svg');
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return 'SuperCalPlan';
	}

	public function getPriority(): int {
		return 45;
	}
}
