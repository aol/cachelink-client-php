<?php

namespace Aol\CacheLink;

class CacheLinkServerException extends \Exception
{
	/**
	 * @inheritdoc
	 */
	public function __construct($message = "", $code = 0, \Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}
}