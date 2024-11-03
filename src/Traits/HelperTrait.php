<?php

namespace ErrorLogViewer\Traits;

use ErrorLogViewer\Error;
use ErrorLogViewer\Errors;

trait HelperTrait
{
	/** @var Errors Collection of PHP error. */
	private Errors $errors;

	static public function getWpConfigDir ()
	{
		if ( !defined( 'ABSPATH' ) ) {
			return FALSE;
		}

		if ( file_exists( dirname( \ABSPATH ) . '/wp-config.php' ) ) {
			return wp_normalize_path( trailingslashit( dirname( \ABSPATH ) ) );
		}

		return wp_normalize_path( trailingslashit( \ABSPATH ) );
	}

	protected function isErrorLog ( $fileName )
	{
		if ( str_contains( $fileName, '.' ) && !str_ends_with( $fileName, '.log' ) ) {
			return FALSE;
		}

		return str_starts_with( $fileName, 'error_log' ) || str_contains( $fileName, 'php_error' ) || str_contains( $fileName, 'debug.log' );
	}

	protected function searchErrorLogs ( $directory, $args = [] )
	{
		$args = wp_parse_args( $args, [
			'directory_depth'     => 7,
			'exclude_directories' => [ 'node_modules', 'vendor' ],
			'include_filenames'   => [],
		] );

		$directory         = wp_normalize_path( $directory );
		$result            = [];
		$directoryIterator = new \RecursiveDirectoryIterator( $directory, \RecursiveDirectoryIterator::SKIP_DOTS );
		$iterator          = new \RecursiveIteratorIterator( $directoryIterator, \RecursiveIteratorIterator::SELF_FIRST );
		$iterator->setMaxDepth( $args['directory_depth'] );

		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				$dirName = $file->getBasename();
				if ( in_array( $dirName, (array) $args['exclude_directories'] ) ) {
					$iterator->next();
				}
			}
			elseif ( $file->isFile() && ( in_array( $file->getFilename(), (array) $args['include_filenames'] ) || $this->isErrorLog( $file->getFilename() ) ) ) {
				$result[] = wp_normalize_path( $file->getPathname() );
			}
		}

		return $result;
	}

	static public function parseLargeFile ( $filePath )
	{
		if ( !file_exists( $filePath ) ) {
			return NULL;
		}

		if ( empty( $fileHandle = @fopen( $filePath, 'r' ) ) ) {
			return $fileHandle;
		}

		$lineNumber = 0;
		while ( ( $line = fgets( $fileHandle ) ) !== FALSE ) {
			yield ++$lineNumber => rtrim( $line );
		}
		fclose( $fileHandle );

		return TRUE;
	}

	protected function getErrorsFromLogs ( $logFiles = [] )
	{
		$this->errors  = new Errors;
		$error         = NULL;
		$pattern       = '/^\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2}) +([^\]]*)\] ([^:]*): (.+) in (.+)(?: on line |:)(\d+)$/';
		$localTimezone = date_default_timezone_get() ?: ini_get( 'date.timezone' ) ?: 'UTC';

		foreach ( $logFiles as $logFile ) {

			foreach ( static::parseLargeFile( $logFile ) as $line ) {
				if ( preg_match( $pattern, $line, $matches ) ) {
					if ( $error !== NULL ) {
						$this->errors->addError( $error );
					}
					$error = new Error( $matches, $localTimezone, $logFile );
				}
				elseif ( $error !== NULL ) {
					$error->addToStackTrace( $line );
				}
			}

		}

		if ( $error !== NULL ) {
			$this->errors->addError( $error );
		}
	}

	protected function getData ( $logFiles = [] )
	{
		$this->getErrorsFromLogs( $logFiles );

		return $this->errors->buildData();
	}

	static public function removeErrorsFromLogs ( $error )
	{
		$logFile    = $error['log'];
		$tmpLogFile = $logFile . '.tmp';
		unset( $error['log'], $error['timestamp'], $error['count'] );
		$error['line'] = (int) $error['line'];
		ksort( $error );

		$pattern = '/^\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2}) +([^\]]*)\] ([^:]*): (.+) in (.+)(?: on line |:)(\d+)$/';
		$isMatch = FALSE;

		$tempFileHandle = fopen( $tmpLogFile, 'w' );
		foreach ( static::parseLargeFile( $logFile ) as $lineNumber => $line ) {
			if ( preg_match( $pattern, $line, $matches ) ) {
				$isMatch = $error === [
						'file'    => wp_normalize_path( trim( $matches[5] ) ),
						'line'    => (int) trim( $matches[6] ),
						'message' => trim( $matches[4] ),
						'type'    => trim( $matches[3] ),
					];
			}

			if ( !$isMatch ) {
				fwrite( $tempFileHandle, $line . \PHP_EOL );
			}
		}
		fclose( $tempFileHandle );

		if ( !@rename( $tmpLogFile, $logFile ) ) {
			return FALSE;
		}

		return static::getFileSize( $logFile );
	}

	static public function getFileSize ( string $filename )
	{
		if ( empty( $filesize = filesize( $filename ) ) ) {
			return '0 bytes';
		}

		return number_format( $filesize / 1024 ) . ' KB';
	}

	static public function formatFileLink ( string $file, int $line = 1 )
	{
		if ( empty( $format = ini_get( 'xdebug.file_link_format' ) ) ) {
			return NULL;
		}

		return str_replace( [ '%f', '%l' ], [ urlencode( wp_normalize_path( $file ) ), $line ], $format );
	}

	static public function getMaybeFileLink ( $text, string $file, int $line = 1 )
	{
		$text = !empty( $text ) && $text != 0 ? $text : $file;

		if ( empty( $link = static::formatFileLink( $file, $line ) ) ) {
			return $text;
		}

		ob_start();
		?><a class="ide-link" href="<?= $link ?>" target="_blank"><?= $text ?></a><?php
		return ob_get_clean();
	}

	static public function flush ()
	{
		ob_flush(); // get the buffer
		flush(); // send it to the browser
	}
}