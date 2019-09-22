<?php
/**
 * Traffic schema
 *
 * Handles all schema operations.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace Traffic\Plugin\Feature;

use Traffic\System\Http;
use Traffic\System\Logger;
use Traffic\System\Cache;
use function GuzzleHttp\Psr7\str;

/**
 * Define the schema functionality.
 *
 * Handles all schema operations.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class Schema {

	/**
	 * Statistics table name.
	 *
	 * @since  1.0.0
	 * @var    string    $statistics    The statistics table name.
	 */
	private static $statistics = TRAFFIC_SLUG . '_statistics';

	/**
	 * Statistics buffer.
	 *
	 * @since  1.0.0
	 * @var    array    $statistics    The statistics buffer.
	 */
	private static $statistics_buffer = [];

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Initialize static properties and hooks.
	 *
	 * @since    1.0.0
	 */
	public static function init() {
		add_action( 'shutdown', [ 'Traffic\Plugin\Feature\Schema', 'write' ], 90, 0 );
	}

	/**
	 * Write all buffers to database.
	 *
	 * @since    1.0.0
	 */
	public static function write() {
		self::write_statistics();
	}

	/**
	 * Write statistics.
	 *
	 * @since    1.0.0
	 */
	private static function write_statistics() {
		foreach ( self::$statistics_buffer as $record ) {
			self::write_statistics_records_to_database( $record );
		}
	}

	/**
	 * Effectively write a buffer element in the database.
	 *
	 * @param   array $record     The record to write.
	 * @since    1.0.0
	 */
	private static function write_statistics_records_to_database( $record ) {
		global $wpdb;
		$field_insert = [];
		$value_insert = [];
		$value_update = [];
		foreach ( $record as $k => $v ) {
			$field_insert[] = '`' . $k . '`';
			$value_insert[] = "'" . $v . "'";
			if ( 'hit' === $k ) {
				$value_update[] = '`hit`=hit + 1';
			}
			if ( 'kb_in' === $k ) {
				$value_update[] = '`kb_in`=kb_in + ' . $v;
			}
			if ( 'kb_out' === $k ) {
				$value_update[] = '`kb_out`=kb_out + ' . $v;
			}
			if ( 'latency_min' === $k ) {
				$value_update[] = '`latency_min`=if(latency_min>' . $v . ',' . $v . ',latency_min)';
			}
			if ( 'latency_avg' === $k ) {
				$value_update[] = '`latency_avg`=((latency_avg*hit)+' . $v . ')/(hit+1)';
			}
			if ( 'latency_max' === $k ) {
				$value_update[] = '`latency_max`=if(latency_max<' . $v . ',' . $v . ',latency_max)';
			}
		}
		if ( count( $field_insert ) > 0 ) {
			global $wpdb;
			$sql  = 'INSERT INTO `' . $wpdb->base_prefix . self::$statistics . '` ';
			$sql .= '(' . implode( ',', $field_insert ) . ') ';
			$sql .= 'VALUES (' . implode( ',', $value_insert ) . ') ';
			$sql .= 'ON DUPLICATE KEY UPDATE ' . implode( ',', $value_update ) . ';';
			// phpcs:ignore
			$wpdb->query( $sql );
		}
	}

	/**
	 * Store statistics in buffer.
	 *
	 * @param   array $record     The record to bufferize.
	 * @since    1.0.0
	 */
	public static function store_statistics( $record ) {
		self::$statistics_buffer[] = $record;
	}

	/**
	 * Initialize the schema.
	 *
	 * @since    1.0.0
	 */
	public function initialize() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = 'CREATE TABLE IF NOT EXISTS ' . $wpdb->base_prefix . self::$statistics;
		$sql            .= " (`timestamp` date NOT NULL DEFAULT '0000-00-00',";
		$sql            .= " `site` bigint(20) NOT NULL DEFAULT '0',";
		$sql            .= " `context` enum('" . implode( "','", Http::$contexts ) . "') NOT NULL DEFAULT 'unknown',";
		$sql            .= " `id` varchar(40) NOT NULL DEFAULT '-',";
		$sql            .= " `verb` enum('" . implode( "','", Http::$verbs ) . "') NOT NULL DEFAULT 'unknown',";
		$sql            .= " `scheme` enum('" . implode( "','", Http::$schemes ) . "') NOT NULL DEFAULT 'unknown',";
		$sql            .= " `authority` varchar(250) NOT NULL DEFAULT '-',";
		$sql            .= " `endpoint` varchar(250) NOT NULL DEFAULT '-',";
		$sql            .= " `code` smallint UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= " `hit` int(11) UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= " `latency_min` smallint UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= " `latency_avg` smallint UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= " `latency_max` smallint UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= " `kb_in` int(11) UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= " `kb_out` int(11) UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= ' UNIQUE KEY u_stat (timestamp, site, context, id, verb, scheme, authority, endpoint, code)';
		$sql            .= ") $charset_collate;";
		// phpcs:ignore
		$wpdb->query( $sql );
		Logger::debug( sprintf( 'Table "%s" created.', $wpdb->base_prefix . self::$statistics ) );
		Logger::debug( 'Schema installed.' );
	}

	/**
	 * Finalize the schema.
	 *
	 * @since    1.0.0
	 */
	public function finalize() {
		global $wpdb;
		$sql = 'DROP TABLE IF EXISTS ' . $wpdb->base_prefix . self::$statistics;
		// phpcs:ignore
		$wpdb->query( $sql );
		Logger::debug( sprintf( 'Table "%s" removed.', $wpdb->base_prefix . self::$statistics ) );
		Logger::debug( 'Schema destroyed.' );
	}

	/**
	 * Get an empty record.
	 *
	 * @return  array   An empty, ready to use, record.
	 * @since    1.0.0
	 */
	public static function init_record() {
		$record = [
			'timestamp'   => '0000-00-00',
			'site'        => 0,
			'context'     => 'unknown',
			'id'          => '-',
			'verb'        => 'unknown',
			'scheme'      => 'unknown',
			'authority'   => '-',
			'endpoint'    => '-',
			'code'        => 0,
			'hit'         => 1,
			'latency_min' => 0,
			'latency_avg' => 0,
			'latency_max' => 0,
			'kb_in'       => 0,
			'kb_out'      => 0,
		];
		return $record;
	}

	/**
	 * Get "where" clause of a query.
	 *
	 * @param array $filters Optional. An array of filters.
	 * @return string The "where" clause.
	 * @since 1.0.0
	 */
	private static function get_where_clause( $filters = [] ) {
		$result = '';
		if ( 0 < count( $filters ) ) {
			$w = [];
			foreach ( $filters as $key => $filter ) {
				if ( is_array( $filter ) ) {
					$w[] = '`' . $key . '` IN (' . implode( ',', $filter ) . ')';
				} else {
					$w[] = '`' . $key . '`="' . $filter . '"';
				}
			}
			$result = 'WHERE (' . implode( ' AND ', $w ) . ')';
		}
		return $result;
	}

	/**
	 * Get the oldest date.
	 *
	 * @return  string   The oldest timestamp in the statistics table.
	 * @since    1.0.0
	 */
	public static function get_oldest_date() {
		$result = Cache::get_global( 'get_oldest_date' );
		if ( $result ) {
			return $result;
		}
		global $wpdb;
		$sql = 'SELECT * FROM ' . $wpdb->base_prefix . self::$statistics . ' ORDER BY `timestamp` ASC LIMIT 1';
		// phpcs:ignore
		$result = $wpdb->get_results( $sql, ARRAY_A );
		if ( is_array( $result ) && array_key_exists( 'timestamp', $result[0] ) ) {
			Cache::set_global( 'get_oldest_date', $result[0]['timestamp'], 'infinite' );
			return $result[0]['timestamp'];
		}
		return '';
	}

	/**
	 * Get the distinct contexts.
	 *
	 * @param   array $filter   The filter of the query.
	 * @return  array   The distinct contexts.
	 * @since    1.0.0
	 */
	public static function get_distinct_context( $filter, $cache = true ) {
		if ( array_key_exists( 'context', $filter ) ) {
			unset( $filter['context'] );
		}
		// phpcs:ignore
		$id     = md5( serialize( $filter ) );
		if ( $cache ) {
			$result = Cache::get_global( $id );
			if ( $result ) {
				return $result;
			}
		}
		global $wpdb;
		$sql = 'SELECT DISTINCT context FROM ' . $wpdb->base_prefix . self::$statistics . ' WHERE (' . implode( ' AND ', $filter ) . ')';
		// phpcs:ignore
		$result = $wpdb->get_results( $sql, ARRAY_A );
		if ( is_array( $result ) && 0 < count( $result ) ) {
			$contexts = [];
			foreach ( $result as $item ) {
				$contexts[] = $item['context'];
			}
			if ( $cache ) {
				Cache::set_global( $id, $contexts, 'infinite' );
			}
			return $contexts;
		}
		return [];
	}
}

Schema::init();
