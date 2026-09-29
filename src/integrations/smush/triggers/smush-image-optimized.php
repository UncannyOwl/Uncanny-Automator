<?php

namespace Uncanny_Automator\Integrations\Smush;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Smush_Image_Optimized
 *
 * @package Uncanny_Automator
 *
 * @property Smush_Helpers $item_helpers
 */
class Smush_Image_Optimized extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * `wp_smush_image_optimised` is fired by Smush_Optimization::optimize()
	 * (core/smush/class-smush-optimization.php:205) and is the only optimize
	 * hook that covers single, automatic and bulk smushes alike. The sibling
	 * `image_smushed` hook fires from the bulk process only.
	 */
	public static function definition() {
		return self::new_definition( 'SMUSH_IMAGE_OPTIMIZED', 'SMUSH' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'SMUSH_IMAGE' )
			->hook( 'wp_smush_image_optimised', 10, 3 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_uses_api( false );
		$this->set_sentence( esc_html_x( 'An image is optimized', 'Smush', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'An image is optimized', 'Smush', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * Smush fires this hook for every optimized image, so there is nothing to
	 * select — the trigger always fires and exposes the image as tokens.
	 *
	 * @return array[]
	 */
	public function options() {
		return array();
	}

	/**
	 * Define available tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			$this->item_helpers->get_image_token_definitions(),
			array(
				array(
					'tokenId'   => 'SIZE_BEFORE',
					'tokenName' => esc_html_x( 'Size before optimization (bytes)', 'Smush', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => 'SIZE_AFTER',
					'tokenName' => esc_html_x( 'Size after optimization (bytes)', 'Smush', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => 'BYTES_SAVED',
					'tokenName' => esc_html_x( 'Bytes saved', 'Smush', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => 'PERCENT_SAVED',
					'tokenName' => esc_html_x( 'Percent saved', 'Smush', 'uncanny-automator' ),
					'tokenType' => 'float',
				),
				array(
					'tokenId'   => 'IS_LOSSY',
					'tokenName' => esc_html_x( 'Lossy compression used', 'Smush', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => 'SIZES_OPTIMIZED',
					'tokenName' => esc_html_x( 'Number of image sizes optimized', 'Smush', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
			)
		);
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( empty( $hook_args ) ) {
			return false;
		}

		$attachment_id = absint( $hook_args[0] ?? 0 );

		return 0 !== $attachment_id;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$attachment_id = absint( $hook_args[0] ?? 0 );
		$smush_meta    = $hook_args[1] ?? array();

		$stats = $this->item_helpers->normalize_stats(
			is_array( $smush_meta ) && isset( $smush_meta['stats'] ) ? $smush_meta['stats'] : array()
		);

		return array_merge(
			$this->item_helpers->hydrate_image_tokens( $attachment_id ),
			array(
				'SIZE_BEFORE'     => $stats['size_before'],
				'SIZE_AFTER'      => $stats['size_after'],
				'BYTES_SAVED'     => $stats['bytes'],
				'PERCENT_SAVED'   => $stats['percent'],
				'IS_LOSSY'        => $stats['is_lossy'],
				'SIZES_OPTIMIZED' => $this->item_helpers->count_optimized_sizes( $smush_meta ),
			)
		);
	}
}
