<?php

namespace ErrorLogViewer;

class Error
{
	/** @var int The Unix timestamp when the error occurred. */
	public int $unix;

	/** @var string The formatted timestamp of when the error occurred. */
	public string $timestamp;

	/** @var string The timezone in which the error timestamp is recorded. */
	public string $timezone;

	/** @var string The type of error (e.g., 'Warning', 'Fatal error', 'Notice'). */
	public string $type;

	/** @var string The error message. */
	public string $message;

	/** @var string The file path where the error occurred. */
	public string $file;

	/** @var int The line number where the error occurred. */
	public int $line;

	/** @var string The log file name where this error was found. */
	public string $log;

	/** @var string[] The stack trace of the error, if available. */
	public array $stackTrace = [];

	/**
	 * Constructs a new Error instance from regex matches.
	 *
	 * @param array  $matches       Regex match groups containing error information.
	 * @param string $localTimezone Default timezone to use if not specified in the log.
	 * @param string $logFile       Name of the log file where this error was found.
	 */
	public function __construct ( array $matches, string $localTimezone, string $logFile )
	{
		$this->timestamp = trim( $matches[1] );
		$this->timezone  = in_array( $timezone = trim( $matches[2] ), timezone_identifiers_list() ) ? $timezone : $localTimezone;
		$this->type      = trim( $matches[3] );
		$this->message   = trim( $matches[4] );
		$this->file      = wp_normalize_path( trim( $matches[5] ) );
		$this->line      = (int) trim( $matches[6] );
		$this->log       = $logFile;
		$this->unix      = strtotime( $this->timestamp . ' ' . $this->timezone );
	}

	/**
	 * Adds a line to the stack trace of this error.
	 *
	 * @param string $line The line to add to the stack trace.
	 */
	public function addToStackTrace ( string $line ): void
	{
		$this->stackTrace[] = trim( $line );
	}

	/**
	 * Converts the error data to an associative array.
	 *
	 * @return array An associative array representing the error data.
	 */
	public function toArray (): array
	{
		return [
			'log'         => $this->log,
			'type'        => $this->type,
			'file'        => $this->file,
			'line'        => $this->line,
			'message'     => $this->message,
			'unix'        => $this->unix,
			'stack_trace' => $this->stackTrace,
		];
	}
}