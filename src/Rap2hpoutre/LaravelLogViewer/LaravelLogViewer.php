<?php

namespace Rap2hpoutre\LaravelLogViewer;

/**
 * Class LaravelLogViewer
 * @package Rap2hpoutre\LaravelLogViewer
 */
class LaravelLogViewer
{
	/**
	 * @var string file
	 */
	private $file;

	/**
	 * @var string folder
	 */
	private $folder;

	/**
	 * @var string storage_path
	 */
	private $storage_path;

	/**
	 * Why? Uh... Sorry
	 */
	const MAX_FILE_SIZE = 52428800;

	/**
	 * @var Level level
	 */
	private $level;

	/**
	 * @var Pattern pattern
	 */
	private $pattern;

	/**
	 * LaravelLogViewer constructor.
	 */
	public function __construct()
	{
		$this->level = new Level();
		$this->pattern = new Pattern();
		$this->storage_path = function_exists('config') ? config('logviewer.storage_path', storage_path('logs')) : storage_path('logs');
	}

	/**
	 * @param string $date
	 */
	private function prepareJSON($json)
	{
		$json = preg_replace('~\n~', '\n', $json);
		return $json;
	}

	private function detectStackLineType($file_name)
	{
		if (mb_stripos($file_name, '/vendor/') !== false) {
			return 'vendor';
		}
		return 'user';
	}

	/**
	 * @param string $date
	 */
	private function splitStackIntoLines($stack_trace)
	{
		return preg_split('~\s*\r?\n\s*~m', $stack_trace, -1, PREG_SPLIT_NO_EMPTY);
	}

	/**
	 * @param string $date
	 */
	private function formatLogDate($date)
	{
		$date = trim($date);
		return $date;
	}

	/**
	 * @param string $value
	 */
	private function formatLogValue($value)
	{
		$value = trim($value);
		return $value;
	}

	/**
	 * @param string $level
	 */
	private function formatLogLevel($level)
	{
		$level = $this->formatLogValue($level);
		$level = mb_convert_case($level, MB_CASE_LOWER);
		return $level;
	}

	/**
	 * @param string $text
	 */
	private function stripDocumentRootFromText($text)
	{
		$document_root = base_path();
		return str_ireplace($document_root, '', $text);
	}

	/**
	 * @param string $text
	 */
	private function formatText($text)
	{
		$text = $this->stripDocumentRootFromText($text);
		$text = $this->formatLogValue($text);
		return $text;
	}

	/**
	 * @param string $stack_line
	 */
	private function splitStackLine($stack_line)
	{
		$stack_line_pattern = $this->pattern->getPattern('stack_line');
		if (preg_match($stack_line_pattern, $stack_line, $matches)) {
			array_shift($matches);
			return $matches;
		}
		return [0, '', '', 0, ''];
	}

	/**
	 * @param array $block_storage
	 * @param string $block_type
	 * @param string $stack_line
	 * @param int $block_number
	 */
	private function addLineToBlock($block_storage, $block_type, $stack_line, $block_number)
	{
		$key = $block_type . '_' . $block_number;
		if (!array_key_exists($key, $block_storage)) {
			$block_storage[$key] = (object) ['type' => $block_type, 'number' => $block_number, 'lines' => []];
		}
		$block_storage[$key]->lines[] = $stack_line;
		return $block_storage;
	}

	/**
	 * @param array $block_storage
	 * @param string $stack_line
	 * @param int $block_number
	 */
	private function addLineToUserBlock($block_storage, $stack_line, $block_number)
	{
		return $this->addLineToBlock($block_storage, 'user', $stack_line, $block_number);
	}
	private function addLineToVendorBlock($block_storage, $stack_line, $block_number)
	{
		return $this->addLineToBlock($block_storage, 'vendor', $stack_line, $block_number);
	}

