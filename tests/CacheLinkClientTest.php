<?php

namespace Aol\CacheLink\Tests;

use Aol\CacheLink\CacheLinkClient;
use Aol\CacheLink\CacheLinkEncoderStandard;
use Aol\CacheLink\CacheLinkItem;
use Aol\CacheLink\Exceptions\CacheLinkUnserializeException;
use Predis\Client;

abstract class CacheLinkClientTest extends \PHPUnit_Framework_TestCase
{
	/** @var CacheLinkClient */
	protected $client;
	/** @var \Predis\Client */
	protected $redis_client;

	protected function setUp()
	{
		$this->client = $this->createClient();
		$this->redis_client = $this->createRedisClient();
		$this->flushRedis($this->redis_client);
	}

	protected abstract function getPort();
	protected abstract function createRedisClient();
	protected abstract function flushRedis(Client $redis_client);
	protected abstract function getAllRedisData(Client $redis_client);

	protected function createClient()
	{
		return new CacheLinkClient(
			'http://localhost:' . $this->getPort(),
			CacheLinkClient::DEFAULT_TIMEOUT
		);
	}

	public function testCustomDecoderIsUsed()
	{
		$this->client->setupDirectRedis($this->redis_client, $this->redis_client);
		$item = new CacheLinkItem(__METHOD__, __METHOD__ . '_value', 10000, [], []);
		$decoder = self::getMockBuilder(CacheLinkEncoderStandard::class)->getMock();
		$decoder->expects(self::once())->method('decode')->willReturn($item->freeze());
		$this->client->setDecoder($decoder);
		$this->client->set($item->getKey(), $item->getValue(), $item->getTtlMillis());
		self::assertEquals($item->getValue(), $this->client->getSimple($item->getKey()));
	}

	/**
	 * @dataProvider dataSetAndGet
	 */
	public function testSetAndGet($direct_read, $direct_write, $keys_to_vals)
	{
		$this->client->setupDirectRedis(
			$direct_read  ? $this->redis_client : null,
			$direct_write ? $this->redis_client : null
		);

		$r=$direct_read?'direct_read':'service_read';
		$w=$direct_write?'direct_write':'service_write';
		$t="testSetAndGet($r/$w)";

		$ok_set = ['cacheSet' => 'OK', 'clearAssocIn' => 0, 'success' => true, 'broadcastResult' => null];
		if ($direct_write) {
			$ok_set['directSet'] = true;
		}
		foreach ($keys_to_vals as $key => $val) {
			$result_set = $this->client->set($key, $val, 10000, [], ['wait' => true]);
			self::assertEquals($ok_set, $result_set);
		}

		$keys = array_keys($keys_to_vals);
		$vals = array_values($keys_to_vals);

		$result_get = $this->client->getSimple($keys[0]);
		self::assertEquals($vals[0], $result_get);

		$result_get_many = $this->client->getManySimple($keys);
		self::assertEquals($vals, $result_get_many);
	}

	/**
	 * @dataProvider dataSetAndGetDetailed
	 */
	public function testSetAndGetDetailed($direct_read, $direct_write, $set)
	{
		$this->client->setupDirectRedis(
			$direct_read  ? $this->redis_client : null,
			$direct_write ? $this->redis_client : null
		);

		$ok_set = [
			'cacheSet' => 'OK',
			'clearAssocIn' => 0,
			'success' => true,
			'assocIn' => 1,
			'expireAssocIn' => 1,
		];
		foreach ($set as $key => $p) {
			$result_set = $this->client->set($key, $p[0], $p[1], $p[2], ['wait' => true], $p[3]);
			self::assertEquals($ok_set, array_intersect_key($ok_set, $result_set));
		}

		$keys = array_keys($set);
		$ps = array_values($set);

		$result_get = $this->client->getSimple($keys[0]);
		self::assertEquals($ps[0][0], $result_get);

		$result_get_many = $this->client->getMany($keys);
		foreach ($keys as $i => $key) {
			/** @var CacheLinkItem $item */
			$item = $result_get_many[$i];
			$p = $ps[$i];
			self::assertInstanceOf(CacheLinkItem::class, $item);
			self::assertTrue($item->isHit());
			self::assertEquals($key, $item->getKey());
			self::assertEquals($p[0], $item->getValue());
			self::assertEquals($p[1], $item->getTtlMillis());
			self::assertEquals($p[2], $item->getAssociations());
			self::assertEquals($p[3], $item->getMetadata());
		}
	}

	public function testNonexistent()
	{
		$this->client->setupDirectRedis($this->redis_client);
		self::assertNull($this->client->getSimple('noope'));
		self::assertEquals(array_fill(0, 2, null), $this->client->getManySimple(['no1','no2']));
	}

