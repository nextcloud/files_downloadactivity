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
use OCA\FilesDownloadActivity\CurrentUser;
use OCP\Activity\IManager;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;

class Listener {
	/** @var IRequest */
	protected $request;
	/** @var IManager */
	protected $activityManager;
	/** @var IURLGenerator */
	protected $urlGenerator;
	/** @var IRootFolder */
	protected $rootFolder;
	/** @var CurrentUser */
	protected $currentUser;
	/** @var ILogger */
	protected $logger;

	/**
	 * @param IRequest $request
	 * @param IManager $activityManager
	 * @param IURLGenerator $urlGenerator
	 * @param IRootFolder $rootFolder
	 * @param CurrentUser $currentUser
	 * @param ILogger $logger
	 */
	public function __construct(IRequest $request, IManager $activityManager, IURLGenerator $urlGenerator, IRootFolder $rootFolder, CurrentUser $currentUser, ILogger $logger) {
		$this->request = $request;
		$this->activityManager = $activityManager;
		$this->urlGenerator = $urlGenerator;
		$this->rootFolder = $rootFolder;
		$this->currentUser = $currentUser;
		$this->logger = $logger;
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

		if ($this->currentUser->getUID() === null) {
			// User is not logged in, this download is handled by the files_sharing app
			return;
		}

		try {
			list($filePath, $owner, $fileId, $isDir) = $this->getSourcePathAndOwner($path);
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
		} else if ($this->request->isUserAgent([IRequest::USER_AGENT_CLIENT_ANDROID, IRequest::USER_AGENT_CLIENT_IOS])) {
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
			$this->logger->logException($e, [
				'app' => 'files_downloadactivity',
			]);
		} catch (\BadMethodCallException $e) {
			$this->logger->logException($e, [
				'app' => 'files_downloadactivity',
			]);
		}
	}

	private function getUserIP() {
	    $client = (isset($_SERVER['HTTP_CLIENT_IP'])?$_SERVER['HTTP_CLIENT_IP']:false);
	    $forward = (isset($_SERVER['HTTP_X_FORWARDED_FOR'])?$_SERVER['HTTP_X_FORWARDED_FOR']:false);
	    $remote  = $_SERVER['REMOTE_ADDR'];

		if (strpos($forward, ',') > 0) {
            $forward = explode(",",$forward);
            $forward = trim($forward[0]);
        }

	    if (filter_var($client, FILTER_VALIDATE_IP)) {
	        $ip = $client;
	    } elseif(filter_var($forward, FILTER_VALIDATE_IP)) {
	        $ip = $forward;
	    } else {
	        $ip = $remote;
	    }

	    return $ip;
	}

	/**
	 * Store the update hook events
	 * @param string $path Path of the file that has been read
	 */
	public function shareAccessFile($params) {
		$file = \OC::$server->getRootFolder()->getById($params['itemSource']);

		foreach($file as $k => $v) {
			$fileinfo = $v->getFileInfo();
			$path = $fileinfo->getPath();
		}
		// Do not add activities for .part-files
		if (substr($path, -5) === '.part') {
			return;
		}

		try {
			$client = 'web';
			if ($this->request->isUserAgent([IRequest::USER_AGENT_CLIENT_DESKTOP])) {
				$client = 'desktop';
			} else if ($this->request->isUserAgent([IRequest::USER_AGENT_CLIENT_ANDROID, IRequest::USER_AGENT_CLIENT_IOS])) {
				$client = 'mobile';
			}
			// Fix for setIncognitoMode being called in the ShareController Class
			// Do not call $this->currentUser->getUID() prior to this, will return nothing afterwards
			\OC_User::setIncognitoMode(false);

			if ($user = \OC::$server->getUserSession()->getUser()) {
				$user = $user->getUID();
			}

			$data = [
				'path'		=> $path,
				'token'		=> $params['token'],
				'code'		=> $params['errorCode'],
				'ip'		=> $this->getUserIP(),
				'user'		=> $user,
				'client'	=> $client,
				'owner'		=> $fileinfo->getOwner()->getUID(),
				'owner_folder'		=> $this->rootFolder->getUserFolder($fileinfo->getOwner()->getUID())->getPath(),
				'file_id'	=> $fileinfo->getId(),
				'is_dir'	=> $fileinfo instanceof Folder,
				'subject'	=> Provider::SUBJECT_SHARED_FILE_ACCESSED,
				'link'		=> []
			];

			// Fix for setIncognitoMode being called in the ShareController Class
			\OC_User::setIncognitoMode(true);

			$data['path'] = substr($data['path'], strlen($data['owner_folder']));

			if ($data['is_dir']) {
				$data['subject'] = Provider::SUBJECT_SHARED_FOLDER_ACCESSED;
				$data['link'] = [
					'dir' => $data['path'],
				];
			} else {
				$data['link'] = [
					'dir'		=> (substr_count($data['path'], '/') === 1) ? '/' : dirname($data['path']),
					'scrollto'	=> basename($data['path'])
				];
			}

			if (empty($data['user'])) {
				$data['user'] = $data['ip'];
			}

			$data['subject_params'] = [[$data['file_id'] => $data['path']], $data['user'], $data['client'], $data['token'], $data['code']];
		} catch (NotFoundException $e) {
			return;
		} catch (InvalidPathException $e) {
			return;
		}

		try {
			$event = $this->activityManager->generateEvent();
			$event->setApp('files_downloadactivity')
					->setType('public_links')
					->setTimestamp(time())
					->setSubject($data['subject'], $data['subject_params'])
					->setObject('files', $data['file_id'], $data['path'])
					->setLink($this->urlGenerator->linkToRouteAbsolute('files.view.index', $data['link']))
					->setAffectedUser($data['owner']);

			if ($data['owner'] !== $data['user']) {
				$event->setAuthor($data['user']);
			}
			$this->activityManager->publish($event);
		} catch (\InvalidArgumentException $e) {
			$this->logger->logException($e, [
				'app' => 'files_downloadactivity',
			]);
		} catch (\BadMethodCallException $e) {
			$this->logger->logException($e, [
				'app' => 'files_downloadactivity',
			]);
		}
	}

	/**
	 * @param string $path
	 * @return array
	 * @throws NotFoundException
	 * @throws InvalidPathException
	 */
	protected function getSourcePathAndOwner($path) {
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
				// throw new NotFoundException($node->getPath());
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
?>