<?php

namespace Aol\CacheLink\Tests;

use Aol\CacheLink\CacheLinkTime;

class CacheLinkTimeTest extends \PHPUnit_Framework_TestCase
{
	public function testConstants()
	{
		self::assertEquals(1000, CacheLinkTime::SECONDS);
		self::assertEquals(1000 * 60, CacheLinkTime::MINUTES);
		self::assertEquals(1000 * 60 * 60, CacheLinkTime::HOURS);
		self::assertEquals(1000 * 60 * 60 * 24, CacheLinkTime::DAYS);
		self::assertEquals(1000 * 60 * 60 * 24 * 7, CacheLinkTime::WEEKS);
	}
}
