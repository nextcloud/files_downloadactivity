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

namespace OCA\FilesDownloadActivity\AppInfo;

use OCA\FilesDownloadActivity\Activity\Listener;
use OCP\AppFramework\App;
use OCP\Files\File;
use OCP\IPreview;
use OCP\Util;
use Symfony\Component\EventDispatcher\GenericEvent;

class Application extends App {

	public function __construct() {
		parent::__construct('files_downloadactivity');
	}

	/**
	 * Register all hooks and listeners
	 */
	public function register() {
		Util::connectHook('OC_Filesystem', 'read', $this, 'listenReadFile');

		$eventDispatcher = $this->getContainer()->getServer()->getEventDispatcher();
		$eventDispatcher->addListener(
			IPreview::EVENT,
			function (GenericEvent $event) {
				$this->listenPreviewFile($event);
			}
		);
	}

	/**
	 * @param array $params
	 */
	public function listenReadFile($params) {
		/** @var Listener $hooks */
		$hooks = $this->getContainer()->query(Listener::class);
		$hooks->readFile($params['path']);
	}

	/**
	 * @param GenericEvent $event
	 */
	public function listenPreviewFile(GenericEvent $event) {
		$details = $event->getArguments();
		if ($details['width'] <= 150 && $details['height'] <= 150) {
			// Ignore mini preview, but we need "big" previews because of the viewer app.
			return;
		}

		/** @var File $file */
		$file = $event->getSubject();

		if (substr_count($file->getPath(), '/') < 3) {
			// Invalid path
			return;
		}

		[,, $filesApp, $path] = explode('/', $file->getPath(), 4);

		if ($filesApp !== 'files') {
			return;
		}

		/** @var Listener $hooks */
		$hooks = $this->getContainer()->query(Listener::class);
		$hooks->readFile($path);
	}
}
