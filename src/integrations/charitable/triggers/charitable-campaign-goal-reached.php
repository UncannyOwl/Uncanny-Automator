<?php

namespace Uncanny_Automator\Integrations\Charitable;

/**
 * Class CHARITABLE_CAMPAIGN_GOAL_REACHED
 *
 * Fires the first time a campaign's donated amount meets/exceeds its goal.
 * Re-arms automatically if the campaign later drops below goal (e.g. refund / goal raised).
 *
 * @property \Uncanny_Automator\Integrations\Charitable\Charitable_Helpers $item_helpers
 */
class CHARITABLE_CAMPAIGN_GOAL_REACHED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'CHARITABLE_CAMPAIGN_GOAL_REACHED', 'CHARITABLE' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'CHARITABLE_CAMPAIGN' )
			->hook( 'charitable_after_save_donation', 20, 1 );
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
				esc_html_x( '{{A campaign:%1$s}} reaches its goal', 'Charitable', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( '{{A campaign}} reaches its goal', 'Charitable', 'uncanny-automator' ) );
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
	 * Validate trigger.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$donation_id = isset( $hook_args[0] ) ? (int) $hook_args[0] : 0;
		if ( empty( $donation_id ) ) {
			return false;
		}

		$campaign = $this->item_helpers->get_donation_campaign( $donation_id );
		if ( ! $campaign ) {
			return false;
		}

		$campaign_id  = (int) $campaign->get_campaign_id();
		$selected_raw = (string) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? '-1' );

		// Compare the "any" sentinel as a string before any int cast so an unexpected
		// stored value (label, empty, etc.) does not collapse to 0 and fail the match.
		if ( '-1' !== $selected_raw && $campaign_id !== (int) $selected_raw ) {
			return false;
		}

		$goal = (float) $campaign->get_goal();
		if ( $goal <= 0 ) {
			return false;
		}

		$donated = (float) $campaign->get_donated_amount();

		// Re-arm: if we previously announced but the campaign has since dropped below goal, clear the flag.
		if ( $donated < $goal ) {
			delete_post_meta( $campaign_id, '_uo_charitable_goal_announced' );
			return false;
		}

		// Goal met. Fire only once per crossing.
		if ( 'yes' === get_post_meta( $campaign_id, '_uo_charitable_goal_announced', true ) ) {
			return false;
		}
		update_post_meta( $campaign_id, '_uo_charitable_goal_announced', 'yes' );

		return true;
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->get_campaign_tokens_config() );
	}

	/**
	 * Hydrate tokens. Re-derives campaign from $hook_args to avoid instance state leak.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$donation_id = isset( $hook_args[0] ) ? (int) $hook_args[0] : 0;
		$campaign    = $this->item_helpers->get_donation_campaign( $donation_id );

		if ( ! $campaign ) {
			return array();
		}

		$tokens                        = $this->item_helpers->hydrate_campaign_tokens( $campaign->get_campaign_id() );
		$tokens['CHARITABLE_CAMPAIGN'] = $campaign->post_title;
		return $tokens;
	}
}
