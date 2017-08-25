<?php

namespace Aol\CacheLink\Tests;

use Aol\CacheLink\CacheLinkEncoderEncryptedAes256;

class CacheLinkClientSingleEncryptedTest extends CacheLinkClientSingleTest
{
	protected function createClient()
	{
		$client = parent::createClient();
		$client->setEncoder(new CacheLinkEncoderEncryptedAes256($client->getEncoder(), 'foo', [
			'foo' => 'test_key',
		]));
		return $client;
	}
}
