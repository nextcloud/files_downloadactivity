<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
use OCP\Server;
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

		$eventDispatcher = Server::get(IEventDispatcher::class);
		$eventDispatcher->addListener(
			BeforePreviewFetchedEvent::class,
			function (BeforePreviewFetchedEvent $event) {
				$this->listenPreviewFile($event);
			}
		);
	}

	public function listenReadFile(array $params): void {
		$hooks = Server::get(Listener::class);
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

		$hooks = Server::get(Listener::class);
		$hooks->readFile($path);
	}
}
