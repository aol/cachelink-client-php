<?php

namespace Aol\CacheLink;

class CacheLinkClientException extends \Exception
{
	const SOCKET_OPEN_ERROR    = 1;
	const SOCKET_WRITE_ERROR   = 2;
	const COMMUNICATION_ERROR  = 3;
	const NO_RESPONSE          = 4;
	const BAD_RESPONSE_HEADERS = 5;
	const BAD_RESPONSE_BODY    = 6;
	const BAD_RESPONSE_JSON    = 7;

	/**
	 * @inheritdoc
	 */
	public function __construct($message = "", $code = 0, \Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}
}