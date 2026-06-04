<?php

namespace Uncanny_Automator\Integrations\Popup_Maker;

use Uncanny_Automator\Recipe\Trigger;
use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class ANON_PM_FORM_SUBMITTED
 *
 * Fires when anyone (logged in or out) submits an integrated form inside a
 * popup. Hook: `pum_integrated_form_submission`. Anonymous trigger —
 * intentionally not gated on login state, so it runs for both.
 *
 * @property Popup_Maker_Helpers $item_helpers
 *
 * @package Uncanny_Automator\Integrations\Popup_Maker
 */
class ANON_PM_FORM_SUBMITTED extends Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'ANON_PM_FORM_SUBMITTED', 'PM' )
			->trigger_meta( 'PM_POPUP_FORM' )
			->trigger_type( 'anonymous' )
			->hook( 'pum_integrated_form_submission', 10, 1 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_login_required( false );

		// translators: 1: Form provider 2: Popup
		$this->set_sentence( sprintf( esc_html_x( 'A {{form provider:%1$s}} is submitted in {{a popup:%2$s}}', 'Popup Maker', 'uncanny-automator' ), 'PM_FORM_PROVIDER:' . $this->get_trigger_meta(), 'PM_POPUP:' . $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'A {{form provider}} is submitted in {{a popup}}', 'Popup Maker', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'     => 'PM_POPUP',
				'label'           => esc_html_x( 'Popup', 'Popup Maker', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'popups_any' ),
			),
			array(
				'option_code'     => 'PM_FORM_PROVIDER',
				'label'           => esc_html_x( 'Form provider', 'Popup Maker', 'uncanny-automator' ),
				'description'     => esc_html_x( 'Some form plugins submit via AJAX, which prevents Popup Maker from firing this trigger. If the trigger does not run, disable AJAX submission in your form plugin settings (e.g. WPForms or Fluent Forms).', 'Popup Maker', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'form_providers' ),
			),
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Form ID', 'Popup Maker', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => false,
				'supports_tokens' => false,
				'placeholder'     => esc_html_x( 'Leave blank for any form', 'Popup Maker', 'uncanny-automator' ),
				'description'     => esc_html_x( 'Optional provider-native form ID. Leave blank to fire for any form.', 'Popup Maker', 'uncanny-automator' ),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Define trigger tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			array(
				'tokenId'   => 'POPUP_ID',
				'tokenName' => esc_html_x( 'Popup ID', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POPUP_TITLE',
				'tokenName' => esc_html_x( 'Popup title', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POPUP_EDIT_URL',
				'tokenName' => esc_html_x( 'Popup edit URL', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'FORM_PROVIDER',
				'tokenName' => esc_html_x( 'Form provider key', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FORM_PROVIDER_LABEL',
				'tokenName' => esc_html_x( 'Form provider label', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FORM_ID',
				'tokenName' => esc_html_x( 'Form ID', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FORM_INSTANCE_ID',
				'tokenName' => esc_html_x( 'Form instance ID', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FORM_AJAX',
				'tokenName' => esc_html_x( 'Form submitted via AJAX', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'VISITOR_IP',
				'tokenName' => esc_html_x( 'Visitor IP', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'VISITOR_EMAIL',
				'tokenName' => esc_html_x( 'Visitor email', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
		);
	}

	/**
	 * Validate whether the trigger should fire.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The arguments from the WP hook.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $hook_args[0] ) || ! is_array( $hook_args[0] ) ) {
			return false;
		}

		$args = $hook_args[0];

		$selected_popup    = isset( $trigger['meta']['PM_POPUP'] ) ? (int) $trigger['meta']['PM_POPUP'] : 0;
		$selected_provider = isset( $trigger['meta']['PM_FORM_PROVIDER'] ) ? (string) $trigger['meta']['PM_FORM_PROVIDER'] : '-1';
		$selected_form_id  = isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ? trim( (string) $trigger['meta'][ $this->get_trigger_meta() ] ) : '';

		$fired_popup_id = isset( $args['popup_id'] ) ? (int) $args['popup_id'] : 0;
		$fired_provider = isset( $args['form_provider'] ) ? (string) $args['form_provider'] : '';
		$fired_form_id  = isset( $args['form_id'] ) ? (string) $args['form_id'] : '';

		if ( -1 !== $selected_popup && $fired_popup_id !== $selected_popup ) {
			return false;
		}

		if ( '-1' !== $selected_provider && $fired_provider !== $selected_provider ) {
			return false;
		}

		if ( '' !== $selected_form_id && (int) $fired_form_id !== (int) $selected_form_id ) {
			return false;
		}

		// Attribute to the logged-in submitter when present; 0 for anonymous.
		$this->set_user_id( get_current_user_id() );

		return true;
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

		$args = isset( $hook_args[0] ) && is_array( $hook_args[0] ) ? $hook_args[0] : array();

		$popup_id     = isset( $args['popup_id'] ) ? (int) $args['popup_id'] : 0;
		$provider_key = isset( $args['form_provider'] ) ? (string) $args['form_provider'] : '';

		$popup_tokens = $this->item_helpers->get_popup_token_values( $popup_id );

		$visitor_ip = (string) automator_filter_input( 'REMOTE_ADDR', INPUT_SERVER );

		// Only providers that explicitly inject an email via the
		// pum_integrated_form_submission_args filter will populate this.
		$visitor_email = isset( $args['email'] ) ? sanitize_email( (string) $args['email'] ) : '';

		return array(
			$this->get_trigger_meta() => isset( $args['form_id'] ) ? (string) $args['form_id'] : '',
			'PM_POPUP'                => $popup_tokens['POPUP_TITLE'],
			'PM_FORM_PROVIDER'        => $this->item_helpers->get_form_provider_label( $provider_key ),
			'POPUP_ID'                => $popup_tokens['POPUP_ID'],
			'POPUP_TITLE'             => $popup_tokens['POPUP_TITLE'],
			'POPUP_EDIT_URL'          => $popup_tokens['POPUP_EDIT_URL'],
			'FORM_PROVIDER'           => $provider_key,
			'FORM_PROVIDER_LABEL'     => $this->item_helpers->get_form_provider_label( $provider_key ),
			'FORM_ID'                 => isset( $args['form_id'] ) ? (string) $args['form_id'] : '',
			'FORM_INSTANCE_ID'        => isset( $args['form_instance_id'] ) ? (string) $args['form_instance_id'] : '',
			'FORM_AJAX'               => ! empty( $args['ajax'] ) ? 'yes' : 'no',
			'VISITOR_IP'              => $visitor_ip,
			'VISITOR_EMAIL'           => $visitor_email,
		);
	}
}
