<?php
namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads\Triggers;

use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Helpers\Facebook_Lead_Ads_Helpers;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities\Client;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities\Credentials_Manager;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities\Tokens_Handler;

/**
 * Class Lead_Created
 *
 * Represents a trigger for when a new lead is created in Facebook Lead Ads.
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads\Triggers
 */
class Lead_Created extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Sets up the trigger properties.
	 *
	 * Defines integration, trigger code, trigger metadata, and type. Also configures
	 * sentences for describing the trigger.
	 *
	 * @link https://developer.automatorplugin.com/adding-a-custom-trigger-to-uncanny-automator/
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_integration( 'FACEBOOK_LEAD_ADS' );
		$this->set_trigger_code( 'FB_LEAD_ADS_CODE' );
		$this->set_trigger_meta( 'FB_LEAD_ADS_META' );
		$this->set_trigger_type( 'anonymous' );
		$this->set_uses_api( true );

		$this->set_sentence(
			sprintf(
				/* translators: Trigger sentence */
				esc_attr_x( 'A new lead from {{Page:%1$s}} is created', 'Facebook Lead Ads', 'uncanny-automator' ),
				$this->get_trigger_meta() // Returns string 'REDIRECT_TYPE'.
			)
		);

		$this->set_readable_sentence(
			esc_attr_x( '{{A new lead}} is created', 'Facebook Lead Ads', 'uncanny-automator' )
		);

		$this->add_action( 'automator_facebook_lead_ads_rest_api_handle_request_after', 10, 1 );
	}

	/**
	 * Returns options for configuring the trigger.
	 *
	 * Provides options for selecting pages associated with Facebook Lead Ads.
	 *
	 * @return array[] An array of configuration options.
	 */
	public function options() {

		$pages = array(
			'option_code'     => $this->get_trigger_meta(),
			'input_type'      => 'select',
			'label'           => esc_html_x( 'Page', 'Facebook Lead Ads', 'uncanny-automator' ),
			'required'        => true,
			'options'         => Facebook_Lead_Ads_Helpers::get_pages(),
			'relevant_tokens' => array(),
		);

		$forms = array(
			'option_code'     => 'FORMS',
			'input_type'      => 'select',
			'label'           => esc_html_x( 'Form', 'Facebook Lead Ads', 'uncanny-automator' ),
			'description'     => esc_html_x( 'Updating a form in Facebook generates a new form ID, requiring you to reselect it from the dropdown.', 'Facebook Lead Ads', 'uncanny-automator' ),
			'required'        => true,
			'ajax'            => array(
				'endpoint'      => 'automator_facebook_lead_ads_forms_handler',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $this->get_trigger_meta() ),
			),
			'relevant_tokens' => array(),
		);

		return array(
			$pages,
			$forms,
		);
	}

	/**
	 * Defines the tokens for this trigger.
	 *
	 * @param  array $tokens
	 * @param  array $trigger - options selected in the current recipe/trigger
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$tokens[] = array(
			'tokenId'   => 'RAW_PAYLOAD',
			'tokenName' => esc_html_x( 'Raw payload (JSON)', 'Facebook Lead Ads', 'uncanny-automator' ),
		);

		$tokens[] = array(
			'tokenId'   => 'CREATED_TIME',
			'tokenName' => esc_html_x( 'Created time (timestamp)', 'Facebook Lead Ads', 'uncanny-automator' ),
		);

		$tokens[] = array(
			'tokenId'   => 'FORM_ID',
			'tokenName' => esc_html_x( 'Form ID', 'Facebook Lead Ads', 'uncanny-automator' ),
		);

		$tokens[] = array(
			'tokenId'   => 'LEADGEN_ID',
			'tokenName' => esc_html_x( 'Lead Gen ID', 'Facebook Lead Ads', 'uncanny-automator' ),
		);

		$tokens[] = array(
			'tokenId'   => 'PAGE_ID',
			'tokenName' => esc_html_x( 'Page ID', 'Facebook Lead Ads', 'uncanny-automator' ),
		);

		$form_fields = (array) maybe_unserialize( $trigger['meta']['meta_form_fields'] );

		foreach ( $form_fields as $fields ) {
			$tokens[] = array(
				'tokenId'   => $fields['key'] ?? '',
				'tokenName' => $fields['label'] ?? '',
			);
		}

		return $tokens;
	}

	/**
	 * Populate the tokens with actual values when a trigger runs.
	 *
	 * @param mixed[] $trigger The Trigger args.
	 * @param mixed[] $hook_args The accepted action hook arguments.
	 *
	 * @return mixed[]
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$payload = $hook_args[0]['data']['data'] ?? array();

		$json       = wp_json_encode( (array) $payload, true );
		$leadgen_id = $payload['leadgen_id'] ?? '';

		$token_values = array(
			'RAW_PAYLOAD'  => $json,
			'CREATED_TIME' => $payload['created_time'] ?? '',
			'PAGE_ID'      => $payload['page_id'] ?? '',
			'LEADGEN_ID'   => $leadgen_id,
			'FORM_ID'      => $payload['form_id'] ?? '',
		);

		$client = new Client();

		$page_id = absint( $trigger['meta'][ $this->trigger_meta ] ?? 0 );

		$credentials       = new Credentials_Manager();
		$page_access_token = $credentials->get_page_access_token( $page_id );
		$the_lead          = $client->get_lead( $page_id, $leadgen_id, $page_access_token );

		if ( is_wp_error( $the_lead ) ) {
			return $token_values;
		}

		$field_reference   = maybe_unserialize( $trigger['meta'][ Tokens_Handler::POST_META_KEY ] ?? '' );
		$lead_field_values = Tokens_Handler::map_lead_data( (array) $the_lead, (array) $field_reference );

		if ( ! empty( $lead_field_values ) ) {
			return array_merge( $token_values, $lead_field_values );
		}

		return $token_values;
	}

	/**
	 * Validates the trigger.
	 *
	 * Determines whether the trigger should fire based on supplied arguments.
	 *
	 * @param array{'ID': int, 'post_status': string, 'meta': mixed[]} $trigger   The arguments supplied by the trigger.
	 * @param mixed[]                                                $hook_args The action hook arguments.
	 *
	 * @return bool True if the trigger is valid, false otherwise.
	 */
	public function validate( $trigger, $hook_args ) {

		$entry = $hook_args[0]['data']['data'] ?? array();

		if ( empty( $entry ) ) {
			return false;
		}

		$selected_form_id = absint( $trigger['meta']['FORMS'] ?? 0 );
		$selected_page_id = absint( $trigger['meta'][ $this->get_trigger_meta() ] ?? 0 );

		if ( empty( $selected_form_id ) || empty( $selected_page_id ) ) {
			return false;
		}

		$entry_form_id = absint( $entry['form_id'] ?? 0 );
		$entry_page_id = absint( $entry['page_id'] ?? 0 );

		return $entry_form_id === $selected_form_id
			&& $entry_page_id === $selected_page_id;
	}
}
