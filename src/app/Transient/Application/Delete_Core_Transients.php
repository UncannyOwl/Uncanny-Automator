<?php
/**
 * Delete Automator Free core transients use case.
 *
 * @package Uncanny_Automator
 * @since 7.5.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transient\Application;

use Uncanny_Automator\App\Transient\Ports\Transient_Port;

/**
 * Deletes the specified Automator Free core transients.
 */
class Delete_Core_Transients {

	/**
	 * Transient storage port.
	 *
	 * @var Transient_Port
	 */
	private Transient_Port $transients;

	/**
	 * Automator Free core transient keys.
	 *
	 * @var string[]
	 */
	private array $keys;

	/**
	 * Create the use case.
	 *
	 * @param Transient_Port $transients The transient storage port.
	 * @param string[]       $keys       The transient keys to delete.
	 */
	public function __construct( Transient_Port $transients, array $keys ) {
		$this->transients = $transients;
		$this->keys       = $keys;
	}

	/**
	 * Delete the specified Automator Free core transients.
	 *
	 * @return void
	 */
	public function execute(): void {
		foreach ( $this->keys as $key ) {
			$this->transients->delete( $key );
		}
	}
}
