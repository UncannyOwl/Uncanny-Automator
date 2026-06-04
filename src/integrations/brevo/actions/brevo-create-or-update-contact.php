<?php

namespace Uncanny_Automator\Integrations\Brevo;

use Uncanny_Automator\Recipe\Log_Properties;

/**
 * Class BREVO_CREATE_OR_UPDATE_CONTACT
 *
 * Replaces the legacy BREVO_ADD_UPDATE_CONTACT action. Uses a transposed
 * repeater backed by Brevo's actual writable attribute list so non-English
 * accounts (where defaults like FIRSTNAME / LASTNAME are localized to
 * VORNAME / NACHNAME / PRENOM / NOM / ...) can map values correctly without
 * the hardcoded English keys silently being dropped on Brevo's side.
 *
 * @package Uncanny_Automator
 *
 * @property Brevo_App_Helpers $helpers
 * @property Brevo_Api_Caller  $api
 */
class BREVO_CREATE_OR_UPDATE_CONTACT extends \Uncanny_Automator\Recipe\App_Action {

	use Log_Properties;

	/**
	 * Prefix key for the action.
	 *
	 * @var string
	 */
	public $prefix = 'BREVO_CREATE_OR_UPDATE_CONTACT';

	/**
	 * Brevo_Contact_Attributes_Helper instance built per-run.
	 *
	 * @var Brevo_Contact_Attributes_Helper|null
	 */
	private $attributes_helper = null;

	/**
	 * Define and register the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'BREVO' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( 'CONTACT_EMAIL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/brevo/' ) );
		$this->set_requires_user( false );
		// translators: Contact Email
		$this->set_sentence( sprintf( esc_attr_x( 'Create or update {{a contact:%1$s}}', 'Brevo', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Create or update {{a contact}}', 'Brevo', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$fields = array();

		$fields[] = array(
			'option_code' => $this->get_action_meta(),
			'label'       => esc_html_x( 'Email', 'Brevo', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
		);

		$fields[] = array(
			'option_code'     => 'CONTACT_ATTRIBUTES',
			'input_type'      => 'repeater',
			'hide_actions'    => true,
			'hide_header'     => true,
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Contact attributes', 'Brevo', 'uncanny-automator' ),
			'required'        => true,
			'layout'          => 'transposed',
			'fields'          => array(),
			'remote_data'     => $this->helpers->remote_data_load_config( 'contact_attributes' ),
			'description'     => esc_html_x( 'Use [DELETE] to clear an attribute on an existing contact, or leave blank to keep its current value.', 'Brevo', 'uncanny-automator' ),
		);

		$fields[] = array(
			'option_code' => 'UPDATE_EXISTING_CONTACT',
			'label'       => esc_html_x( 'Update existing contact', 'Brevo', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'required'    => false,
		);

		$fields[] = array(
			'option_code' => 'DOUBLE_OPT_IN',
			'label'       => esc_html_x( 'Double-opt-in', 'Brevo', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'required'    => false,
		);

		$fields[] = array(
			'option_code'           => 'DOUBLE_OPT_IN_TEMPLATE',
			'label'                 => esc_html_x( 'Double-opt-in template', 'Brevo', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => false,
			'remote_data'           => $this->helpers->remote_data_load_config( 'templates' ),
			'supports_custom_value' => false,
			'description'           => esc_html_x( 'Template is required when using double-opt-in', 'Brevo', 'uncanny-automator' ),
		);

		$fields[] = array(
			'option_code'           => 'DOUBLE_OPT_IN_LIST',
			'label'                 => esc_html_x( 'Double-opt-in list', 'Brevo', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => false,
			'remote_data'           => $this->helpers->remote_data_load_config( 'lists' ),
			'supports_custom_value' => false,
			'description'           => esc_html_x( 'Double-opt-in list is required when using double-opt-in', 'Brevo', 'uncanny-automator' ),
		);

		$fields[] = array(
			'option_code' => 'DOUBLE_OPT_IN_REDIRECT_URL',
			'label'       => esc_html_x( 'Double-opt-in redirect URL', 'Brevo', 'uncanny-automator' ),
			'input_type'  => 'url',
			'required'    => false,
			'description' => esc_html_x( 'Redirect URL is required when using double-opt-in', 'Brevo', 'uncanny-automator' ),
		);

		return $fields;
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool|null
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$email           = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );
		$double_optin    = filter_var( strtolower( (string) $this->get_parsed_meta_value( 'DOUBLE_OPT_IN', false ) ), FILTER_VALIDATE_BOOLEAN );
		$update_existing = filter_var( strtolower( (string) $this->get_parsed_meta_value( 'UPDATE_EXISTING_CONTACT', false ) ), FILTER_VALIDATE_BOOLEAN );

		$attributes = $this->build_attributes_payload( $action_data, $recipe_id, $user_id, $args );

		if ( ! $double_optin ) {
			$this->api->create_contact( $email, $attributes, $update_existing, $action_data );
			return $this->maybe_complete_with_attribute_errors();
		}

		$template_id  = sanitize_text_field( $this->get_parsed_meta_value( 'DOUBLE_OPT_IN_TEMPLATE', false ) );
		$redirect_url = sanitize_text_field( $this->get_parsed_meta_value( 'DOUBLE_OPT_IN_REDIRECT_URL', false ) );
		$list_id      = sanitize_text_field( $this->get_parsed_meta_value( 'DOUBLE_OPT_IN_LIST', false ) );

		if ( ! $template_id || ! $redirect_url || ! $list_id ) {
			$missing = array();
			if ( ! $template_id ) {
				$missing[] = esc_html_x( 'Template', 'Brevo', 'uncanny-automator' );
			}
			if ( ! $redirect_url ) {
				$missing[] = esc_html_x( 'Redirect URL', 'Brevo', 'uncanny-automator' );
			}
			if ( ! $list_id ) {
				$missing[] = esc_html_x( 'List', 'Brevo', 'uncanny-automator' );
			}
			throw new \Exception(
				esc_html(
					sprintf(
						// translators: %s: list of missing required fields
						esc_html_x( '%s are required fields for double-opt-in', 'Brevo', 'uncanny-automator' ),
						implode( ', ', $missing )
					)
				)
			);
		}

		$this->api->create_contact_with_double_optin( $email, $attributes, $template_id, $redirect_url, $list_id, $update_existing, $action_data );
		return $this->maybe_complete_with_attribute_errors();
	}

	/**
	 * Parse the transposed-repeater submission and run it through the helper.
	 *
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param int   $user_id
	 * @param array $args
	 *
	 * @return array
	 */
	private function build_attributes_payload( $action_data, $recipe_id, $user_id, $args ) {

		$raw = isset( $action_data['meta']['CONTACT_ATTRIBUTES'] )
			? Automator()->parse->text( $action_data['meta']['CONTACT_ATTRIBUTES'], $recipe_id, $user_id, $args )
			: '';

		$fields = json_decode( $raw, true );
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return array();
		}

		$this->attributes_helper = new Brevo_Contact_Attributes_Helper( $this->helpers, $this->api );

		return $this->attributes_helper->process_repeater_fields( $fields );
	}

	/**
	 * If the attribute helper accumulated validation errors, surface them as
	 * a "completed with notice" result so the recipe log shows them clearly.
	 *
	 * @return bool|null
	 */
	private function maybe_complete_with_attribute_errors() {
		if ( null !== $this->attributes_helper && $this->attributes_helper->has_errors() ) {
			$this->set_complete_with_notice( true );
			$this->add_log_error( $this->attributes_helper->get_error_message() );
			return null;
		}
		return true;
	}
}
