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

namespace OCA\FilesDownloadActivity\Tests\Activity;

use OCA\FilesDownloadActivity\Activity\Extension;
use OCA\FilesDownloadActivity\Tests\TestCase;
use OCP\Activity\IExtension;
use OCP\Activity\IManager;
use OCP\IL10N;
use OCP\L10N\IFactory;

class ExtensionTest extends TestCase {
	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $activity;
	/** @var IFactory|\PHPUnit_Framework_MockObject_MockObject */
	protected $factory;
	/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject */
	protected $l;
	/** @var Extension */
	protected $extension;

	protected function setUp() {
		parent::setUp();

		$this->activity = $this->createMock(IManager::class);
		$this->l = $this->createMock(IL10N::class);
		$this->factory = $this->createMock(IFactory::class);

		$this->l->expects($this->any())
			->method('t')
			->willReturnCallback(function($string, $args) {
				return vsprintf($string, $args);
			});
		$this->factory->expects($this->any())
			->method('get')
			->willReturn($this->l);

		$this->extension = new Extension(
			$this->factory,
			$this->activity
		);
	}

	public function testGetNotificationTypes() {
		$this->assertEquals([
			Extension::TYPE_SHARE_DOWNLOADED => 'A local or remote shared file or folder was <strong>downloaded</strong>'
			], $this->extension->getNotificationTypes('en'));
	}

	public function dataGetDefaultTypes() {
		return [
			[IExtension::METHOD_STREAM, [Extension::TYPE_SHARE_DOWNLOADED]],
			[IExtension::METHOD_MAIL, false],
		];
	}

	/**
	 * @dataProvider dataGetDefaultTypes
	 * @param string $method
	 * @param mixed $expected
	 */
	public function testGetDefaultTypes($method, $expected) {
		$this->assertSame($expected, $this->extension->getDefaultTypes($method));
	}

	public function dataGetTypeIcon() {
		return [
			[Extension::TYPE_SHARE_DOWNLOADED, 'icon-download'],
			['unknownType', false],
		];
	}

	/**
	 * @dataProvider dataGetTypeIcon
	 * @param string $type
	 * @param mixed $expected
	 */
	public function testGetTypeIcon($type, $expected) {
		$this->assertSame($expected, $this->extension->getTypeIcon($type));
	}

	public function dataGetSpecialParameterList() {
		return [
			['files_downloadactivity', Extension::SUBJECT_SHARED_FOLDER_DOWNLOADED, [
				0 => 'file',
				1 => 'username',
			]],
			['files_downloadactivity', Extension::SUBJECT_SHARED_FILE_DOWNLOADED, [
				0 => 'file',
				1 => 'username',
			]],
			['files_downloadactivity', 'Not the subject we are looking for', false],
			['files', '', false]
		];
	}

	/**
	 * @dataProvider dataGetSpecialParameterList
	 * @param string $app
	 * @param string $text
	 * @param mixed $expected
	 */
	public function testGetSpecialParameterList($app, $text, $expected) {
		$this->assertSame($expected, $this->extension->getSpecialParameterList($app, $text));
	}

	public function testGetGroupParameter() {
		$this->assertFalse($this->extension->getGroupParameter(['app' => 'files_downloadactivity']));
	}

	public function testGetNavigation() {
		$this->assertFalse($this->extension->getNavigation());
	}

	public function dataKnownFilters() {
		return [
			['all'],
			['self'],
			['by'],
			['filter'],
		];
	}

	/**
	 * @dataProvider dataKnownFilters
	 *
	 * @param string $filter
	 */
	public function testIsFilterValid($filter) {
		$this->assertFalse($this->extension->isFilterValid($filter));
	}

	/**
	 * @dataProvider dataKnownFilters
	 *
	 * @param string $filter
	 * @param mixed $expected
	 */
	public function testFilterNotificationTypes($filter) {
		$this->assertFalse($this->extension->filterNotificationTypes([], $filter));
	}

	/**
	 * @dataProvider dataKnownFilters
	 *
	 * @param string $filter
	 */
	public function testGetQueryForFilter($filter) {
		$this->assertFalse($this->extension->getQueryForFilter($filter));
	}
}
