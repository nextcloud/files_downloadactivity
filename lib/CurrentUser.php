<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesDownloadActivity;

use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;

class CurrentUser {
	protected ?string $identifier = null;
	/** @var string|false|null */
	protected $sessionUser;

	/**
	 * @param IUserSession $userSession
	 * @param IRequest $request
	 * @param IManager $shareManager
	 */
	public function __construct(
		protected IUserSession $userSession,
		protected IRequest $request,
		protected IManager $shareManager,
	) {
		$this->sessionUser = false;
	}

	/**
	 * Get an identifier for the user, session or token
	 * @return string
	 */
	public function getUserIdentifier(): string {
		if ($this->identifier === null) {
			$this->identifier = $this->getUID();

			if ($this->identifier === null) {
				$this->identifier = $this->getCloudIDFromToken();

				if ($this->identifier === null) {
					// Nothing worked, fallback to empty string
					$this->identifier = '';
				}
			}
		}

		return $this->identifier;
	}

	/**
	 * Get the current user from the session
	 * @return string|null
	 */
	public function getUID(): ?string {
		if ($this->sessionUser === false) {
			$user = $this->userSession->getUser();
			if ($user instanceof IUser) {
				$this->sessionUser = $user->getUID();
			} else {
				$this->sessionUser = null;
			}
		}

		return $this->sessionUser;
	}

	/**
	 * Get the cloud ID from the sharing token
	 * @return string|null
	 */
	protected function getCloudIDFromToken(): ?string {
		if (!empty($this->request->server['PHP_AUTH_USER'])) {
			$token = $this->request->server['PHP_AUTH_USER'];
			try {
				$share = $this->shareManager->getShareByToken($token);
				if ($share->getShareType() === IShare::TYPE_REMOTE) {
					return $share->getSharedWith();
				}
			} catch (ShareNotFound $e) {
				// No share, use the fallback
			}
		}

		return null;
	}
}
