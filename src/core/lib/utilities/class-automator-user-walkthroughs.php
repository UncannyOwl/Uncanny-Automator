<?php

namespace Uncanny_Automator;

use Exception;

/**
 * Class Automator_User_Walkthroughs
 *
 * @package Uncanny_Automator
 */
class Automator_User_Walkthroughs {

	/**
	 * User ID.
	 *
	 * @var int
	 */
	private $user_id = 0;

	/**
	 * User progress.
	 *
	 * @var mixed ( array ) - Null when not set yet.
	 */
	private $user_progress_meta = null;

	/**
	 * User walkthroughs.
	 *
	 * @var mixed ( array ) - Null when not set yet.
	 */
	private $user_walkthroughs = null;

	/**
	 * Default progress array.
	 *
	 * @var array
	 */
	private $default_progress = array(
		'show'      => 0,
		'step'      => '',
		'progress'  => 0,
		'dismissed' => 0,
	);

	/**
	 * User walkthroughs meta key.
	 *
	 * @var string
	 */
	const PROGRESS_KEY = 'automator_walkthrough_progress';

	/**
	 * Automator_User_Walkthroughs constructor.
	 *
	 * @param int $user_id
	 * @throws Exception
	 */
	public function __construct( $user_id = 0 ) {

		if ( empty( $user_id ) || ! is_numeric( $user_id ) ) {
			throw new Exception( __( 'Invalid user ID.', 'uncanny-automator' ) );
		}

		$this->user_id = $user_id;

		$meta = $this->get_user_progress_meta();

		/**
		 * Add action hook for user walkthroughs to register filters.
		 *
		 * @param Automator_User_Walkthroughs $this - Instance of Automator_User_Walkthroughs.
		 * @param int $user_id - User ID.
		 */
		do_action( 'automator_user_walkthroughs_init', $this, $user_id );
	}

	/**
	 * Get / set user progress meta.
	 *
	 * @return array
	 */
	public function get_user_progress_meta() {

		if ( is_null( $this->user_progress_meta ) ) {
			$this->user_progress_meta = get_user_meta( $this->user_id, self::PROGRESS_KEY, true );
			if ( empty( $this->user_progress_meta ) ) {
				$this->user_progress_meta = array();
			}
		}

		return $this->user_progress_meta;
	}

	/**
	 * Get / set user walkthroughs.
	 *
	 * @return array
	 */
	public function get_user_walkthroughs() {

		// Check if walkthroughs are disabled.
		if ( defined( 'AUTOMATOR_DISABLE_WALKTHROUGHS' ) && AUTOMATOR_DISABLE_WALKTHROUGHS ) {
			$this->user_walkthroughs = array();
			return $this->user_walkthroughs;
		}

		// Property not set yet.
		if ( is_null( $this->user_walkthroughs ) ) {
			$walkthroughs            = array();
			$walkthroughs            = apply_filters( 'automator_get_user_walkthroughs', $walkthroughs, $this->user_id, $this );
			$this->user_walkthroughs = empty( $walkthroughs ) || ! is_array( $walkthroughs ) ? array() : $walkthroughs;
		}

		// Parse default values.
		if ( ! empty( $this->user_walkthroughs ) ) {
			foreach ( $this->user_walkthroughs as $id => $walkthrough ) {
				$this->user_walkthroughs[ $id ] = wp_parse_args( $walkthrough, $this->default_progress );
			}
		}

		return $this->user_walkthroughs;
	}

	/**
	 * Get user walkthroughs.
	 *
	 * @param string $id - Walkthrough ID.
	 * @param bool   $defaults - Whether to parse and return default values.
	 *
	 * @return array
	 * @throws WP_Error
	 */
	public function get_progress_by_id( string $id, bool $defaults = true ) {

		$this->validate_walkthrough_id( $id );

		if ( isset( $this->user_progress_meta[ $id ] ) ) {
			if ( $defaults ) {
				$this->user_progress_meta[ $id ] = wp_parse_args( $this->user_progress_meta[ $id ], $this->default_progress );
			}
			return $this->user_progress_meta[ $id ];
		}

		return $defaults ? $this->default_progress : array();
	}

	/**
	 * Set user walkthrough progress.
	 *
	 * @param string $id - Walkthrough ID.
	 * @param array  $update - Progress array.
	 *
	 * @return void
	 */
	public function set_progress_by_id( string $id, array $update ) {

		$this->validate_walkthrough_id( $id );

		if ( empty( $update ) ) {
			$this->remove_user_walkthrough_progress( $id );
			return;
		}

		$existing = $this->get_progress_by_id( $id );
		$progress = wp_parse_args( $update, $existing );
		$progress = apply_filters( 'automator_set_user_walkthrough_progress', $progress, $this->user_id, $id );
		$progress = apply_filters( 'automator_set_user_walkthrough_progress_' . $id, $progress, $this->user_id );

		if ( empty( $progress ) ) {
			$this->remove_user_walkthrough_progress( $id );
			return;
		}

		$this->user_progress_meta[ $id ] = $progress;
		$this->save_progress();
	}

	/**
	 * Remove user walkthrough progress.
	 *
	 * @param string $id
	 *
	 * @return void
	 */
	public function remove_user_walkthrough_progress( string $id ) {

		$this->validate_walkthrough_id( $id );

		if ( isset( $this->user_progress_meta[ $id ] ) ) {
			unset( $this->user_progress_meta[ $id ] );
			$this->save_progress();
		}
	}

	/**
	 * Restart user walkthrough.
	 *
	 * @param string $id
	 *
	 * @return void
	 */
	public function restart_user_walkthrough( string $id ) {

		$this->validate_walkthrough_id( $id );

		$progress         = $this->default_progress;
		$progress['show'] = 1;
		$progress         = apply_filters( 'automator_restart_user_walkthrough', $progress, $this->user_id, $id, $this );
		$progress         = apply_filters( 'automator_restart_user_walkthrough_' . $id, $progress, $this->user_id, $this );

		$this->set_progress_by_id( $id, $progress );

	}

	/**
	 * Save user progress.
	 *
	 * @return void
	 */
	public function save_progress() {
		update_user_meta( $this->user_id, self::PROGRESS_KEY, $this->user_progress_meta );
	}

	/**
	 * Validate walkthrough ID.
	 *
	 * @param string $id
	 *
	 * @return void
	 * @throws Exception
	 */
	private function validate_walkthrough_id( $id ) {
		if ( empty( $id ) || ! is_string( $id ) ) {
			throw new Exception( __( 'Invalid walkthrough ID.', 'uncanny-automator' ) );
		}
	}

}
