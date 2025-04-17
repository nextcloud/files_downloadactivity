<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesDownloadActivity\Activity;

use OC\Files\Filesystem;
use OCA\FilesDownloadActivity\CurrentUser;
use OCP\Activity\IManager;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class Listener {
	public function __construct(
		protected IRequest $request,
		protected IManager $activityManager,
		protected IURLGenerator $urlGenerator,
		protected IRootFolder $rootFolder,
		protected CurrentUser $currentUser,
		protected LoggerInterface $logger,
	) {
	}

	/**
	 * Store the update hook events
	 * @param string $path Path of the file that has been read
	 */
	public function readFile(string $path): void {
		// Do not add activities for .part-files
		if (substr($path, -5) === '.part') {
			return;
		}

		if ($this->currentUser->getUID() === null) {
			// User is not logged in, this download is handled by the files_sharing app
			return;
		}

		try {
			[$filePath, $owner, $fileId, $isDir] = $this->getSourcePathAndOwner($path);
		} catch (NotFoundException $e) {
			return;
		} catch (InvalidPathException $e) {
			return;
		}

		if ($this->currentUser->getUID() === $owner) {
			// Could not find the file for the owner ...
			return;
		}

		$client = 'web';
		if ($this->request->isUserAgent([IRequest::USER_AGENT_CLIENT_DESKTOP])) {
			$client = 'desktop';
		} elseif ($this->request->isUserAgent([IRequest::USER_AGENT_CLIENT_ANDROID, IRequest::USER_AGENT_CLIENT_IOS])) {
			$client = 'mobile';
		}
		$subjectParams = [[$fileId => $filePath], $this->currentUser->getUserIdentifier(), $client];

		if ($isDir) {
			$subject = Provider::SUBJECT_SHARED_FOLDER_DOWNLOADED;
			$linkData = [
				'dir' => $filePath,
			];
		} else {
			$subject = Provider::SUBJECT_SHARED_FILE_DOWNLOADED;
			$parentDir = (substr_count($filePath, '/') === 1) ? '/' : dirname($filePath);
			$fileName = basename($filePath);
			$linkData = [
				'dir' => $parentDir,
				'scrollto' => $fileName,
			];
		}

		try {
			$event = $this->activityManager->generateEvent();
			$event->setApp('files_downloadactivity')
				->setType('file_downloaded')
				->setAffectedUser($owner)
				->setAuthor($this->currentUser->getUID())
				->setTimestamp(time())
				->setSubject($subject, $subjectParams)
				->setObject('files', $fileId, $filePath)
				->setLink($this->urlGenerator->linkToRouteAbsolute('files.view.index', $linkData));
			$this->activityManager->publish($event);
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), $e->getTrace());
		} catch (\BadMethodCallException $e) {
			$this->logger->error($e->getMessage(), $e->getTrace());
		}
	}

	/**
	 * @param string $path
	 * @return array
	 * @throws NotFoundException
	 * @throws InvalidPathException
	 */
	protected function getSourcePathAndOwner(string $path): array {
		$currentUserId = $this->currentUser->getUID();
		$userFolder = $this->rootFolder->getUserFolder($currentUserId);
		$node = $userFolder->get($path);
		$owner = $node->getOwner()->getUID();

		if ($owner !== $currentUserId) {
			$storage = $node->getStorage();
			if (!$storage->instanceOfStorage('OCA\Files_Sharing\External\Storage')) {
				Filesystem::initMountPoints($owner);
			} else {
				// Probably a remote user, let's try to at least generate activities
				// for the current user
				$owner = $currentUserId;
			}

			$ownerFolder = $this->rootFolder->getUserFolder($owner);
			$nodes = $ownerFolder->getById($node->getId());

			if (empty($nodes)) {
				throw new NotFoundException($node->getPath());
			}

			$node = $nodes[0];
			$path = substr($node->getPath(), strlen($ownerFolder->getPath()));
		}

		return [
			$path,
			$owner,
			$node->getId(),
			$node instanceof Folder
		];
	}
}
