<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesDownloadActivity\Tests\AppInfo;

use OCA\FilesDownloadActivity\Activity\Listener;
use OCA\FilesDownloadActivity\Activity\Provider;
use OCA\FilesDownloadActivity\Activity\Setting;
use OCA\FilesDownloadActivity\AppInfo\Application;
use OCA\FilesDownloadActivity\Tests\TestCase;
use OCP\Activity\IProvider;
use OCP\Activity\ISetting;

/**
 * Class ApplicationTest
 *
 * @package OCA\FilesDownloadActivity\Tests
 * @group DB
 */
class ApplicationTest extends TestCase {
	/** @var \OCA\FilesDownloadActivity\AppInfo\Application */
	protected $app;

	/** @var \OCP\AppFramework\IAppContainer */
	protected $container;

	protected function setUp(): void {
		parent::setUp();
		$this->app = new Application();
		$this->container = $this->app->getContainer();
	}

	public function testContainerAppName() {
		$this->app = new Application();
		$this->assertEquals('files_downloadactivity', $this->container->getAppName());
	}

	public function dataContainerQuery() {
		return [
			[Setting::class, Setting::class],
			[Setting::class, ISetting::class],
			[Provider::class, Provider::class],
			[Provider::class, IProvider::class],
			[Listener::class, Listener::class],
		];
	}

	/**
	 * @dataProvider dataContainerQuery
	 * @param string $service
	 * @param string $expected
	 */
	public function testContainerQuery($service, $expected) {
		$this->assertInstanceOf($expected, $this->container->query($service));
	}
}
