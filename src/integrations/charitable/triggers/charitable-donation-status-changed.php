<?php

namespace Uncanny_Automator\Integrations\Charitable;

/**
 * Class CHARITABLE_DONATION_STATUS_CHANGED
 *
 * @property \Uncanny_Automator\Integrations\Charitable\Charitable_Helpers $item_helpers
 */
class CHARITABLE_DONATION_STATUS_CHANGED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'CHARITABLE_DONATION_STATUS_CHANGED', 'CHARITABLE' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'CHARITABLE_DONATION_STATUS' )
			->hook( 'charitable_donation_status_changed', 20, 3 );
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
				/* translators: 1: Status placeholder */
				esc_html_x( "A donation's status is changed to {{a status:%1\$s}}", 'Charitable', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( "A donation's status is changed to {{a status}}", 'Charitable', 'uncanny-automator' ) );
	}

	/**
	 * Trigger Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_html_x( 'Status', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => $this->item_helpers->get_donation_status_options(),
			),
			array(
				'option_code' => 'CHARITABLE_CAMPAIGN',
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

		list( $donation, $new_status ) = array_pad( $hook_args, 2, null );

		if ( ! is_a( $donation, 'Charitable_Donation' ) ) {
			return false;
		}

		$selected_status   = $trigger['meta'][ $this->get_trigger_meta() ] ?? '-1';
		$selected_campaign = (int) ( $trigger['meta']['CHARITABLE_CAMPAIGN'] ?? -1 );

		if ( '-1' !== $selected_status && $selected_status !== $new_status ) {
			return false;
		}

		if ( -1 !== $selected_campaign ) {
			$campaign = $this->item_helpers->get_donation_campaign( $donation->ID );
			if ( ! $campaign || (int) $campaign->get_campaign_id() !== $selected_campaign ) {
				return false;
			}
		}

		// Set user from linked donor if available.
		$donor_id = (int) $donation->get_donor_id();
		if ( $donor_id > 0 && class_exists( 'Charitable_Donor' ) ) {
			$donor = new \Charitable_Donor( $donor_id );
			$user  = $donor ? $donor->get_user() : null;
			if ( $user && isset( $user->ID ) && $user->ID > 0 ) {
				$this->set_user_id( (int) $user->ID );
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
		list( $donation )                     = $hook_args;
		$tokens                               = $this->item_helpers->hydrate_donation_tokens( $donation->ID );
		$campaign                             = $this->item_helpers->get_donation_campaign( $donation->ID );
		$tokens['CHARITABLE_DONATION_STATUS'] = $donation->get_status_label();
		$tokens['CHARITABLE_CAMPAIGN']        = $campaign->post_title;
		return $tokens;
	}
}
