<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\WhatsApp;

use Uncanny_Automator\Recipe\App_Action;
use Exception;

/**
 * Class WHATSAPP_SEND_MESSAGE_TEMPLATE
 *
 * @package Uncanny_Automator
 * @property WhatsApp_Helpers $helpers
 * @property WhatsApp_Api_Caller $api
 * @property WhatsApp_Webhooks $webhooks
 */
class WHATSAPP_SEND_MESSAGE_TEMPLATE extends App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WHATSAPP' );
		$this->set_action_code( 'WHATSAPP_SEND_MESSAGE_TEMPLATE_CODE' );
		$this->set_action_meta( 'WHATSAPP_SEND_MESSAGE_TEMPLATE_META' );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/whatsapp/' ) );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				// translators: %1$s is the selected message template, %2$s is the recipient number.
				esc_html_x( 'Send a WhatsApp {{message template:%1$s}} to {{a number:%2$s}}', 'WhatsApp', 'uncanny-automator' ),
				$this->get_action_meta(),
				'PHONE_NUMBER:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Send a WhatsApp {{message template}} to {{a number}}', 'WhatsApp', 'uncanny-automator' )
		);
	}

	/**
	 * Load the options for the action.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'           => 'PHONE_NUMBER',
				'label'                 => esc_attr_x( 'To', 'WhatsApp', 'uncanny-automator' ),
				'input_type'            => 'text',
				'placeholder'           => esc_attr_x( '+1 123 345 6789', 'WhatsApp', 'uncanny-automator' ),
				'required'              => true,
				'supports_token'        => true,
				'supports_custom_value' => true,
			),
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_attr_x( 'Message template', 'WhatsApp', 'uncanny-automator' ),
				'description'           => esc_attr_x( 'Select a message template to send.', 'WhatsApp', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_token'        => false,
				'supports_custom_value' => false,
				'options'               => array(),
				'options_show_id'       => false,
				'relevant_tokens'       => array(),
				'remote_data'           => $this->helpers->remote_data_load_config( 'message_templates' ),
			),
			// Header variables repeater.
			array(
				'option_code'     => 'HEADER_VARIABLES',
				'label'           => esc_attr_x( 'Header', 'WhatsApp', 'uncanny-automator' ),
				'description'     => esc_attr_x( 'Provide a value for each header variable in the order they appear in your template (e.g., {{1}}, {{2}}). For media headers, enter a public URL to the image, video, or document.', 'WhatsApp', 'uncanny-automator' ),
				'input_type'      => 'repeater',
				'relevant_tokens' => array(),
				'hide_actions'    => true,
				'fields'          => array(
					array(
						'option_code' => 'HEADER_VARIABLE_FORMAT',
						'input_type'  => 'text',
						'read_only'   => true,
						'required'    => false,
						'label'       => esc_html_x( 'Type', 'WhatsApp', 'uncanny-automator' ),
					),
					array(
						'option_code' => 'HEADER_VARIABLE_VALUE',
						'input_type'  => 'text',
						'required'    => false,
						'label'       => esc_html_x( 'Value', 'WhatsApp', 'uncanny-automator' ),
					),
				),
				'remote_data'     => $this->helpers->remote_data_with_mapping_column(
					$this->helpers->remote_data_parent_config( 'template_repeater_data', array( $this->get_action_meta() ) ),
					'HEADER_VARIABLE_FORMAT'
				),
			),
			// Body variables repeater.
			array(
				'option_code'     => 'BODY_VARIABLES',
				'label'           => esc_attr_x( 'Body variables', 'WhatsApp', 'uncanny-automator' ),
				'description'     => esc_attr_x( 'Provide a value for each body variable in the order they appear in your template (e.g., row 1 = {{1}}, row 2 = {{2}}, row 3 = {{3}}).', 'WhatsApp', 'uncanny-automator' ),
				'input_type'      => 'repeater',
				'relevant_tokens' => array(),
				'hide_actions'    => true,
				'fields'          => array(
					array(
						'option_code' => 'BODY_VARIABLE',
						'input_type'  => 'text',
						'required'    => false,
						'label'       => esc_html_x( 'Value', 'WhatsApp', 'uncanny-automator' ),
					),
				),
				'remote_data'     => $this->helpers->remote_data_parent_config( 'template_repeater_data', array( $this->get_action_meta() ) ),
			),
			// Button variables repeater.
			array(
				'option_code'     => 'BUTTON_VARIABLES',
				'label'           => esc_attr_x( 'Buttons', 'WhatsApp', 'uncanny-automator' ),
				'description'     => esc_attr_x( 'Provide a value for each dynamic URL parameter in your button links (e.g., {{1}} in https://example.com/?code={{1}}).', 'WhatsApp', 'uncanny-automator' ),
				'input_type'      => 'repeater',
				'relevant_tokens' => array(),
				'hide_actions'    => true,
				'fields'          => array(
					array(
						'option_code' => 'BUTTON_FORMAT',
						'input_type'  => 'text',
						'read_only'   => true,
						'required'    => false,
						'label'       => esc_html_x( 'Type', 'WhatsApp', 'uncanny-automator' ),
					),
					array(
						'option_code' => 'BUTTON_VARIABLE',
						'input_type'  => 'text',
						'required'    => false,
						'label'       => esc_html_x( 'Value', 'WhatsApp', 'uncanny-automator' ),
					),
				),
				'remote_data'     => $this->helpers->remote_data_with_mapping_column(
					$this->helpers->remote_data_parent_config( 'template_repeater_data', array( $this->get_action_meta() ) ),
					'BUTTON_FORMAT'
				),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws Exception If the API request fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$to                             = sanitize_text_field( $parsed['PHONE_NUMBER'] ?? '' );
		$template                       = sanitize_textarea_field( $parsed[ $this->get_action_meta() ] ?? '' );
		list( $template_name, $locale ) = explode( '|', $template );

		// Prepare the body.
		$body = array(
			'action'               => 'send_template',
			'to'                   => $to,
			'template'             => $template_name,
			'template_composition' => wp_json_encode( $parsed ),
			'language'             => $locale,
			'phone_id'             => $this->helpers->get_phone_number_id(),
		);

		try {
			$response = $this->api->api_request( $body, $action_data );
			// Set custom data on $this->action_data for use in filters/completion
			$this->action_data['args']['await'] = array(
				'whatsapp_response' => $response,
			);
			wp_schedule_single_event( time() + 60, 'automator_whatsapp_webhook_noresponse_closure', array( $response ) );
			return true;
		} catch ( Exception $e ) {
			throw new Exception( esc_html( $e->getMessage() ) );
		}
	}

	////////////////////////////////////////////////////////////
	// Template repeater row formatting (called from WhatsApp_Helpers).
	////////////////////////////////////////////////////////////

	/**
	 * Format repeater rows for a given template section.
	 *
	 * Called by the remote-data handler in WhatsApp_Helpers.
	 *
	 * @param array  $components The template components from the Meta API.
	 * @param string $field_id   The repeater field ID (HEADER_VARIABLES, BODY_VARIABLES, BUTTON_VARIABLES).
	 * @param array  $values     The full request field values, used by BODY rows to preserve existing entries.
	 *
	 * @return array The formatted repeater rows.
	 */
	public static function format_repeater_rows( $components, $field_id, $values = array() ) {
		switch ( $field_id ) {
			case 'HEADER_VARIABLES':
				return self::format_header_rows( $components );
			case 'BODY_VARIABLES':
				return self::format_body_rows( $components, $values );
			case 'BUTTON_VARIABLES':
				return self::format_button_rows( $components );
			default:
				return array();
		}
	}

	/**
	 * Format header repeater rows.
	 *
	 * - For media headers (IMAGE/VIDEO/DOCUMENT): 1 row for URL input
	 * - For TEXT headers: 1 row per {{n}} token, or 0 if no tokens
	 *
	 * @param array $components The template components.
	 *
	 * @return array The repeater rows.
	 */
	private static function format_header_rows( $components ) {
		$rows = array();

		foreach ( $components as $component ) {
			if ( 'HEADER' !== $component['type'] ) {
				continue;
			}

			$format = $component['format'] ?? 'TEXT';

			// Media headers (IMAGE, VIDEO, DOCUMENT) need 1 row for URL input.
			if ( in_array( strtoupper( $format ), array( 'IMAGE', 'VIDEO', 'DOCUMENT' ), true ) ) {
				$rows[] = array(
					'HEADER_VARIABLE_FORMAT' => $format,
					'HEADER_VARIABLE_VALUE'  => '',
				);
				break;
			}

			// TEXT headers - only add rows if there are {{n}} tokens.
			$text   = $component['text'] ?? '';
			$tokens = self::extract_unique_tokens( $text );

			foreach ( $tokens as $token ) {
				$rows[] = array(
					'HEADER_VARIABLE_FORMAT' => $format,
					'HEADER_VARIABLE_VALUE'  => '',
				);
			}
		}

		return $rows;
	}

	/**
	 * Format body repeater rows.
	 *
	 * 1 row per unique {{n}} token in body text.
	 *
	 * @param array $components The template components.
	 * @param array $values     The full request field values, used to preserve existing BODY_VARIABLES entries.
	 *
	 * @return array The repeater rows.
	 */
	private static function format_body_rows( $components, $values = array() ) {
		$rows = array();

		foreach ( $components as $component ) {
			if ( 'BODY' !== $component['type'] ) {
				continue;
			}

			$text   = $component['text'] ?? '';
			$tokens = self::extract_unique_tokens( $text );

			foreach ( $tokens as $token ) {
				$rows[] = array(
					'BODY_VARIABLE' => '',
				);
			}
		}

		// Merge existing saved values by position for backwards compatibility.
		// This repeater lacks an identifier column, so we preserve values on page load template listener.
		return self::merge_existing_body_values( $rows, $values );
	}

	/**
	 * Merge existing saved body variable values into rows.
	 *
	 * Preserves user-entered values when the cascade fires on page load,
	 * preventing saved data from being wiped out by empty template rows.
	 *
	 * @param array $rows   The template rows with empty values.
	 * @param array $values The full request field values.
	 *
	 * @return array The rows with existing values merged in.
	 */
	private static function merge_existing_body_values( $rows, $values = array() ) {
		$existing_values = $values['BODY_VARIABLES'] ?? array();

		if ( empty( $existing_values ) || ! is_array( $existing_values ) ) {
			return $rows;
		}

		foreach ( $rows as $index => $row ) {
			if ( isset( $existing_values[ $index ]['BODY_VARIABLE'] ) && '' !== $existing_values[ $index ]['BODY_VARIABLE'] ) {
				$rows[ $index ]['BODY_VARIABLE'] = $existing_values[ $index ]['BODY_VARIABLE'];
			}
		}

		return $rows;
	}

	/**
	 * Format button repeater rows.
	 *
	 * 1 row per button that has {{n}} tokens in its URL.
	 *
	 * @param array $components The template components.
	 *
	 * @return array The repeater rows.
	 */
	private static function format_button_rows( $components ) {
		$rows = array();

		foreach ( $components as $component ) {
			if ( 'BUTTONS' !== $component['type'] || empty( $component['buttons'] ) ) {
				continue;
			}

			foreach ( $component['buttons'] as $button ) {
				$url = $button['url'] ?? '';
				preg_match_all( '/\{\{\d+\}\}/', $url, $matches );

				// Only add row if button URL has tokens.
				if ( ! empty( $matches[0] ) ) {
					$rows[] = array(
						'BUTTON_FORMAT'   => $button['type'] ?? 'URL',
						'BUTTON_VARIABLE' => '',
					);
				}
			}
		}

		return $rows;
	}

	/**
	 * Extract unique {{n}} template tokens from text.
	 *
	 * @param string $text The text to extract tokens from.
	 *
	 * @return array Unique tokens (e.g., ['{{1}}', '{{2}}']).
	 */
	private static function extract_unique_tokens( $text ) {
		preg_match_all( '/\{\{\d+\}\}/', $text, $matches );
		return array_unique( $matches[0] ?? array() );
	}
}
