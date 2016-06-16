<?php

namespace Aol\CacheLink\Tests;

use Aol\CacheLink\CacheLinkItem;
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
		$this->assertEquals(null, $this->cache->getSimple('foo'));
		$this->cache->set('foo', 'bar', 10000, [], ['wait' => true]);
		$this->assertEquals('bar', $this->cache->getSimple('foo'));
	}

	public function testGetDetailed()
	{
		$key   = 'foo_detailed';
		$val   = 'bar';
		$ttl   = 10000;
		$assoc = ['assoc'];
		$meta  = ['x' => 'y'];
		$this->assertTrue($this->cache->get($key)->isMiss());
		$this->cache->set($key, $val, $ttl, $assoc, ['wait' => true], $meta);
		$item = $this->cache->get($key);
		$this->assertInstanceOf(CacheLinkItem::class, $item);
		$this->assertTrue($item->isHit());
		$this->assertEquals($key, $item->getKey());
		$this->assertEquals($val, $item->getValue());
		$this->assertEquals($ttl, $item->getTtlMillis());
		$this->assertEquals($assoc, $item->getAssociations());
		$this->assertEquals($meta, $item->getMetadata());
	}

	/**
	 * @large
	 */
	public function testTtl()
	{
		$this->assertEquals(null, $this->cache->getSimple('ttl'));
		$this->cache->set('ttl', 'bar', 10, [], ['wait' => true]);
		$this->assertEquals('bar', $this->cache->getSimple('ttl'));
		usleep(1000 * 1000);
		$this->assertEquals(null, $this->cache->getSimple('ttl'));
	}

	public function testGetMany()
	{
		$this->assertEquals(array_fill(0, 3, null), $this->cache->getManySimple(['foo','bar','baz']));
		$this->cache->set('foo', 'bar', 10000, [], ['wait' => true]);
		$this->cache->set('bar', 'baz', 10000, [], ['wait' => true]);
		$this->cache->set('baz', 'qux', 10000, [], ['wait' => true]);
		$this->assertEquals(['bar','baz','qux'], $this->cache->getManySimple(['foo','bar','baz']));
	}

	public function testGetManyDetailed()
	{
		$set = [
			'foo' => ['fooval', 10000, ['fooassoc'], ['foometa' => 'foometaval']],
			'bar' => ['barval', 10000, ['barassoc'], ['barmeta' => 'barmetaval']],
			'baz' => ['bazval', 10000, ['bazassoc'], ['bazmeta' => 'bazmetaval']],
		];
		$items = $this->cache->getMany(array_keys($set));
		/** @var CacheLinkItem $item */
		foreach ($items as $item) {
			$this->assertTrue($item->isMiss());
		}
		foreach ($set as $key => $p) {
			$this->cache->set($key, $p[0], $p[1], $p[2], ['wait' => true], $p[3]);
		}
		$keys  = array_keys($set);
		$items = $this->cache->getMany($keys);
		foreach ($keys as $i => $key) {
			/** @var CacheLinkItem $item */
			$item = $items[$i];
			$p = $set[$key];
			$this->assertInstanceOf(CacheLinkItem::class, $item);
			$this->assertTrue($item->isHit());
			$this->assertEquals($key, $item->getKey());
			$this->assertEquals($p[0], $item->getValue());
			$this->assertEquals($p[1], $item->getTtlMillis());
			$this->assertEquals($p[2], $item->getAssociations());
			$this->assertEquals($p[3], $item->getMetadata());
		}
	}

	public function testClear()
	{
		$this->assertEquals(null, $this->cache->getSimple('clear1'));
		$this->cache->set('clear1', 'bar', 10000, [], ['wait' => true]);
		$this->assertEquals('bar', $this->cache->getSimple('clear1'));
		$this->assertEquals(['background' => true], $this->cache->clear(['clear1', 'bar']));
		$this->assertEquals(null, $this->cache->getSimple('clear1'));
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