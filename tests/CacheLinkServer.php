<?php

namespace Aol\CacheLink\Tests;

class CacheLinkServer
{
	private $command;
	private $output_file;
	private $pid_file;
	private $config_file;
	private $config;

	private static $instance;

	public static function getInstance()
	{
		if (!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{
		$cachelink_dir     = __DIR__ . '/node_modules/cachelink-service';
		$cachelink_bin_dir =  $cachelink_dir . '/bin';
		$this->command     = $cachelink_bin_dir . '/cachelink';
		$this->output_file = __DIR__ . '/cachelink-server.out';
		$this->pid_file    = __DIR__ . '/cachelink-server.pid';
		$this->config_file = __DIR__ . '/cachelink-config.json';
		$this->config      = json_decode(file_get_contents($this->config_file));
	}

	public function getConfig()
	{
		return $this->config;
	}

	public function start()
	{
		if (file_exists($this->output_file)) {
			unlink($this->output_file);
		}
		$this->stop();
		$full_command = sprintf("%s %s > %s 2>&1 & echo $! >> %s",
			$this->command,
			$this->config_file,
			$this->output_file,
			$this->pid_file
		);
		exec($full_command);
		while (true) {
			if (file_exists($this->output_file)) {
				$contents = file_get_contents($this->output_file);
				if ($contents && strpos($contents, 'running on port ' . $this->config->port) !== false) {
					break;
				}
			}
			usleep(10 * 1000);
		}
	}

	public function stop()
	{
		if (file_exists($this->pid_file)) {
			if ($pid = intval(file_get_contents($this->pid_file))) {
				posix_kill($pid, 9);
			}
			unlink($this->pid_file);
		}
	}

	public function __destruct()
	{
		$this->stop();
	}

}