	/**
	 * @param string $error_text
	 */
	private function formatStackTrace($stack_trace)
	{
		$stack_trace = $this->prepareJSON($stack_trace);
		$json_data = json_decode($stack_trace);
		if (!isset($json_data->exception)) {
			return $stack_trace;
		}
		$stack_lines = $this->splitStackIntoLines($json_data->exception);
		$output = [];
		$skip_lines = ['[stacktrace]'];
		$block_counters = [
			'vendor' => 0,
			'user' => 0
		];
		$save_block = '';
		$line_type = 'vendor';
		foreach ($stack_lines as $line_number => $stack_line) {
			if (in_array($stack_line, $skip_lines)) {
				continue;
			}
			$stack_line = $this->stripDocumentRootFromText($stack_line);
			if ($line_number == 0) {
				$line_type = 'user';
				continue;
			} else {
				list($stack_position, $internal, $file_name, $file_line_number, $code_line) = $this->splitStackLine($stack_line);
				if ($internal) {
					$line_type = 'user';
					$stack_line  = '<div class="user code-line">' . $code_line . '</div>';
				} else {
					if (!$file_line_number) {
						continue;
					}
					$line_type = $this->detectStackLineType($file_name);
					$stack_line = '<div class="' . $line_type . ' file-name">' . $file_name . ' at line ' . $file_line_number . '</div><div class="' . $line_type . ' code-line">' . $code_line . '</div>';
				}
			}

			if ($save_block && $save_block != $line_type) {
				$block_counters[$line_type]++;
			}
			$save_block = $line_type;
			$output = $this->addLineToBlock($output, $line_type, $stack_line, $block_counters[$line_type]);
		}
		$html_output = '';
		foreach ($output as $block) {
			switch ($block->type) {
				case 'user':
					$html_output .= '<ul class="stack-trace user">';
					foreach ($block->lines as $line) {
						$html_output .= '<li>' . $line . '</li>';
					}
					$html_output .= '</ul>';
					break;
				case 'vendor':
					$line_count = count($block->lines);
					if ($line_count < 3) {
						$html_output .= '<ul class="stack-trace vendor">';
						foreach ($block->lines as $line) {
							$html_output .= '<li>' . $line . '</li>';
						}
						$html_output .= '</ul>';
					} else {
						$html_output .= '<ul class="stack-trace vendor">';
						$html_output .= '<li>' . $block->lines[0] . '</li>';
						$html_output .= '</ul>';
						$html_output .= '<div class="toggle-block" data-type="' . $block->type . '" data-number="' . $block->number . '">Show ' . ($line_count - 1) . ' more ↓</div>';
						$html_output .= '<ul class="stack-trace vendor hidden" data-type="' . $block->type . '" data-number="' . $block->number . '">';
						for ($i = 1; $i < $line_count; $i++) {
							$html_output .= '<li>' . $block->lines[$i] . '</li>';
						}
						$html_output .= '</ul>';
					}
					break;
			}
		}
		return $html_output;
	}

	/**
	 * @param string $folder
	 */
	public function setFolder($folder)
	{
		if (app('files')->exists($folder)) {
			$this->folder = $folder;
		}
		if (is_array($this->storage_path)) {
			foreach ($this->storage_path as $value) {
				$logsPath = $value . '/' . $folder;
				if (app('files')->exists($logsPath)) {
					$this->folder = $folder;
					break;
				}
			}
		} else {
			if ($this->storage_path) {
				$logsPath = $this->storage_path . '/' . $folder;
				if (app('files')->exists($logsPath)) {
					$this->folder = $folder;
				}
			}
		}
	}

	/**
	 * @param string $file
	 * @throws \Exception
	 */
	public function setFile($file)
	{
		$file = $this->pathToLogFile($file);

		if (app('files')->exists($file)) {
			$this->file = $file;
		}
	}

	/**
	 * @param string $file
	 * @return string
	 * @throws \Exception
	 */
	public function pathToLogFile($file)
	{

		if (app('files')->exists($file)) { // try the absolute path
			return $file;
		}
		if (is_array($this->storage_path)) {
			foreach ($this->storage_path as $folder) {
				if (app('files')->exists($folder . '/' . $file)) { // try the absolute path
					$file = $folder . '/' . $file;
					break;
				}
			}
			return $file;
		}

		$logsPath = $this->storage_path;
		$logsPath .= ($this->folder) ? '/' . $this->folder : '';
		$file = $logsPath . '/' . $file;
		// check if requested file is really in the logs directory
		if (dirname($file) !== $logsPath) {
			throw new \Exception('No such log file');
		}
		return $file;
	}

