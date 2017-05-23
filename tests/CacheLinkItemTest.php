<?php

namespace Aol\CacheLink\Tests;

use Aol\CacheLink\CacheLinkItem;

class CacheLinkItemTest extends \PHPUnit_Framework_TestCase
{
    public function testThawReturnsMissForNull()
    {
        $key = 'miss_me';
        $miss = CacheLinkItem::thaw($key, null);
        self::assertInstanceOf(CacheLinkItem::class, $miss);
        self::assertTrue($miss->isMiss());
        self::assertFalse($miss->isHit());
        self::assertNull($miss->getValue());
        self::assertEquals($key, $miss->getKey());
        self::assertEmpty($miss->getAssociations());
        self::assertEmpty($miss->getMetadata());
    }

    public function testThawReturnsValidItem()
    {
        $key = 'gondola';
        $value = 'kermit';
        $millis = 1999;
        $associations = ['piggy', 'tea'];
        $metadata = ['wut' => 'heh', 'noes' => 'okay'];
        $raw = [CacheLinkItem::ITEM_IDENTIFIER, $value, $millis, $associations, $metadata];
        $item = CacheLinkItem::thaw($key, $raw);
        self::assertEquals($key, $item->getKey());
        self::assertEquals($value, $item->getValue());
        self::assertEquals($millis, $item->getTtlMillis());
        self::assertEquals($associations, $item->getAssociations());
        self::assertEquals($metadata, $item->getMetadata());
        self::assertTrue($item->isHit());
        self::assertFalse($item->isMiss());
    }

    /**
     * @expectedException \Aol\CacheLink\Exceptions\CacheLinkRuntimeException
     * @expectedExceptionMessageRegExp /value\("foo_doo_moo"\)/
     */
    public function testThawThrowsExceptionForInvalidItem()
    {
        CacheLinkItem::thaw('broken', 'foo_doo_moo');
    }

    /**
     * @expectedException \Aol\CacheLink\Exceptions\CacheLinkRuntimeException
     * @expectedExceptionMessageRegExp /value\([^)]+\.\.\.\)/
     */
    public function testThawThrowsExceptionForInvalidItemWithLongSnippet()
    {
        $multiply = 'hello_world_';
        CacheLinkItem::thaw('broken', str_repeat($multiply, 25));
    }
}
