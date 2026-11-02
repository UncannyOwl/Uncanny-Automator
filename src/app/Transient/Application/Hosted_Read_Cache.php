<?php
/**
 * Hosted read cache use case.
 *
 * @package Uncanny_Automator
 * @since 8.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transient\Application;

use Uncanny_Automator\App\Transient\Domain\Hosted_Read_Transient_Keys;
use Uncanny_Automator\App\Transient\Infrastructure\WP_Transient_Adapter;
use Uncanny_Automator\App\Transient\Ports\Transient_Port;

/**
 * Keeps the AI Dashboard's reads from Automator MCP for five minutes, so a
 * Dashboard refresh does not call Automator MCP again, and drops them all at
 * once when the site connects, reconnects or disconnects.
 *
 * Every key carries the plugin version, so new code never reads what an
 * earlier version kept, even when the files were replaced without an update.
 */
class Hosted_Read_Cache {

	/**
	 * Transient storage port.
	 *
	 * @var Transient_Port
	 */
	private Transient_Port $transients;

	/**
	 * The plugin version the keys carry.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Create the use case.
	 *
	 * @param Transient_Port|null $transients The transient storage port.
	 * @param string|null         $version    The plugin version the keys carry.
	 */
	public function __construct( ?Transient_Port $transients = null, ?string $version = null ) {
		$this->transients = $transients ?? new WP_Transient_Adapter();
		$this->version    = $version ?? AUTOMATOR_PLUGIN_VERSION;
	}

	/**
	 * The kept read, or a fresh one from `$fetch`. Only an array is kept; an
	 * error is returned and never kept.
	 *
	 * The read is kept under the generation it started in, so a reconnect that
	 * clears the cache while the read is in flight leaves it behind with the
	 * old generation instead of bringing old data back.
	 *
	 * @param string   $scope The credential the read is made with.
	 * @param string   $read  The read path and query.
	 * @param callable $fetch Makes the read: returns an array, or an error.
	 *
	 * @return mixed The kept or fresh read.
	 */
	public function remember( string $scope, string $read, callable $fetch ) {
		$key = $this->key( $scope, $read );

		$kept = $this->transients->get( $key );
		if ( is_array( $kept ) ) {
			return $kept;
		}

		$fresh = $fetch();
		if ( is_array( $fresh ) ) {
			$this->transients->add( $key, $fresh, Hosted_Read_Transient_Keys::TTL );
		}

		return $fresh;
	}

	/**
	 * A fresh read from `$fetch`, kept in place of any earlier one, for a caller
	 * that can't wait for the kept read to expire. An error is returned and
	 * leaves the kept read as it was.
	 *
	 * Like remember(), it keeps the read under the generation it started in.
	 *
	 * @param string   $scope The credential the read is made with.
	 * @param string   $read  The read path and query.
	 * @param callable $fetch Makes the read: returns an array, or an error.
	 *
	 * @return mixed The fresh read.
	 */
	public function refresh( string $scope, string $read, callable $fetch ) {
		$key = $this->key( $scope, $read );

		$fresh = $fetch();
		if ( is_array( $fresh ) ) {
			$this->transients->edit( $key, $fresh, Hosted_Read_Transient_Keys::TTL );
		}

		return $fresh;
	}

	/**
	 * Drop every kept read. The old reads expire on their own.
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->start_generation();
	}

	/**
	 * Where a read is kept in the current generation.
	 *
	 * @param string $scope The credential the read is made with.
	 * @param string $read  The read path and query.
	 *
	 * @return string
	 */
	private function key( string $scope, string $read ): string {
		$generation = $this->generation() ?? $this->start_generation();

		return Hosted_Read_Transient_Keys::read( $generation, $this->version . "\0" . $scope, $read );
	}

	/**
	 * The current generation, or null when there is none.
	 *
	 * @return string|null
	 */
	private function generation(): ?string {
		$generation = $this->transients->get( Hosted_Read_Transient_Keys::GENERATION );

		return is_string( $generation ) && '' !== $generation ? $generation : null;
	}

	/**
	 * Start a new generation. It is random, so a generation that expired or was
	 * evicted never matches the reads of an earlier one.
	 *
	 * @return string
	 */
	private function start_generation(): string {
		$generation = bin2hex( random_bytes( 8 ) );
		$this->transients->edit( Hosted_Read_Transient_Keys::GENERATION, $generation, Hosted_Read_Transient_Keys::GENERATION_TTL );

		return $generation;
	}
}
