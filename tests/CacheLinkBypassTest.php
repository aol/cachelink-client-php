<?php

namespace Aol\CacheLink\Tests;

use Aol\CacheLink\CacheLinkBypass;

class CacheLinkBypassTest extends \PHPUnit_Framework_TestCase
{
	/** @var CacheLinkBypass */
	protected $cache;

	public function setUp()
	{
		$this->cache = new CacheLinkBypass;
	}

	public function testSet()
	{
		$this->assertEquals(['background' => true], $this->cache->set('foo', 'bar', 10000));
	}

	public function testGet()
	{
		$this->assertEquals(null, $this->cache->get('foo'));
		$this->cache->set('foo', 'bar', 10000, [], ['wait' => true]);
		$this->assertEquals(null, $this->cache->get('foo'));
	}

	public function testGetMany()
	{
		$this->assertEquals(array_fill(0, 3, null), $this->cache->getMany(['foo','bar','baz']));
		$this->cache->set('foo', 'bar', 10000, [], ['wait' => true]);
		$this->assertEquals(array_fill(0, 3, null), $this->cache->getMany(['foo','bar','baz']));
	}

	public function testClear()
	{
		$this->assertEquals(['background' => true], $this->cache->clear(['foo', 'bar']));
	}

	public function testClearLater()
	{
		$this->assertEquals(['background' => true], $this->cache->clearLater(['foo', 'bar']));
	}

	public function testTriggerClearNow()
	{
		$this->assertEquals(['background' => true], $this->cache->triggerClearNow());
	}

}