<?php

namespace Aol\CacheLink\Tests;

use Aol\CacheLink\CacheLinkBypass;
use Aol\CacheLink\CacheLinkItem;

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
		/** @var CacheLinkItem $val */
		$val = $this->cache->get('foo');
		$this->assertInstanceOf(CacheLinkItem::class, $val);
		$this->assertTrue($val->isMiss());
		$this->assertFalse($val->isHit());
		$this->assertNull($val->getValue());

		$this->cache->set('foo', 'bar', 10000, [], ['wait' => true]);

		/** @var CacheLinkItem $val */
		$val = $this->cache->get('foo');
		$this->assertInstanceOf(CacheLinkItem::class, $val);
		$this->assertTrue($val->isMiss());
		$this->assertFalse($val->isHit());
		$this->assertNull($val->getValue());
	}

	public function testGetMany()
	{
		$vals = $this->cache->getMany(['foo','bar','baz']);
		/** @var CacheLinkItem $val */
		foreach ($vals as $val) {
			$this->assertInstanceOf(CacheLinkItem::class, $val);
			$this->assertTrue($val->isMiss());
			$this->assertFalse($val->isHit());
			$this->assertNull($val->getValue());
		}

		$this->cache->set('foo', 'bar', 10000, [], ['wait' => true]);

		$vals = $this->cache->getMany(['foo','bar','baz']);
		/** @var CacheLinkItem $val */
		foreach ($vals as $val) {
			$this->assertInstanceOf(CacheLinkItem::class, $val);
			$this->assertTrue($val->isMiss());
			$this->assertFalse($val->isHit());
			$this->assertNull($val->getValue());
		}
	}

	public function testGetSimple()
	{
		$this->assertEquals(null, $this->cache->getSimple('foo'));
		$this->cache->set('foo', 'bar', 10000, [], ['wait' => true]);
		$this->assertEquals(null, $this->cache->getSimple('foo'));
	}

	public function testGetManySimple()
	{
		$this->assertEquals(array_fill(0, 3, null), $this->cache->getManySimple(['foo','bar','baz']));
		$this->cache->set('foo', 'bar', 10000, [], ['wait' => true]);
		$this->assertEquals(array_fill(0, 3, null), $this->cache->getManySimple(['foo','bar','baz']));
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