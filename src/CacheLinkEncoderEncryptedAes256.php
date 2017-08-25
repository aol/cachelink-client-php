<?php

namespace Aol\CacheLink;

class CacheLinkEncoderEncryptedAes256 extends CacheLinkEncoderEncrypted
{
	const ENCRYPTION_METHOD = 'AES-256-CBC';

	protected function encrypt($data, $key)
	{
		$iv = random_bytes(16);
		return $iv . openssl_encrypt($data, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
	}

	protected function decrypt($string, $key)
	{
		$iv = mb_substr($string, 0, 16, '8bit');
		$encrypted = mb_substr($string, 16, null, '8bit');
		return openssl_decrypt($encrypted, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
	}
}