	/**
	 * @return string
	 */
	public function getFolderName()
	{
		return $this->folder;
	}

	/**
	 * @return string
	 */
	public function getFileName()
	{
		return basename($this->file);
	}

	/**
	 * @return array
	 */
	public function all()
	{
		$log = [];

		if (!$this->file) {
			$log_file = (!$this->folder) ? $this->getFiles() : $this->getFolderFiles();
			if (!count($log_file)) {
				return [];
			}
			$this->file = $log_file[0];
		}

		$max_file_size = function_exists('config') ? config('logviewer.max_file_size', self::MAX_FILE_SIZE) : self::MAX_FILE_SIZE;
		if (app('files')->size($this->file) > $max_file_size) {
			return null;
		}

		$file = app('files')->get($this->file);
		if (!preg_match_all($this->pattern->getPattern('log_entry'), $file, $log_entries, PREG_SET_ORDER)) {
			dd('die');
		}

		foreach ($log_entries as $log_entry) {

			$context = $this->formatLogValue($log_entry[2]);
			$level = $this->formatLogLevel($log_entry[3]);
			$text = $this->formatText($log_entry[4]);
			$stack_trace = $this->formatStackTrace($log_entry[5]);
			$date = $this->formatLogDate($log_entry[1]);

			$log[] = array(
				'context' => $context,
				'level' => $level,
				'folder' => $this->folder,
				'level_class' => $this->level->cssClass($level),
				'level_img' => $this->level->img($level),
				'date' => $log_entry[1],
				'text' => $text,
				'in_file' =>  $date,
				'stack' => $stack_trace,
			);
		}

		if (empty($log)) {

			$lines = explode(PHP_EOL, $file);
			$log = [];

			foreach ($lines as $key => $line) {
				$log[] = [
					'context' => '',
					'level' => '',
					'folder' => '',
					'level_class' => '',
					'level_img' => '',
					'date' => $key + 1,
					'text' => $line,
					'in_file' => null,
					'stack' => '',
				];
			}
		}

		return array_reverse($log);
	}

	/**
	 * @return array
	 */
	public function getFolders()
	{
		$folders = glob($this->storage_path . '/*', GLOB_ONLYDIR);
		if (is_array($this->storage_path)) {
			foreach ($this->storage_path as $value) {
				$folders = array_merge(
					$folders,
					glob($value . '/*', GLOB_ONLYDIR)
				);
			}
		}

		if (is_array($folders)) {
			foreach ($folders as $k => $folder) {
				$folders[$k] = basename($folder);
			}
		}
		return array_values($folders);
	}

	/**
	 * @param bool $basename
	 * @return array
	 */
	public function getFolderFiles($basename = false)
	{
		return $this->getFiles($basename, $this->folder);
	}

	/**
	 * @param bool $basename
	 * @param string $folder
	 * @return array
	 */
	public function getFiles($basename = false, $folder = '')
	{
		$pattern = function_exists('config') ? config('logviewer.pattern', '*.log') : '*.log';
		$files = glob(
			$this->storage_path . '/' . $folder . '/' . $pattern,
			preg_match($this->pattern->getPattern('files'), $pattern) ? GLOB_BRACE : 0
		);
		if (is_array($this->storage_path)) {
			foreach ($this->storage_path as $value) {
				$files = array_merge(
					$files,
					glob(
						$value . '/' . $folder . '/' . $pattern,
						preg_match($this->pattern->getPattern('files'), $pattern) ? GLOB_BRACE : 0
					)
				);
			}
		}

		$files = array_reverse($files);
		$files = array_filter($files, 'is_file');
		if ($basename && is_array($files)) {
			foreach ($files as $k => $file) {
				$files[$k] = basename($file);
			}
		}
		return array_values($files);
	}
}
