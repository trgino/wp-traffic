<?php
/**
 * Traffic shared memory
 *
 * Handles all shared memory operations.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   2.0.0
 */

namespace Traffic\Plugin\Feature;

use Traffic\System\Blog;
use Traffic\System\Option;
use Traffic\System\Database;
use Traffic\System\Http;
use Traffic\System\Favicon;
use Traffic\System\Logger;
use Traffic\System\Cache;
use Traffic\System\GeoIP;
use Traffic\System\Environment;
use Traffic\System\SharedMemory;
use malkusch\lock\mutex\FlockMutex;

/**
 * Define the shared memory functionality.
 *
 * Handles all shared memory operations.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   2.0.0
 */
class Memory {

	/**
	 * Statistics buffer.
	 *
	 * @since  2.0.0
	 * @var    array    $statistics    The statistics buffer.
	 */
	private static $statistics_buffer = [];

	/**
	 * Messages buffer.
	 *
	 * @since  2.0.0
	 * @var    array    $statistics    The statistics buffer.
	 */
	private static $messages_buffer = [];

	/**
	 * GeoIP instance.
	 *
	 * @since  2.0.0
	 * @var    GeoIP    $geo_ip    Maintain the GeoIP instance..
	 */
	private static $geo_ip = null;

	/**
	 * The buffer size.
	 *
	 * @since  2.0.0
	 * @var    integer    $buffer    The number of messages in buffer.
	 */
	private static $buffer = 4000;

	/**
	 * The read index.
	 *
	 * @since  2.0.0
	 * @var    string    $index    The index for data.
	 */
	private static $index = '';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
	}

	/**
	 * Initialize static properties and hooks.
	 *
	 * @since    2.0.0
	 */
	public static function init() {
		add_action( 'shutdown', [ 'Traffic\Plugin\Feature\Schema', 'write' ], 90, 0 );
		self::$geo_ip = new GeoIP();
	}

	/**
	 * Verify if auto-logging is enabled.
	 *
	 * @since    2.0.0
	 */
	public static function is_enabled() {
		return Option::network_get( 'livelog' );
	}

	/**
	 * Write all buffers to shared memory.
	 *
	 * @since    2.0.0
	 */
	public static function write() {
		if ( self::is_enabled() ) {
			self::write_statistics();
		}
	}

	/**
	 * Write statistics.
	 *
	 * @param boolean $final Optional. If false, allows recursive calls. This is to allow to write
	 *                                 records generated while executing 'shutdown' hook.
	 * @since    2.0.0
	 */
	private static function write_statistics( $final = false ) {
		/*foreach ( self::$statistics_buffer as $key => $record ) {
			self::write_statistics_records_to_memory( $record );
			unset( self::$statistics_buffer[ $key ] );
		}
		if ( 0 < count( self::$statistics_buffer ) && ! $final ) {
			self::write_statistics( true );
		}*/
	}

	/**
	 * Get relevant ftok.
	 *
	 * @since    2.0.0
	 */
	private static function ftok() {
		if ( 1 === Environment::exec_mode() ) {
			return ftok( __FILE__, 'c' );
		} else {
			return ftok( __FILE__, 'w' );
		}
	}

	/**
	 * Effectively write the message buffer to shared memory.
	 *
	 * @since    2.0.0
	 */
	private static function write_records_to_memory() {
		// phpcs:ignore
		$messages = self::$messages_buffer;
		$mutex    = new FlockMutex( fopen( __FILE__, 'r' ), 1 );
		$ftok     = self::ftok();
		$mutex->synchronized(function () use ($messages, $ftok) {
			$sm   = new SharedMemory( $ftok );
			$data = $sm->read();
			foreach ( $messages as $key => $message ) {
				if ( is_array( $message ) ) {
					$data[ $key ] = $message;
				}
			}
			$data = array_slice( $data, -$this->buffer );
			if ( false === $sm->write( $data ) ) {
				//error_log( 'ERROR' );
			}
		} );
	}

	/**
	 * Read the current records.
	 *
	 * @return  array   The current records, ordered.
	 * @since    2.0.0
	 */
	public static function read(): array {
		try {
			$mutex = new FlockMutex( fopen( __FILE__, 'r' ), 1 );
			$ftok  = ftok( __FILE__, 'w' );
			$data1 = $mutex->synchronized( function () use ( $ftok ) {
				$log  = new SharedMemory( $ftok );
				$data = $log->read();
				return $data;
			} );
			$ftok  = ftok( __FILE__, 'c' );
			$data2 = $mutex->synchronized( function () use ( $ftok ) {
				$log  = new SharedMemory( $ftok );
				$data = $log->read();
				return $data;
			} );
			$data  = array_merge( $data1, $data2 );
			uksort($data, 'strcmp' );
		} catch ( \Throwable $e ) {
			$data = [];
		}
		$result = [];
		foreach ( $data as $key => $line ) {
			if ( 0 < strcmp( $key, self::$index ) ) {
				$result[ $key ] = $line;
				self::$index    = $key;
			}
		}
		return $result;
	}

	/**
	 * Store statistics in buffer.
	 *
	 * @param   array $record     The record to bufferize.
	 * @since    2.0.0
	 */
	public static function store_statistics( $record ) {
		$date = new \DateTime();
		self::$statistics_buffer[ $date->format( 'YmdHisu' ) ] = $record;
	}
}

Memory::init();