	public function testDifferentEncoding()
	{
		$old_encoding = mb_internal_encoding();
		mb_internal_encoding('iso-8859-1');
		$client = $this->createClient();
		$client->set('encoding_test', 'foo', 10000);
		self::assertEquals('foo', $client->getSimple('encoding_test'));
		mb_internal_encoding($old_encoding);
	}

	public function testSetNull()
	{
		$this->client->set('set_null', null, 10000);
		self::assertNull($this->client->getSimple('set_null'));
	}

	public function testRedisSetsAreVisible()
	{
		$client1 = $this->createClient();
		$client2 = $this->createClient();
		$redis = $this->createRedisClient();
		$client1->setupDirectRedis($redis, $redis);
		$client1->set('simple_key1', 'simple_value1', 100000, [], []);
		$client2->set('simple_key2', 'simple_value2', 100000, [], []);
		self::assertEquals('simple_value1', $client2->getSimple('simple_key1'));
		self::assertEquals('simple_value2', $client1->getSimple('simple_key2'));
	}

	/**
	 * @expectedException \Aol\CacheLink\Exceptions\CacheLinkServerException
	 */
	public function testBadRequest()
	{
		$this->client->getSimple('');
	}

	/**
	 * @expectedException \Exception
	 */
	public function testInvalidGet()
	{
		$this->redis_client->set('d:invalid_get', '$%^&*(');
		$this->client->getSimple('invalid_get');
	}

	public function testInvalidGetWithCustomUnserializeExceptionHandler()
	{
		$key = 'invalid_get_custom_exception_handler';
		$raw = '$%^&*(';
		$this->redis_client->set("d:$key", $raw);
		$caught = null;
		$client = $this->createClient();
		$client->setUnserializeExceptionHandler(function ($ex) use (&$caught, $key, $raw) {
			$caught = $ex;
			/** @var CacheLinkUnserializeException $ex */
			self::assertInstanceOf(CacheLinkUnserializeException::class, $ex);
			self::assertEquals($key, $ex->getCacheKey());
			self::assertEquals($raw, $ex->getCacheRawValue());
		});
		$client->getSimple($key);
		self::assertNotNull($caught);
	}

	/**
	 * @expectedException \Exception
	 */
	public function testInvalidSet()
	{
		$this->client->set('invalid_set', function () { }, 1000);
	}

	/**
	 * @expectedException \Exception
	 */
	public function testInvalidMany()
	{
		$this->redis_client->set('d:invalidMany', '$%^&*(');
		$this->client->getManySimple(['invalidMany']);
	}


	/**
	 * @expectedException \Exception
	 */
	public function testInvalidDirect()
	{
		$this->client->setupDirectRedis($this->redis_client);
		$this->redis_client->set('d:invalid', '$%^&*(');
		$this->client->getSimple('invalid');
	}

	/**
	 * @expectedException \Exception
	 */
	public function testInvalidManyDirect()
	{
		$this->client->setupDirectRedis($this->redis_client);
		$this->redis_client->set('d:invalidMany', '$%^&*(');
		$this->client->getManySimple(['invalidMany']);
	}

	public function dataSetAndGet()
	{
		$keys_to_vals = [
			'foo1' => ['x'=>'bar', 'y'=>[1,2,'something'], 'z'=>false, 'w'=>94.3, new \DateTime('now')],
			'foo2' => [1,2,'hello',false,true,54.3,new \stdClass()]
		];
		return [
			'read from service, write to service' => [false, false, $keys_to_vals],
			'read from redis, write to service' => [true, false, $keys_to_vals],
			'read from service, write to redis' => [false, true, $keys_to_vals],
			'read from redis, write to redis' => [true, true, $keys_to_vals]
		];
	}

	public function dataSetAndGetDetailed()
	{
		$set = [
			'foo' => ['fooval', 1000000000, ['fooassoc'], ['foometa' => 'foometaval']],
			'bar' => ['barval', 1000000000, ['barassoc'], ['barmeta' => 'barmetaval']],
			'baz' => ['bazval', 1000000000, ['bazassoc'], ['bazmeta' => 'bazmetaval']],
		];
		return [
			'read from service, write to service' => [false, false, $set],
			'read from redis, write to service' => [true, false, $set],
			'read from service, write to redis' => [false, true, $set],
			'read from redis, write to redis' => [true, true, $set]
		];
	}

	public function testClear()
	{
		$ok_set = ['cacheSet' => 'OK', 'clearAssocIn' => 0, 'success' => true, 'broadcastResult' => null];
		self::assertEquals($ok_set, $this->client->set('foo', 'bar', 100000, [], ['wait' => true]));
		self::assertEquals('bar', $this->client->getSimple('foo'));

		self::assertEquals(
			[
				'success' => true,
				'level' => 1,
				'keys' => ['foo'],
				'keysCount' => 1,
				'cleared' => 1,
				'keysContains' => [],
				'removedFromContains' => 0,
				'keysInDeleted' => 0,
				'keysNextLevel' => [],
				'allKeysCleared' => ['foo'],
				'broadcastResult' => null,
			],
			$this->client->clear(['foo'], CacheLinkClient::CLEAR_LEVELS_ALL, ['wait' => true])
		);
		self::assertNull($this->client->getSimple('foo'));
	}

