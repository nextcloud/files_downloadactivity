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

namespace OCA\FilesDownloadActivity\AppInfo;

use OCA\FilesDownloadActivity\Activity\Listener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;
use OCP\Preview\BeforePreviewFetchedEvent;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'files_downloadactivity';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
		Util::connectHook('OC_Filesystem', 'read', $this, 'listenReadFile');

		$eventDispatcher = $this->getContainer()->get(IEventDispatcher::class);
		$eventDispatcher->addListener(
			BeforePreviewFetchedEvent::class,
			function (BeforePreviewFetchedEvent $event) {
				$this->listenPreviewFile($event);
			}
		);
	}

	public function listenReadFile(array $params): void {
		/** @var Listener $hooks */
		$hooks = $this->getContainer()->get(Listener::class);
		$hooks->readFile($params['path']);
	}

	public function listenPreviewFile(BeforePreviewFetchedEvent $event): void {
		if ($event->getWidth() <= 250 && $event->getHeight() <= 250) {
			// Ignore mini preview, but we need "big" previews because of the viewer app.
			return;
		}

		/** @var File $file */
		$file = $event->getNode();

		if (substr_count($file->getPath(), '/') < 3) {
			// Invalid path
			return;
		}

		[,, $filesApp, $path] = explode('/', $file->getPath(), 4);

		if ($filesApp !== 'files') {
			return;
		}

		/** @var Listener $hooks */
		$hooks = $this->getContainer()->get(Listener::class);
		$hooks->readFile($path);
	}
}
