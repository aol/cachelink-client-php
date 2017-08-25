<?php

namespace Aol\CacheLink;

use Aol\CacheLink\Exceptions\CacheLinkEncoderException;

class CacheLinkEncoderStandard implements CacheLinkEncoderInterface
{
	/**
	 * @inheritdoc
	 */
	public function encode($data)
	{
		try {
			return serialize($data);
		} catch (\Exception $ex) {
			throw new CacheLinkEncoderException('CacheLink could not serialize data: ' . $ex->getMessage(), 0, $ex);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function decode($value)
	{
		try {
			return unserialize($value);
		} catch (\Exception $ex) {
			throw new CacheLinkEncoderException('CacheLink could not unserialize data: ' . $ex->getMessage(), 0, $ex);
		}
	}
}
