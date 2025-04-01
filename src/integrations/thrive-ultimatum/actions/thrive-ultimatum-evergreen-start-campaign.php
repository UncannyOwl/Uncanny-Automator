<?php
namespace Uncanny_Automator\Integrations\Thrive_Ultimatum;

use Uncanny_Automator\Recipe\Action;
use WP_Post;

/**
 * Class THRIVE_ULTIMATUM_EVERGREEN_START_CAMPAIGN
 *
 * @package Uncanny_Automator
 */
class THRIVE_ULTIMATUM_EVERGREEN_START_CAMPAIGN extends Action {

	/**
	 * Constant ACTION_CODE.
	 *
	 * @var string
	 */
	const ACTION_CODE = 'THRIVE_ULTIMATUM_EVERGREEN_START_CAMPAIGN';

	/**
	 * Constant ACTION_META.
	 *
	 * @var string
	 */
	const ACTION_META = 'THRIVE_ULTIMATUM_EVERGREEN_START_CAMPAIGN_META';

	/**
	 * @var Thrive_Ultimatum_Helpers
	 */
	protected $helpers;

	/**
	 * Setup action
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->helpers = new Thrive_Ultimatum_Helpers( false );

		$this->set_integration( 'THRIVE_ULTIMATUM' );
		$this->set_action_code( self::ACTION_CODE );
		$this->set_action_meta( self::ACTION_META );
		$this->set_requires_user( true );
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
			/* translators: %1$s: Campaign */
				esc_html_x( 'Start {{a campaign:%1$s}} for the user', 'Thrive Ultimatum', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Start {{a campaign}} for the user', 'Thrive Ultimatum', 'uncanny-automator' ) );

		$this->set_background_processing( false );
	}

	/**
	 * Define the Action's options
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'required'        => true,
				'label'           => esc_html_x( 'Evergreen campaign', 'Thrive Ultimatum', 'uncanny-automator' ),
				'input_type'      => 'select',
				'options'         => $this->get_evergreen_campaigns(),
				'relevant_tokens' => $this->helpers->get_all_campaign_tokens(),
			),

		);
	}

	/**
	 * Get available evergreen campaigns for dropdown options
	 *
	 * @return array
	 */
	private function get_evergreen_campaigns() {
		if ( ! function_exists( 'tve_ult_get_campaigns' ) ) {
			return array();
		}

		$campaigns = tve_ult_get_campaigns();
		$options   = array();

		if ( ! empty( $campaigns ) ) {

			foreach ( $campaigns as $campaign ) {

				if ( is_object( $campaign ) && $campaign instanceof WP_Post ) {
					// Get campaign type from post meta

					$type = get_post_meta( $campaign->ID, 'tve_ult_campaign_type', true );

					// Only include evergreen campaigns
					if ( 'evergreen' === $type ) {
						$options[] = array(
							'value' => $campaign->ID,
							'text'  => $campaign->post_title,
						);
					}
				} elseif ( is_array( $campaign ) && isset( $campaign['id'] ) && isset( $campaign['name'] ) ) {
					// Handle array format
					$type = isset( $campaign['type'] ) ? $campaign['type'] : '';
					if ( 'evergreen' === $type ) {
						$options[] = array(
							'value' => $campaign['id'],
							'text'  => $campaign['name'],
						);
					}
				}
			}
		}

		return $options;
	}

	/**
	 * Process the action to start a Thrive Ultimatum evergreen campaign.
	 *
	 * @param int $user_id The user ID.
	 * @param array $action_data The action configuration data.
	 * @param int $recipe_id The recipe ID.
	 * @param array $args Additional args.
	 * @param array $parsed Parsed tokens and values.
	 *
	 * @return bool|null True on success, false on failure, null if skipped.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$campaign_id = isset( $parsed[ $this->get_action_meta() ] ) ? $parsed[ $this->get_action_meta() ] : 0;

		if ( empty( $campaign_id ) ) {
			$this->add_log_error( esc_html_x( 'Campaign ID is missing.', 'Thrive Ultimatum', 'uncanny-automator' ) );

			return false;
		}

		if ( ! function_exists( 'tu_start_campaign' ) ) {
			$this->add_log_error( esc_html_x( 'Thrive Ultimatum functions are not available.', 'Thrive Ultimatum', 'uncanny-automator' ) );

			return false;
		}

		try {
			$user       = get_userdata( $user_id );
			$user_email = $user->user_email;

			// Start campaign
			$result = tu_start_campaign( $campaign_id, $user_email );

			if ( is_wp_error( $result ) ) {
				$this->add_log_error( $result->get_error_message() );

				return false;
			}

			return true;

		} catch ( Exception $e ) {
			$this->add_log_error( $e->getMessage() );

			return false;
		}
	}
}
