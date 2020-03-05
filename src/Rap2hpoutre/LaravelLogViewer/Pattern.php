<?php

namespace Rap2hpoutre\LaravelLogViewer;

/**
 * Class Pattern
 * @property array patterns
 * @package Rap2hpoutre\LaravelLogViewer
 */

class Pattern
{
	private $datetime_pattern = '(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})';
	private $context_pattern = '(\w+)';
	private $level_pattern = '(\w+)';
	private $error_text_pattern = '(.+?(?={"\w))';
	private $metadata_pattern = '(.+?(?=\n\[\d|\s*\Z))';

	/**
	 * @var array
	 */
	private function getPatterns()
	{
		return [
			'log_entry' => '~^\[' . $this->datetime_pattern . '\]\s+' . $this->context_pattern . '.' . $this->level_pattern . ':\s+' . $this->error_text_pattern . $this->metadata_pattern . '~ismu',
			'files' => '/\{.*?\,.*?\}/i',
			'stack_line' => '~#(\d+)\s+(?:(\[internal function\])|(.+?\.php)\((\d+)\)):\s+(.+)~',
		];
	}

	/**
	 * @return array
	 */
	public function all()
	{
		return array_keys($this->getPatterns());
	}

	/**
	 * @param $pattern
	 * @param null $position
	 * @return string pattern
	 */
	public function getPattern($pattern, $position = null)
	{
		if ($position !== null) {
			return $this->getPatterns()[$pattern][$position];
		}
		return $this->getPatterns()[$pattern];
	}
}
