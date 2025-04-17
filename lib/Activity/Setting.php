<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesDownloadActivity\Activity;

use OCP\Activity\ActivitySettings;
use OCP\IL10N;

class Setting extends ActivitySettings {
	public function __construct(
		protected IL10N $l,
	) {
	}

	/**
	 * @return string Lowercase a-z and underscore only identifier
	 * @since 11.0.0
	 */
	public function getIdentifier(): string {
		return 'file_downloaded';
	}

	/**
	 * @return string A translated string
	 * @since 11.0.0
	 */
	public function getName(): string {
		return $this->l->t('A local shared file or folder was <strong>downloaded</strong>');
	}

	/**
	 * @return string Lowercase a-z and underscore only group identifier
	 * @since 20.0.0
	 */
	public function getGroupIdentifier() {
		return 'sharing';
	}

	/**
	 * @return string A translated string for the settings group
	 * @since 20.0.0
	 */
	public function getGroupName() {
		return $this->l->t('Sharing');
	}

	/**
	 * @return int whether the filter should be rather on the top or bottom of
	 *             the admin section. The filters are arranged in ascending order of the
	 *             priority values. It is required to return a value between 0 and 100.
	 * @since 11.0.0
	 */
	public function getPriority(): int {
		return 21;
	}

	/**
	 * @return bool True when the option can be changed for the stream
	 * @since 11.0.0
	 */
	public function canChangeStream(): bool {
		return true;
	}

	/**
	 * @return bool True when the option can be changed for the stream
	 * @since 11.0.0
	 */
	public function isDefaultEnabledStream(): bool {
		return true;
	}

	/**
	 * @return bool True when the option can be changed for the mail
	 * @since 11.0.0
	 */
	public function canChangeMail(): bool {
		return true;
	}

	/**
	 * @return bool True when the option can be changed for the stream
	 * @since 11.0.0
	 */
	public function isDefaultEnabledMail(): bool {
		return false;
	}
}
