<?php

namespace Aol\CacheLink\Tests;

use Aol\CacheLink\CacheLinkEncoderEncryptedAes256;
use Aol\CacheLink\CacheLinkEncoderStandard;

class CacheLinkEncoderEncryptedTest extends CacheLinkEncoderTest
{
	public function setUp()
	{
		parent::setUp();
		$this->encoder = new CacheLinkEncoderEncryptedAes256($this->encoder, 'foo', [
			'foo' => 'fookey',
			'bar' => 'barkey'
		]);
	}

	/**
	 * @expectedException \Aol\CacheLink\Exceptions\CacheLinkException
	 * @expectedExceptionMessage Invalid $encryption_key_id
	 */
	public function testEncryptionIdCannotHaveColon()
	{
		new CacheLinkEncoderEncryptedAes256(new CacheLinkEncoderStandard(), 'foo:bar', []);
	}

	/**
	 * @expectedException \Aol\CacheLink\Exceptions\CacheLinkException
	 * @expectedExceptionMessage Missing $encryption_key_id
	 */
	public function testEncryptionIdMustBePresentInKeys()
	{
		new CacheLinkEncoderEncryptedAes256(new CacheLinkEncoderStandard(), 'foo', ['bar'=>'baz']);
	}

	/**
	 * @expectedException \Aol\CacheLink\Exceptions\CacheLinkEncoderException
	 * @expectedExceptionMessage missing key ID
	 */
	public function testDecodeMustReceiveKeyWithColon()
	{
		$this->encoder->decode('not_a_valid_key');
	}

	/**
	 * @expectedException \Aol\CacheLink\Exceptions\CacheLinkEncoderException
	 * @expectedExceptionMessage unknown key ID
	 */
	public function testDecodeMustReceiveValidKey()
	{
		$this->encoder->decode('bad_key:foobar');
	}

	/**
	 * @expectedException \Aol\CacheLink\Exceptions\CacheLinkEncoderException
	 * @expectedExceptionMessage could not unserialize data
	 */
	public function testDecodeFailsWithBadData()
	{
		$this->encoder->decode('foo:' . base64_encode('$%^&*('));
	}

	public function testDecodeFailureUsesBaseWhenFlagSet()
	{
		$base = self::getMockBuilder(CacheLinkEncoderStandard::class)->setMethods(['decode'])->getMock();
		$base->expects(self::once())->method('decode')->willReturn('test');
		$encoder = new CacheLinkEncoderEncryptedAes256($base, 'foo', ['foo' => 'bar'], true);
		self::assertEquals('test', $encoder->decode('foo:' . base64_encode('$%^&*(')));
	}

	public function testDecodesStandardIfNotEncrypted()
	{
		$base = new CacheLinkEncoderStandard();
		$encoder = new CacheLinkEncoderEncryptedAes256($base, 'foo', ['foo' => 'bar'], true);
		$encoded = $base->encode('hello world');
		self::assertEquals('hello world', $encoder->decode($encoded));
	}
}
