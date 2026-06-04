<?php

namespace Uncanny_Automator\Integrations\Charitable;

/**
 * Class CHARITABLE_DONATION_REFUNDED
 *
 * @property \Uncanny_Automator\Integrations\Charitable\Charitable_Helpers $item_helpers
 */
class CHARITABLE_DONATION_REFUNDED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'CHARITABLE_DONATION_REFUNDED', 'CHARITABLE' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'CHARITABLE_CAMPAIGN' )
			->hook( 'charitable_donation_status_charitable-refunded', 20, 2 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_login_required( false );
		$this->set_sentence(
			sprintf(
				/* translators: 1: Campaign placeholder */
				esc_html_x( 'A donation to {{a campaign:%1$s}} is refunded', 'Charitable', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A donation to {{a campaign}} is refunded', 'Charitable', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_html_x( 'Campaign', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'remote_data' => $this->item_helpers->remote_data_load_config( 'campaigns' ),
			),
		);
	}

	/**
	 * Validate Trigger.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		list( $donation ) = $hook_args;

		if ( ! is_a( $donation, 'Charitable_Donation' ) ) {
			return false;
		}

		$selected_campaign = (int) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? -1 );
		if ( -1 !== $selected_campaign ) {
			$campaign = $this->item_helpers->get_donation_campaign( $donation->ID );
			if ( ! $campaign || (int) $campaign->get_campaign_id() !== $selected_campaign ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Define Tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->get_donation_tokens_config() );
	}

	/**
	 * Hydrate Tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $donation )              = $hook_args;
		$tokens                        = $this->item_helpers->hydrate_donation_tokens( $donation->ID );
		$campaign                      = $this->item_helpers->get_donation_campaign( $donation->ID );
		$tokens['CHARITABLE_CAMPAIGN'] = $campaign->post_title;
		return $tokens;
	}
}
