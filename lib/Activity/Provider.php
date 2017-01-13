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

use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class Provider implements IProvider {

	/** @var IFactory */
	protected $languageFactory;

	/** @var IL10N */
	protected $l;

	/** @var IURLGenerator */
	protected $url;

	/** @var IManager */
	protected $activityManager;

	/** @var IUserManager */
	protected $userManager;

	/** @var array */
	protected $displayNames = [];

	const SUBJECT_SHARED_FILE_DOWNLOADED = 'shared_file_downloaded';
	const SUBJECT_SHARED_FOLDER_DOWNLOADED = 'shared_folder_downloaded';

	/**
	 * @param IFactory $languageFactory
	 * @param IURLGenerator $url
	 * @param IManager $activityManager
	 * @param IUserManager $userManager
	 */
	public function __construct(IFactory $languageFactory, IURLGenerator $url, IManager $activityManager, IUserManager $userManager) {
		$this->languageFactory = $languageFactory;
		$this->url = $url;
		$this->activityManager = $activityManager;
		$this->userManager = $userManager;
	}

	/**
	 * @param string $language
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parse($language, IEvent $event, IEvent $previousEvent = null) {
		if ($event->getApp() !== 'files_downloadactivity') {
			throw new \InvalidArgumentException();
		}

		$this->l = $this->languageFactory->get('files_downloadactivity', $language);

		if ($this->activityManager->isFormattingFilteredObject()) {
			try {
				return $this->parseShortVersion($event);
			} catch (\InvalidArgumentException $e) {
				// Ignore and simply use the long version...
			}
		}

		return $this->parseLongVersion($event);
	}

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parseShortVersion(IEvent $event) {
		$parsedParameters = $this->getParsedParameters($event);
		$params = $event->getSubjectParameters();

		if ($params[2] === 'desktop') {
			$event->setParsedSubject($this->l->t('Downloaded by %s (via desktop)', $parsedParameters['actor']['name']))
				->setRichSubject($this->l->t('Downloaded by {actor} (via desktop)'), [
					'actor' => $parsedParameters['actor'],
				]);
		} else if ($params[2] === 'mobile') {
			$event->setParsedSubject($this->l->t('Downloaded by %s (via mobile)', $parsedParameters['actor']['name']))
				->setRichSubject($this->l->t('Downloaded by {actor} (via mobile)'), [
					'actor' => $parsedParameters['actor'],
				]);
		} else {
			$event->setParsedSubject($this->l->t('Downloaded by %s (via web)', $parsedParameters['actor']['name']))
				->setRichSubject($this->l->t('Downloaded by {actor} (via web)'), [
					'actor' => $parsedParameters['actor'],
				]);
		}

		$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/share.svg')));
		return $event;
	}

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parseLongVersion(IEvent $event) {
		$parsedParameters = $this->getParsedParameters($event);
		$params = $event->getSubjectParameters();

		if ($event->getSubject() === self::SUBJECT_SHARED_FOLDER_DOWNLOADED) {
			if ($params[2] === 'desktop') {
				$event->setParsedSubject($this->l->t('Shared file %1$s was downloaded by %2$s via the desktop client', [
						$parsedParameters['file']['path'],
						$parsedParameters['actor']['name'],
					]))
					->setRichSubject($this->l->t('Shared file {file} was downloaded by {actor} via the desktop client'), $parsedParameters);
			} else if ($params[2] === 'mobile') {
				$event->setParsedSubject($this->l->t('Shared file %1$s was downloaded by %2$s via the mobile client', [
						$parsedParameters['file']['path'],
						$parsedParameters['actor']['name'],
					]))
					->setRichSubject($this->l->t('Shared file {file} was downloaded by {actor} via the mobile client'), $parsedParameters);
			} else {
				$event->setParsedSubject($this->l->t('Shared file %1$s was downloaded by %2$s via the web interface', [
						$parsedParameters['file']['path'],
						$parsedParameters['actor']['name'],
					]))
					->setRichSubject($this->l->t('Shared file {file} was downloaded by {actor} via the web interface'), $parsedParameters);
			}
		} else if ($event->getSubject() === self::SUBJECT_SHARED_FILE_DOWNLOADED) {
			if ($params[2] === 'desktop') {
				$event->setParsedSubject($this->l->t('Shared file %1$s was downloaded by %2$s via the desktop client', [
						$parsedParameters['file']['path'],
						$parsedParameters['actor']['name'],
					]))
					->setRichSubject($this->l->t('Shared file {file} was downloaded by {actor} via the desktop client'), $parsedParameters);
			} else if ($params[2] === 'mobile') {
				$event->setParsedSubject($this->l->t('Shared file %1$s was downloaded by %2$s via the mobile client', [
						$parsedParameters['file']['path'],
						$parsedParameters['actor']['name'],
					]))
					->setRichSubject($this->l->t('Shared file {file} was downloaded by {actor} via the mobile client'), $parsedParameters);
			} else {
				$event->setParsedSubject($this->l->t('Shared file %1$s was downloaded by %2$s via the web interface', [
						$parsedParameters['file']['path'],
						$parsedParameters['actor']['name'],
					]))
					->setRichSubject($this->l->t('Shared file {file} was downloaded by {actor} via the web interface'), $parsedParameters);
			}
		} else {
			throw new \InvalidArgumentException();
		}

		$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/share.svg')));
		return $event;
	}

	protected function getParsedParameters(IEvent $event) {
		$subject = $event->getSubject();
		$parameters = $event->getSubjectParameters();

		switch ($subject) {
			case self::SUBJECT_SHARED_FOLDER_DOWNLOADED:
			case self::SUBJECT_SHARED_FILE_DOWNLOADED:
				$id = key($parameters[0]);
				return [
					'file' => $this->generateFileParameter($id, $parameters[0][$id]),
					'actor' => $this->generateUserParameter($parameters[1]),
				];
		}
		return [];
	}

	/**
	 * @param int $id
	 * @param string $path
	 * @return array
	 */
	protected function generateFileParameter($id, $path) {
		return [
			'type' => 'file',
			'id' => $id,
			'name' => basename($path),
			'path' => $path,
			'link' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $id]),
		];
	}

	/**
	 * @param string $uid
	 * @return array
	 */
	protected function generateUserParameter($uid) {
		if (!isset($this->displayNames[$uid])) {
			$this->displayNames[$uid] = $this->getDisplayName($uid);
		}

		return [
			'type' => 'user',
			'id' => $uid,
			'name' => $this->displayNames[$uid],
		];
	}

	/**
	 * @param string $uid
	 * @return string
	 */
	protected function getDisplayName($uid) {
		$user = $this->userManager->get($uid);
		if ($user instanceof IUser) {
			return $user->getDisplayName();
		} else {
			return $uid;
		}
	}
}
