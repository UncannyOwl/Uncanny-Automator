<?php
/** Observe native CSS generation without running its filters a second time. */

declare(strict_types=1);

namespace Uncanny_Automator\App\Events;

final class Wpbakery_Css_Observer {

	private int $post_id;
	private array $observed  = array();
	private array $callbacks = array();

	/** Limit observations to the post being edited. */
	public function __construct( int $post_id ) {
		$this->post_id = $post_id;
	}

	/** Capture the native filtered value; never alter it. */
	public function register(): void {
		foreach ( array( 'default', 'custom' ) as $type ) {
			$callback                 = function ( $css, $post_id ) use ( $type ) {
				if ( $this->post_id === (int) $post_id ) {
					$this->observed[ $type ] = $css;
				}
				return $css;
			};
			$this->callbacks[ $type ] = $callback;
			add_filter( 'vc_base_build_shortcodes_' . $type . '_css', $callback, PHP_INT_MAX, 2 );
		}
	}
	/**
	 * Values.
	 *
	 * @return array
	 */
	public function values(): array {
		return $this->observed;
	}
	/**
	 * Unregister.
	 */
	public function unregister(): void {
		foreach ( $this->callbacks as $type => $callback ) {
			remove_filter( 'vc_base_build_shortcodes_' . $type . '_css', $callback, PHP_INT_MAX );
		}
		$this->callbacks = array();
	}
}
