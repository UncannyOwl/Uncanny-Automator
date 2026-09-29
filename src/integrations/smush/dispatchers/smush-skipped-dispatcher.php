<?php

namespace Uncanny_Automator\Integrations\Smush;

use WP_Error;

/**
 * Class Smush_Skipped_Dispatcher
 *
 * Normalizes every way Smush can decline to optimize an image into one internal
 * event, `automator_smush_optimization_skipped`.
 *
 * `wp_smush_after_smush_file` alone is not enough. `Media_Item_Optimizer::optimize()`
 * (`core/media/class-media-item-optimizer.php:196-262`) returns early — before that
 * hook is ever reached — in two cases:
 *
 *   1. a third party vetoed the attempt through `wp_smush_before_smush_attempt`;
 *   2. `$media_item->has_errors() || $media_item->is_skipped()`.
 *
 * Case 2 covers most of what a site owner would call "skipped": not an image,
 * unsupported mime type, missing file or metadata, file not found, over the plan's
 * size limit, excluded by the `wp_smush_image` / `wp_smush_is_smushable` filters
 * (`Media_Item::prepare_errors()`, `core/media/class-media-item.php:798`), plus
 * ignored and animated images (`Media_Item::is_skipped()`, `:521`).
 *
 * Only failures that happen *after* the pre-checks pass reach
 * `wp_smush_after_smush_file` — e.g. `converted_image_larger`, API errors, an
 * unwritable directory.
 *
 * So this dispatcher listens on both ends:
 *
 *   - `wp_smush_before_smush_attempt` at PHP_INT_MAX, which is after Smush's own
 *     listeners (animated + transparent status, both at priority 10) have settled
 *     the flags `is_skipped()` reads, and after any third-party veto has been added
 *     to the shared WP_Error;
 *   - `wp_smush_after_smush_file` at 10, for the post-run failures.
 *
 * The two are mutually exclusive by construction — when the pre-check path emits,
 * `optimize()` returns before `wp_smush_after_smush_file` can fire — so no
 * de-duplication is needed and a genuine second attempt on the same image within
 * one request is still reported.
 *
 * @package Uncanny_Automator\Integrations\Smush
 */
class Smush_Skipped_Dispatcher {

	/**
	 * Skipped before Smush attempted anything.
	 *
	 * @var string
	 */
	const PHASE_PRE_CHECK = 'pre_check';

	/**
	 * Attempted, but the optimization itself failed.
	 *
	 * @var string
	 */
	const PHASE_OPTIMIZATION = 'optimization';

	/**
	 * Guard against double registration.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Register the listeners.
	 *
	 * @return void
	 */
	public static function boot() {

		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		// PHP_INT_MAX so Smush's own priority-10 listeners have already updated
		// the animated/transparent flags that is_skipped() reads, and so any
		// third-party veto is already in $third_party_errors.
		add_action( 'wp_smush_before_smush_attempt', array( __CLASS__, 'maybe_dispatch_pre_check' ), PHP_INT_MAX, 3 );
		add_action( 'wp_smush_after_smush_file', array( __CLASS__, 'maybe_dispatch_optimization' ), 10, 3 );
	}

	/**
	 * Report a skip decided before Smush attempted the file.
	 *
	 * @param int      $attachment_id      The attachment.
	 * @param array    $wp_metadata        The attachment metadata (unused).
	 * @param WP_Error $third_party_errors The veto bag other plugins write into.
	 *
	 * @return void
	 */
	public static function maybe_dispatch_pre_check( $attachment_id, $wp_metadata = array(), $third_party_errors = null ) {

		unset( $wp_metadata );

		$attachment_id = absint( $attachment_id );

		if ( 0 === $attachment_id ) {
			return;
		}

		// A third party stopped the attempt — Smush returns false immediately.
		if ( $third_party_errors instanceof WP_Error && $third_party_errors->has_errors() ) {
			self::dispatch( $attachment_id, $third_party_errors, self::PHASE_PRE_CHECK );
			return;
		}

		$errors = self::media_item_errors( $attachment_id );

		if ( null === $errors ) {
			return;
		}

		self::dispatch( $attachment_id, $errors, self::PHASE_PRE_CHECK );
	}

