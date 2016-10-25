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

use OCP\L10N\IFactory;
use OCP\Activity\IExtension;
use OCP\Activity\IManager;
use OCP\IL10N;

class Extension implements IExtension {
	const TYPE_SHARE_DOWNLOADED = 'file_downloaded';

	const SUBJECT_SHARED_FILE_DOWNLOADED = 'shared_file_downloaded';
	const SUBJECT_SHARED_FOLDER_DOWNLOADED = 'shared_folder_downloaded';

	/** @var IFactory */
	protected $languageFactory;

	/** @var \OCP\Activity\IManager */
	protected $activityManager;

	/**
	 * @param IFactory $languageFactory
	 * @param IManager $activityManager
	 */
	public function __construct(IFactory $languageFactory, IManager $activityManager) {
		$this->languageFactory = $languageFactory;
		$this->activityManager = $activityManager;
	}

	/**
	 * @param string|null $languageCode
	 * @return IL10N
	 */
	protected function getL10N($languageCode = null) {
		return $this->languageFactory->get('files_downloadactivity', $languageCode);
	}

	/**
	 * The extension can return an array of additional notification types.
	 * If no additional types are to be added false is to be returned
	 *
	 * @param string $languageCode
	 * @return array|false Array "stringID of the type" => "translated string description for the setting"
	 * 				or Array "stringID of the type" => [
	 * 					'desc' => "translated string description for the setting"
	 * 					'methods' => [self::METHOD_*],
	 * 				]
	 */
	public function getNotificationTypes($languageCode) {
		$l = $this->getL10N($languageCode);
		return [
			self::TYPE_SHARE_DOWNLOADED => $l->t('A local or remote shared file or folder was <strong>downloaded</strong>'),
		];
	}

	/**
	 * For a given method additional types to be displayed in the settings can be returned.
	 * In case no additional types are to be added false is to be returned.
	 *
	 * @param string $method
	 * @return array|false
	 */
	public function getDefaultTypes($method) {
		if ($method === self::METHOD_STREAM) {
			return [self::TYPE_SHARE_DOWNLOADED];
		}

		return false;
	}

	/**
	 * The extension can translate a given message to the requested languages.
	 * If no translation is available false is to be returned.
	 *
	 * @param string $app
	 * @param string $text
	 * @param array $params
	 * @param boolean $stripPath
	 * @param boolean $highlightParams
	 * @param string $languageCode
	 * @return string|false
	 */
	public function translate($app, $text, $params, $stripPath, $highlightParams, $languageCode) {
		if ($app !== 'files_downloadactivity') {
			return false;
		}

		$l = $this->getL10N($languageCode);

		if ($this->activityManager->isFormattingFilteredObject()) {
			$translation = $this->translateShort($text, $l, $params);
			if ($translation !== false) {
				return $translation;
			}
		}

		return $this->translateLong($text, $l, $params);
	}

	/**
	 * @param string $text
	 * @param IL10N $l
	 * @param array $params
	 * @return string|false
	 */
	protected function translateLong($text, IL10N $l, array $params) {
		switch ($text) {
			case self::SUBJECT_SHARED_FOLDER_DOWNLOADED:
				if ($params[2] === '<parameter>desktop</parameter>') {
					return $l->t('Shared folder %1$s was downloaded by %2$s via the desktop client', $params);
				} else if ($params[2] === '<parameter>mobile</parameter>') {
					return $l->t('Shared folder %1$s was downloaded by %2$s via the mobile client', $params);
				} else {
					return $l->t('Shared folder %1$s was downloaded by %2$s via the web interface', $params);
				}
			case self::SUBJECT_SHARED_FILE_DOWNLOADED:
				if ($params[2] === '<parameter>desktop</parameter>') {
					return $l->t('Shared file %1$s was downloaded by %2$s via the desktop client', $params);
				} else if ($params[2] === '<parameter>mobile</parameter>') {
					return $l->t('Shared file %1$s was downloaded by %2$s via the mobile client', $params);
				} else {
					return $l->t('Shared file %1$s was downloaded by %2$s via the web interface', $params);
				}

			default:
				return false;
		}
	}

	/**
	 * @param string $text
	 * @param IL10N $l
	 * @param array $params
	 * @return string|false
	 */
	protected function translateShort($text, IL10N $l, array $params) {
		switch ($text) {
			case self::SUBJECT_SHARED_FOLDER_DOWNLOADED:
			case self::SUBJECT_SHARED_FILE_DOWNLOADED:
				if ($params[2] === '<parameter>desktop</parameter>') {
					return (string) $l->t('Downloaded by %2$s (via desktop)', $params);
				} else if ($params[2] === '<parameter>mobile</parameter>') {
					return (string) $l->t('Downloaded by %2$s (via mobile)', $params);
				} else {
					return (string) $l->t('Downloaded by %2$s (via web)', $params);
				}
			default:
				return false;
		}
	}

	/**
	 * The extension can define the type of parameters for translation
	 *
	 * Currently known types are:
	 * * file		=> will strip away the path of the file and add a tooltip with it
	 * * username	=> will add the avatar of the user
	 *
	 * @param string $app
	 * @param string $text
	 * @return array|false
	 */
	function getSpecialParameterList($app, $text) {
		if ($app !== 'files_downloadactivity') {
			return false;
		}

		switch ($text) {
			case self::SUBJECT_SHARED_FOLDER_DOWNLOADED:
			case self::SUBJECT_SHARED_FILE_DOWNLOADED:
				return [
					0 => 'file',
					1 => 'username',
					//2 => 'client',
				];
		}
		return false;
	}

	/**
	 * A string naming the css class for the icon to be used can be returned.
	 * If no icon is known for the given type false is to be returned.
	 *
	 * @param string $type
	 * @return string|false
	 */
	public function getTypeIcon($type) {
		if ($type === Extension::TYPE_SHARE_DOWNLOADED) {
			return 'icon-download';
		}
		return false;
	}

	/**
	 * The extension can define the parameter grouping by returning the index as integer.
	 * In case no grouping is required false is to be returned.
	 *
	 * @param array $activity
	 * @return integer|false
	 */
	public function getGroupParameter($activity) {
		return false;
	}

	/**
	 * The extension can define additional navigation entries. The array returned has to contain two keys 'top'
	 * and 'apps' which hold arrays with the relevant entries.
	 * If no further entries are to be added false is no be returned.
	 *
	 * @return array|false
	 */
	public function getNavigation() {
		return false;
	}

	/**
	 * The extension can check if a customer filter (given by a query string like filter=abc) is valid or not.
	 *
	 * @param string $filterValue
	 * @return boolean
	 */
	public function isFilterValid($filterValue) {
		return false;
	}

	/**
	 * The extension can filter the types based on the filter if required.
	 * In case no filter is to be applied false is to be returned unchanged.
	 *
	 * @param array $types
	 * @param string $filter
	 * @return array|false
	 */
	public function filterNotificationTypes($types, $filter) {
		return false;
	}

	/**
	 * For a given filter the extension can specify the sql query conditions including parameters for that query.
	 * In case the extension does not know the filter false is to be returned.
	 * The query condition and the parameters are to be returned as array with two elements.
	 * E.g. return array('`app` = ? and `message` like ?', array('mail', 'ownCloud%'));
	 *
	 * @param string $filter
	 * @return array|false
	 */
	public function getQueryForFilter($filter) {
		return false;
	}
}
