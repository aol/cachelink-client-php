<?php

namespace Aol\CacheLink\Tests;

use Aol\CacheLink\CacheLinkClient;

class CacheLinkClientTest extends \PHPUnit_Framework_TestCase
{
	/** @var CacheLinkClient */
	protected $client;
	/** @var \Predis\Client */
	protected $redis_client;

	protected function setUp()
	{
		$this->client = new CacheLinkClient('http://localhost:' . CacheLinkServer::getInstance()->getConfig()->port);
		$this->redis_client = new \Predis\Client;
		$this->redis_client->flushdb();
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

		$ok_set = ['cacheSet' => 'OK', 'clearAssocIn' => 0, 'success' => true];
		foreach ($keys_to_vals as $key => $val) {
			$result_set = $this->client->set($key, $val, 10000, [], ['wait' => true]);
			$this->assertEquals($ok_set, $result_set);
		}

		$keys = array_keys($keys_to_vals);
		$vals = array_values($keys_to_vals);

		$result_get = $this->client->get($keys[0]);
		$this->assertEquals($vals[0], $result_get);

		$result_get_many = $this->client->getMany($keys);
		$this->assertEquals($vals, $result_get_many);
	}

	public function testNonexistent()
	{
		$this->client->setupDirectRedis($this->redis_client);
		$this->assertNull($this->client->get('noope'));
		$this->assertEquals(array_fill(0, 2, null), $this->client->getMany(['no1','no2']));
	}

	/**
	 * @expectedException \Aol\CacheLink\CacheLinkServerException
	 */
	public function testBadRequest()
	{
		$this->client->get('');
	}

	/**
	 * @expectedException \Exception
	 */
	public function testInvalid()
	{
		$this->redis_client->set('d:invalid', '$%^&*(');
		$this->client->get('invalid');
	}

	/**
	 * @expectedException \Exception
	 */
	public function testInvalidMany()
	{
		$this->redis_client->set('d:invalidMany', '$%^&*(');
		$this->client->getMany(['invalidMany']);
	}


	/**
	 * @expectedException \Exception
	 */
	public function testInvalidDirect()
	{
		$this->client->setupDirectRedis($this->redis_client);
		$this->redis_client->set('d:invalid', '$%^&*(');
		$this->client->get('invalid');
	}

	/**
	 * @expectedException \Exception
	 */
	public function testInvalidManyDirect()
	{
		$this->client->setupDirectRedis($this->redis_client);
		$this->redis_client->set('d:invalidMany', '$%^&*(');
		$this->client->getMany(['invalidMany']);
	}

	public function dataSetAndGet()
	{
		$keys_to_vals = [
			'foo1' => ['x'=>'bar', 'y'=>[1,2,'something'], 'z'=>false, 'w'=>94.3, new \DateTime('now')],
			'foo2' => [1,2,'hello',false,true,54.3,new \stdClass()]
		];
		return [
			[false, false, $keys_to_vals],
			[true, false, $keys_to_vals],
			[false, true, $keys_to_vals],
			[true, true, $keys_to_vals]
		];
	}

	public function testClear()
	{
		$ok_set = ['cacheSet' => 'OK', 'clearAssocIn' => 0, 'success' => true];
		$this->assertEquals($ok_set, $this->client->set('foo', 'bar', 100000, [], ['wait' => true]));
		$this->assertEquals('bar', $this->client->get('foo'));

		$this->assertEquals(
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
				'allKeysCleared' => ['foo']
			],
			$this->client->clear(['foo'], CacheLinkClient::CLEAR_LEVELS_ALL, ['wait' => true])
		);
		$this->assertNull($this->client->get('foo'));
	}

	public function testClearAssociations()
	{
		$ok_set = ['cacheSet' => 'OK', 'clearAssocIn' => 0, 'success' => true];
		$this->assertEquals($ok_set, array_intersect_key($ok_set, $this->client->set('foo', 'V1', 100000, ['bar','baz'], ['wait' => true])));
		$this->assertEquals($ok_set, array_intersect_key($ok_set, $this->client->set('bar', 'V2', 100000, ['asd'], ['wait' => true])));
		$this->assertEquals($ok_set, array_intersect_key($ok_set, $this->client->set('baz', 'V3', 100000, [], ['wait' => true])));
		$this->assertEquals($ok_set, array_intersect_key($ok_set, $this->client->set('asd', 'V4', 100000, [], ['wait' => true])));
		$this->assertEquals(['V1','V2','V3','V4'], $this->client->getMany(['foo','bar','baz','asd']));

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
			'allKeysCleared' => ['asd','bar','foo']
		];

		$clear_result = $this->client->clear(['asd'], CacheLinkClient::CLEAR_LEVELS_ALL, ['wait' => true]);
		if (!empty($clear_result['nextLevel']['keysContains'])) {
			sort($clear_result['nextLevel']['keysContains']);
		}
		if (!empty($clear_result['nextLevel']['nextLevel']['keysContains'])) {
			sort($clear_result['nextLevel']['nextLevel']['keysContains']);
		}

		$this->assertEquals($expected_clear, $clear_result);

		$this->assertEquals([null,null,'V3',null], $this->client->getMany(['foo','bar','baz','asd']));

		$this->assertEquals(
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
				'allKeysCleared' => ['baz']
			],
			$this->client->clear(['baz'], CacheLinkClient::CLEAR_LEVELS_ALL, ['wait' => true])
		);
	}

	public function testClearLater()
	{
		$ok_set = ['cacheSet' => 'OK', 'clearAssocIn' => 0, 'success' => true];
		$this->assertEquals($ok_set, $this->client->set('foo', 'V1', 100000, [], ['wait' => true]));
		$this->assertEquals($ok_set, $this->client->set('bar', 'V2', 100000, [], ['wait' => true]));
		$this->assertEquals($ok_set, $this->client->set('baz', 'V3', 100000, [], ['wait' => true]));
		$this->assertEquals($ok_set, $this->client->set('asd', 'V4', 100000, [], ['wait' => true]));
		$this->assertEquals(['V1','V2','V3','V4'], $this->client->getMany(['foo','bar','baz','asd']));

		$this->client->clearLater(['foo','bar']);
		$this->client->triggerClearNow();
		usleep(10 * 1000);

		$this->assertEquals([null,null,'V3','V4'], $this->client->getMany(['foo','bar','baz','asd']));
		$this->client->clearLater(['baz','asd']);

		$this->assertEquals([null,null,'V3','V4'], $this->client->getMany(['foo','bar','baz','asd']));
		$this->client->triggerClearNow();
		usleep(10 * 1000);
		$this->assertEquals([null,null,null,null], $this->client->getMany(['foo','bar','baz','asd']));
	}




}