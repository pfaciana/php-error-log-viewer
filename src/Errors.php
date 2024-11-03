<?php

namespace ErrorLogViewer;

class Errors
{
	/** @var array Nested array structure to store error information. */
	private array $errors = [];

	/**
	 * Adds an Error instance to the collection.
	 *
	 * @param Error $error The Error instance to add.
	 */
	public function addError ( Error $error ): void
	{
		[
			'log'         => $log,
			'type'        => $type,
			'file'        => $file,
			'line'        => $line,
			'message'     => $message,
			'unix'        => $unix,
			'stack_trace' => $stack_trace,
		] = $error->toArray();

		$this->errors[$log]                                       ??= [];
		$this->errors[$log][$type]                                ??= [];
		$this->errors[$log][$type][$file]                         ??= [];
		$this->errors[$log][$type][$file][$line]                  ??= [];
		$this->errors[$log][$type][$file][$line][$message]        ??= [];
		$this->errors[$log][$type][$file][$line][$message][$unix] ??= $stack_trace;
	}

	/**
	 * Retrieves the collected error data.
	 *
	 * @return array The nested array structure containing all error information.
	 */
	public function getErrors (): array
	{
		return $this->errors;
	}

	/**
	 * Converts the error data for Tabulator JS
	 *
	 * @return array
	 */
	public function buildData ()
	{
		$errors = $this->errors;

		$table = [];

		foreach ( $errors as $log => $types ) {
			foreach ( $types as $type => $files ) {
				foreach ( $files as $file => $lines ) {
					foreach ( $lines as $line => $messages ) {
						foreach ( $messages as $message => $timestamps ) {
							$unix    = max( array_keys( $timestamps ) );
							$table[] = [ 'log' => $log, 'type' => $type, 'file' => $file, 'line' => $line, 'message' => $message, 'timestamp' => $unix, 'count' => count( $timestamps ) ];
						}
					}
				}
			}
		}

		return $table;
	}
}