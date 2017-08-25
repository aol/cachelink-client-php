<?php

namespace Aol\CacheLink;

interface CacheLinkEncoderInterface
{
	/**
	 * Encode data into a string value.
	 *
	 * @param mixed $data The data to encode.
	 *
	 * @return string The encoded value.
	 */
	public function encode($data);

	/**
	 * Decode a string value back into data.
	 *
	 * @param string $value The encoded string.
	 *
	 * @return mixed The decoded data.
	 */
	public function decode($value);
}