	/**
	 * Report a failure that happened after the pre-checks passed.
	 *
	 * The third argument is `array()` on success and the optimizer's WP_Error
	 * when the run failed.
	 *
	 * @param int      $attachment_id The attachment.
	 * @param array    $wp_metadata   The attachment metadata (unused).
	 * @param mixed    $errors        Empty array, or a WP_Error.
	 *
	 * @return void
	 */
	public static function maybe_dispatch_optimization( $attachment_id, $wp_metadata = array(), $errors = array() ) {

		unset( $wp_metadata );

		$attachment_id = absint( $attachment_id );

		if ( 0 === $attachment_id ) {
			return;
		}

		if ( ! $errors instanceof WP_Error || ! $errors->has_errors() ) {
			return;
		}

		self::dispatch( $attachment_id, $errors, self::PHASE_OPTIMIZATION );
	}

	/**
	 * The reasons Smush would refuse this attachment, or null when it would proceed.
	 *
	 * Reads through Smush's own Media_Item_Cache so the item is not rebuilt — the
	 * optimizer is about to use the same cached instance.
	 *
	 * @param int $attachment_id The attachment.
	 *
	 * @return WP_Error|null
	 */
	private static function media_item_errors( $attachment_id ) {

		if ( ! class_exists( '\Smush\Core\Media\Media_Item_Cache' ) ) {
			return null;
		}

		$media_item = \Smush\Core\Media\Media_Item_Cache::get_instance()->get( $attachment_id );

		if ( ! is_object( $media_item ) ) {
			return null;
		}

		if ( $media_item->has_errors() ) {
			return $media_item->get_errors();
		}

		if ( ! $media_item->is_skipped() ) {
			return null;
		}

		// is_skipped() is ignored-or-animated and carries no WP_Error of its own,
		// so give the trigger a code and message in the same shape as every other
		// skip reason.
		return new WP_Error(
			'skipped',
			esc_html_x( 'Skipped. The image is ignored or animated.', 'Smush', 'uncanny-automator' )
		);
	}

	/**
	 * Emit the normalized event, `automator_smush_optimization_skipped`.
	 *
	 * Payload: ( int $attachment_id, array $error, string $phase ), where
	 * `$error` is `array( 'code' => string, 'message' => string )`.
	 *
	 * The reason is flattened to scalars deliberately. `$hook_args` is stored
	 * verbatim as `trigger_args` on the run and is handed to
	 * `automator_before_trigger_completed`, `automator_trigger_should_complete`,
	 * `automator_loopable_token_hydrate` and the replay snapshot. Every other
	 * trigger in the plugin puts scalars there; a live `WP_Error` instance is
	 * the one object in the set, and nothing downstream is built to carry it.
	 *
	 * @param int      $attachment_id The attachment.
	 * @param WP_Error $errors        Why Smush declined.
	 * @param string   $phase         Which of the two paths reported it.
	 *
	 * @return void
	 */
	private static function dispatch( $attachment_id, WP_Error $errors, $phase ) {

		$error = array(
			'code'    => (string) $errors->get_error_code(),
			'message' => (string) $errors->get_error_message(),
		);

		do_action( 'automator_smush_optimization_skipped', $attachment_id, $error, $phase );
	}

	/**
	 * Test seam — unregister and allow boot() to run again.
	 *
	 * @return void
	 */
	public static function reset_boot() {
		remove_action( 'wp_smush_before_smush_attempt', array( __CLASS__, 'maybe_dispatch_pre_check' ), PHP_INT_MAX );
		remove_action( 'wp_smush_after_smush_file', array( __CLASS__, 'maybe_dispatch_optimization' ), 10 );
		self::$booted = false;
	}
}
