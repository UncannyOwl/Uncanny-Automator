<?php

namespace Uncanny_Automator\Integrations\Smush;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Smush_Optimize_Image
 *
 * @package Uncanny_Automator
 *
 * @property Smush_Helpers $item_helpers
 */
class Smush_Optimize_Image extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'SMUSH' );
		$this->set_action_code( 'SMUSH_OPTIMIZE_IMAGE' );
		$this->set_action_meta( 'SMUSH_IMAGE' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		// Compression runs against the external WPMU DEV Smush API — keep it off the request thread.
		$this->set_background_processing( true );
		$this->set_sentence(
			sprintf(
				/* translators: 1: Image to optimize */
				esc_html_x( 'Optimize {{an image:%1$s}}', 'Smush', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Optimize {{an image}}', 'Smush', 'uncanny-automator' ) );
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Image', 'Smush', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_search_config( 'images_strict' ),
			),
		);
	}

	/**
	 * Define the action output tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array_merge(
			$this->item_helpers->get_image_action_token_definitions(),
			array(
				'SIZE_BEFORE'   => array(
					'name' => esc_html_x( 'Size before optimization (bytes)', 'Smush', 'uncanny-automator' ),
					'type' => 'int',
				),
				'SIZE_AFTER'    => array(
					'name' => esc_html_x( 'Size after optimization (bytes)', 'Smush', 'uncanny-automator' ),
					'type' => 'int',
				),
				'BYTES_SAVED'   => array(
					'name' => esc_html_x( 'Bytes saved', 'Smush', 'uncanny-automator' ),
					'type' => 'int',
				),
				'PERCENT_SAVED' => array(
					'name' => esc_html_x( 'Percent saved', 'Smush', 'uncanny-automator' ),
					'type' => 'float',
				),
			)
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$attachment_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );

		if ( ! $this->item_helpers->is_image_attachment( $attachment_id ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: 1: Attachment ID */
					esc_html_x( 'The selected attachment (%d) does not exist or is not an image.', 'Smush', 'uncanny-automator' ),
					$attachment_id
				)
			);

			return false;
		}

		if ( ! class_exists( '\Smush\Core\Optimizer' ) ) {
			$this->add_log_error( esc_html_x( 'The Smush optimizer class is not available.', 'Smush', 'uncanny-automator' ) );

			return false;
		}

		$optimizer = \Smush\Core\Optimizer::get_instance();

		// Verified against Smush 4.2.0 — `Optimizer::optimize()` returns false when:
		//   - another optimization is already running for the site
		//     (`in_progress`, `core/class-optimizer.php:85`);
		//   - a third party vetoed it via `wp_smush_before_smush_attempt`;
		//   - the item is ignored or animated, or already carries errors
		//     (`Media_Item::is_skipped()`, `core/media/class-media-item.php:521`);
		//   - an optimization actually ran and failed — unwritable directory,
		//     over the free plan's 5MB ceiling, or an API error
		//     (`Smusher::validate_file()`, `core/smush/class-smusher.php:257`).
		//
		// It does NOT return false for an already-optimized image: nothing reports
		// `should_optimize()`, so `run_optimizations()` finds nothing to do and
		// reports success (`core/media/class-media-item-optimizer.php:349`). Such a
		// run returns true and re-hydrates the stats already on the attachment,
		// which is also what happens after Smush auto-smushes on upload.
		if ( ! $optimizer->optimize( $attachment_id ) ) {
			$this->add_log_error(
				$this->item_helpers->get_error_message(
					$optimizer->get_errors(),
					esc_html_x( 'Smush did not optimize the image. It may be ignored or animated, too large for the current Smush plan, or another optimization may already be running.', 'Smush', 'uncanny-automator' )
				)
			);

			return false;
		}

		$stats = $this->item_helpers->get_smush_stats( $attachment_id );

		$this->hydrate_tokens(
			array_merge(
				$this->item_helpers->hydrate_image_tokens( $attachment_id, false ),
				array(
					'SIZE_BEFORE'   => $stats['size_before'],
					'SIZE_AFTER'    => $stats['size_after'],
					'BYTES_SAVED'   => $stats['bytes'],
					'PERCENT_SAVED' => $stats['percent'],
				)
			)
		);

		return true;
	}
}
