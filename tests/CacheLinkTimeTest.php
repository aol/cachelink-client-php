<?php

namespace Aol\CacheLink\Tests;

use Aol\CacheLink\CacheLinkTime;

class CacheLinkTimeTest extends \PHPUnit_Framework_TestCase
{
	public function testConstants()
	{
		$this->assertEquals(1000, CacheLinkTime::SECONDS);
		$this->assertEquals(1000 * 60, CacheLinkTime::MINUTES);
		$this->assertEquals(1000 * 60 * 60, CacheLinkTime::HOURS);
		$this->assertEquals(1000 * 60 * 60 * 24, CacheLinkTime::DAYS);
		$this->assertEquals(1000 * 60 * 60 * 24 * 7, CacheLinkTime::WEEKS);
	}
}
