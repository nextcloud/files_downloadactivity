<?php
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

namespace OCA\FilesDownloadActivity\Activity;

use OC\Files\Filesystem;
use OC\Files\View;
use OCA\FilesDownloadActivity\CurrentUser;
use OCP\Activity\IManager;
use OCP\IRequest;
use OCP\IURLGenerator;

class Listener {
	/** @var IRequest */
	protected $request;
	/** @var IManager */
	protected $activityManager;
	/** @var IURLGenerator */
	protected $urlGenerator;
	/** @var CurrentUser */
	protected $currentUser;

	/**
	 * @param IRequest $request
	 * @param IManager $activityManager
	 * @param IURLGenerator $urlGenerator
	 * @param CurrentUser $currentUser
	 */
	public function __construct(IRequest $request, IManager $activityManager, IURLGenerator $urlGenerator, CurrentUser $currentUser) {
		$this->request = $request;
		$this->activityManager = $activityManager;
		$this->urlGenerator = $urlGenerator;
		$this->currentUser = $currentUser;
	}

	/**
	 * Store the update hook events
	 * @param string $path Path of the file that has been read
	 */
	public function readFile($path) {
		// Do not add activities for .part-files
		if (substr($path, -5) === '.part') {
			return;
		}

		list($filePath, $owner, $fileId, $isDir) = $this->getSourcePathAndOwner($path);
		if ($fileId === 0 || $this->currentUser->getUID() === $owner) {
			// Could not find the file for the owner ...
			return;
		}

		$client = 'web';
		if ($this->request->isUserAgent([IRequest::USER_AGENT_CLIENT_DESKTOP])) {
			$client = 'desktop';
		} else if ($this->request->isUserAgent([IRequest::USER_AGENT_CLIENT_ANDROID, IRequest::USER_AGENT_CLIENT_IOS])) {
			$client = 'mobile';
		}
		$subjectParams = [[$fileId => $filePath], $this->currentUser->getUserIdentifier(), $client];

		if ($isDir) {
			$subject = Extension::SUBJECT_SHARED_FOLDER_DOWNLOADED;
			$linkData = [
				'dir' => $filePath,
			];
		} else {
			$subject = Extension::SUBJECT_SHARED_FILE_DOWNLOADED;
			$parentDir = (substr_count($filePath, '/') === 1) ? '/' : dirname($filePath);
			$fileName = basename($filePath);
			$linkData = [
				'dir' => $parentDir,
				'scrollto' => $fileName,
			];
		}

		$event = $this->activityManager->generateEvent();
		$event->setApp('files_downloadactivity')
			->setType(Extension::TYPE_SHARE_DOWNLOADED)
			->setAffectedUser($owner)
			->setAuthor($this->currentUser->getUID())
			->setTimestamp(time())
			->setSubject($subject, $subjectParams)
			->setObject('files', $fileId, $filePath)
			->setLink($this->urlGenerator->linkToRouteAbsolute('files.view.index', $linkData));
		$this->activityManager->publish($event);
	}

	/**
	 * @copyright Copyright (c) 2016, ownCloud, Inc.
	 * @author Joas Schilling <coding@schilljs.com>
	 *
	 * @param string $path
	 * @return array
	 */
	protected function getSourcePathAndOwner($path) {
		$view = Filesystem::getView();
		$uidOwner = $view->getOwner($path);
		$fileId = 0;

		if ($uidOwner !== $this->currentUser->getUID()) {
			/** @var \OCP\Files\Storage\IStorage $storage */
			list($storage,) = $view->resolvePath($path);
			if (!$storage->instanceOfStorage('OCA\Files_Sharing\External\Storage')) {
				Filesystem::initMountPoints($uidOwner);
			} else {
				// Probably a remote user, let's try to at least generate activities
				// for the current user
				$uidOwner = $this->currentUser->getUID();
			}
		}

		$info = Filesystem::getFileInfo($path);
		if ($info !== false) {
			$ownerView = new View('/' . $uidOwner . '/files');
			$fileId = (int) $info['fileid'];
			$path = $ownerView->getPath($fileId);
			$isDir = $ownerView->is_dir($path);
		} else {
			$isDir = $view->is_dir($path);
		}

		return array($path, $uidOwner, $fileId, $isDir);
	}
}