	public function testClearAssociations()
	{
		$ok_set = ['cacheSet' => 'OK', 'clearAssocIn' => 0, 'success' => true, 'broadcastResult' => null];
		self::assertEquals($ok_set, array_intersect_key($ok_set, $this->client->set('foo', 'V1', 100000, ['bar','baz'], ['wait' => true])));
		self::assertEquals($ok_set, array_intersect_key($ok_set, $this->client->set('bar', 'V2', 100000, ['asd'], ['wait' => true])));
		self::assertEquals($ok_set, array_intersect_key($ok_set, $this->client->set('baz', 'V3', 100000, [], ['wait' => true])));
		self::assertEquals($ok_set, array_intersect_key($ok_set, $this->client->set('asd', 'V4', 100000, [], ['wait' => true])));
		self::assertEquals(['V1','V2','V3','V4'], $this->client->getManySimple(['foo','bar','baz','asd']));

		$expected_clear = [
			'success' => true,
			'level' => 1,
			'keys' => ['asd'],
			'keysCount' => 1,
			'cleared' => 1,
			'keysContains' => [],
			'removedFromContains' => 0,
			'keysInDeleted' => 0,
			'keysNextLevel' => ['bar'],
			'nextLevel' => [
				'success' => true,
				'level' => 2,
				'keys' => ['bar'],
				'keysCount' => 1,
				'cleared' => 1,
				'keysContains' => ['asd'],
				'removedFromContains' => 1,
				'keysInDeleted' => 1,
				'keysNextLevel' => ['foo'],
				'nextLevel' => [
					'success' => true,
					'level' => 3,
					'keys' => ['foo'],
					'keysCount' => 1,
					'cleared' => 1,
					'keysContains' => ['bar', 'baz'],
					'removedFromContains' => 2,
					'keysInDeleted' => 1,
					'keysNextLevel' => []
				],
			],
			'allKeysCleared' => ['asd','bar','foo'],
			'broadcastResult' => null,
		];

		$clear_result = $this->client->clear(['asd'], CacheLinkClient::CLEAR_LEVELS_ALL, ['wait' => true]);
		if (!empty($clear_result['nextLevel']['keysContains'])) {
			sort($clear_result['nextLevel']['keysContains']);
		}
		if (!empty($clear_result['nextLevel']['nextLevel']['keysContains'])) {
			sort($clear_result['nextLevel']['nextLevel']['keysContains']);
		}

		self::assertEquals($expected_clear, $clear_result);

		self::assertEquals([null,null,'V3',null], $this->client->getManySimple(['foo','bar','baz','asd']));

		self::assertEquals(
			[
				'success' => true,
				'level' => 1,
				'keys' => ['baz'],
				'keysCount' => 1,
				'cleared' => 1,
				'keysContains' => [],
				'removedFromContains' => 0,
				'keysInDeleted' => 0,
				'keysNextLevel' => [],
				'allKeysCleared' => ['baz'],
				'broadcastResult' => null,
			],
			$this->client->clear(['baz'], CacheLinkClient::CLEAR_LEVELS_ALL, ['wait' => true])
		);
	}

	public function testClearLater()
	{
		$ok_set = ['cacheSet' => 'OK', 'clearAssocIn' => 0, 'success' => true, 'broadcastResult' => null];
		self::assertEquals($ok_set, $this->client->set('foo', 'V1', 100000, [], ['wait' => true]));
		self::assertEquals($ok_set, $this->client->set('bar', 'V2', 100000, [], ['wait' => true]));
		self::assertEquals($ok_set, $this->client->set('baz', 'V3', 100000, [], ['wait' => true]));
		self::assertEquals($ok_set, $this->client->set('asd', 'V4', 100000, [], ['wait' => true]));
		self::assertEquals(['V1','V2','V3','V4'], $this->client->getManySimple(['foo','bar','baz','asd']));

		$this->client->clearLater(['foo','bar']);
		$this->client->triggerClearNow();
		usleep(10 * 1000);

		self::assertEquals([null,null,'V3','V4'], $this->client->getManySimple(['foo','bar','baz','asd']));
		$this->client->clearLater(['baz','asd']);

		self::assertEquals([null,null,'V3','V4'], $this->client->getManySimple(['foo','bar','baz','asd']));
		$this->client->triggerClearNow();
		usleep(10 * 1000);
		self::assertEquals([null,null,null,null], $this->client->getManySimple(['foo','bar','baz','asd']));
	}




}