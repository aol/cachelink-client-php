<?php

namespace Aol\CacheLink\Tests;

use Aol\CacheLink\CacheLinkBypass;
use Aol\CacheLink\CacheLinkMemory;

class CacheLinkMemoryTest extends \PHPUnit_Framework_TestCase
{
	/** @var CacheLinkMemory */
	protected $cache;

	public function setUp()
	{
		$this->cache = new CacheLinkMemory;
	}

	public function testSet()
	{
		$this->assertEquals(['background' => true], $this->cache->set('set1', 'bar', 10000));
	}

	public function testGet()
	{
		$this->assertEquals(null, $this->cache->get('foo'));
		$this->cache->set('foo', 'bar', 10000, [], ['wait' => true]);
		$this->assertEquals('bar', $this->cache->get('foo'));
	}

	/**
	 * @large
	 */
	public function testTtl()
	{
		$this->assertEquals(null, $this->cache->get('ttl'));
		$this->cache->set('ttl', 'bar', 10, [], ['wait' => true]);
		$this->assertEquals('bar', $this->cache->get('ttl'));
		usleep(1000 * 1000);
		$this->assertEquals(null, $this->cache->get('ttl'));
	}

	public function testGetMany()
	{
		$this->assertEquals(array_fill(0, 3, null), $this->cache->getMany(['foo','bar','baz']));
		$this->cache->set('foo', 'bar', 10000, [], ['wait' => true]);
		$this->cache->set('bar', 'baz', 10000, [], ['wait' => true]);
		$this->cache->set('baz', 'qux', 10000, [], ['wait' => true]);
		$this->assertEquals(['bar','baz','qux'], $this->cache->getMany(['foo','bar','baz']));
	}

	public function testClear()
	{
		$this->assertEquals(null, $this->cache->get('clear1'));
		$this->cache->set('clear1', 'bar', 10000, [], ['wait' => true]);
		$this->assertEquals('bar', $this->cache->get('clear1'));
		$this->assertEquals(['background' => true], $this->cache->clear(['clear1', 'bar']));
		$this->assertEquals(null, $this->cache->get('clear1'));
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