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
		self::assertEquals(['background' => true], $this->cache->set('set1', 'bar', 10000));
	}

	public function testGet()
	{
		self::assertEquals(null, $this->cache->getSimple('foo'));
		$this->cache->set('foo', 'bar', 10000, [], ['wait' => true]);
		self::assertEquals('bar', $this->cache->getSimple('foo'));
	}

	public function testGetDetailed()
	{
		$key   = 'foo_detailed';
		$val   = 'bar';
		$ttl   = 10000;
		$assoc = ['assoc'];
		$meta  = ['x' => 'y'];
		self::assertTrue($this->cache->get($key)->isMiss());
		$this->cache->set($key, $val, $ttl, $assoc, ['wait' => true], $meta);
		$item = $this->cache->get($key);
		self::assertInstanceOf(CacheLinkItem::class, $item);
		self::assertTrue($item->isHit());
		self::assertEquals($key, $item->getKey());
		self::assertEquals($val, $item->getValue());
		self::assertEquals($ttl, $item->getTtlMillis());
		self::assertEquals($assoc, $item->getAssociations());
		self::assertEquals($meta, $item->getMetadata());
	}

	/**
	 * @large
	 */
	public function testTtl()
	{
		self::assertEquals(null, $this->cache->getSimple('ttl'));
		$this->cache->set('ttl', 'bar', 10, [], ['wait' => true]);
		self::assertEquals('bar', $this->cache->getSimple('ttl'));
		usleep(1000 * 1000);
		self::assertEquals(null, $this->cache->getSimple('ttl'));
	}

	public function testGetMany()
	{
		self::assertEquals(array_fill(0, 3, null), $this->cache->getManySimple(['foo','bar','baz']));
		$this->cache->set('foo', 'bar', 10000, [], ['wait' => true]);
		$this->cache->set('bar', 'baz', 10000, [], ['wait' => true]);
		$this->cache->set('baz', 'qux', 10000, [], ['wait' => true]);
		self::assertEquals(['bar','baz','qux'], $this->cache->getManySimple(['foo','bar','baz']));
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
			self::assertTrue($item->isMiss());
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
			self::assertInstanceOf(CacheLinkItem::class, $item);
			self::assertTrue($item->isHit());
			self::assertEquals($key, $item->getKey());
			self::assertEquals($p[0], $item->getValue());
			self::assertEquals($p[1], $item->getTtlMillis());
			self::assertEquals($p[2], $item->getAssociations());
			self::assertEquals($p[3], $item->getMetadata());
		}
	}

	public function testClear()
	{
		self::assertEquals(null, $this->cache->getSimple('clear1'));
		$this->cache->set('clear1', 'bar', 10000, [], ['wait' => true]);
		self::assertEquals('bar', $this->cache->getSimple('clear1'));
		self::assertEquals(['background' => true], $this->cache->clear(['clear1', 'bar']));
		self::assertEquals(null, $this->cache->getSimple('clear1'));
	}

	public function testClearLater()
	{
		self::assertEquals(['background' => true], $this->cache->clearLater(['foo', 'bar']));
	}

	public function testTriggerClearNow()
	{
		self::assertEquals(['background' => true], $this->cache->triggerClearNow());
	}

}