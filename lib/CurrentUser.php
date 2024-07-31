<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\FilesDownloadActivity;

use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;

class CurrentUser {

	/** @var IUserSession */
	protected $userSession;
	/** @var IRequest */
	protected $request;
	/** @var IManager */
	protected $shareManager;

	/** @var string */
	protected $identifier;
	/** @var string|false|null */
	protected $sessionUser;

	/**
	 * @param IUserSession $userSession
	 * @param IRequest $request
	 * @param IManager $shareManager
	 */
	public function __construct(IUserSession $userSession, IRequest $request, IManager $shareManager) {
		$this->userSession = $userSession;
		$this->request = $request;
		$this->shareManager = $shareManager;
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
				$this->sessionUser = (string) $user->getUID();
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
