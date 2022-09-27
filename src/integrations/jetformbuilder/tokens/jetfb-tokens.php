<?php
namespace Uncanny_Automator;

/**
 * Class Uncanny_Automator\Jet_Fb_Tokens
 *
 * @since 4.5.0
 * @package Uncanny_Automator
 */
class Jetfb_Tokens {

	protected $helper = null;

	protected $triggers = array();

	public function __construct() {

		$this->helper = new Jetfb_Helpers( false );

		$this->triggers = array(
			'JETFB_USER_FORM_SUBMIT',
			'JETFB_USER_FORM_SPECIFIC_SUBMIT',
			'JETFB_EVERYONE_FORM_SUBMIT',
			'JETFB_EVERYONE_FORM_SPECIFIC_SUBMIT',
		);

		foreach ( $this->triggers as $trigger ) {
			add_filter( 'automator_token_renderable_before_set_' . strtolower( $trigger ), array( $this, 'modify_common_tokens' ), 10, 4 );
		}

	}

	/**
	 * Method modify_common_tokens.
	 *
	 * The renderable tokens depends on the selected form from the Trigger.
	 *
	 * We need to modify the token before its displayed.
	 *
	 * @param array $tokens_renderable
	 * @param string $trigger_code
	 * @param array $tokens Deprecated empty array.
	 * @param array $args The Trigger arguments.
	 */
	public function modify_common_tokens( $tokens_renderable, $trigger_code, $tokens, $args ) {

		$form_id = 0;

		// The $trigger_code . '_META' refers to the field that contains the form id.
		if ( ! empty( $args['triggers_meta'][ $trigger_code . '_META' ] ) ) {

			$form_id = absint( $args['triggers_meta'][ $trigger_code . '_META' ] );

		}

		$form_fields = $this->helper->get_form_fields( $form_id );

		if ( ! empty( $form_fields ) ) {

			foreach ( $form_fields as $form_field ) {

				if ( ! empty( $form_field['name'] ) ) {

					$label = ! empty( $form_field['label'] ) ? $form_field['label'] : $form_field['name'];

					$token = array(
						'name' => $label,
					);

					if ( isset( $form_field['field_type'] ) && 'email' === $form_field['field_type'] ) {
						$token['type'] = 'email';
					}

					// Removed colons from the identifier by replacing it with empty string.
					$tokens_renderable[ str_replace( ':', '', $form_field['name'] ) ] = $token;

				}
			}
		}

		return $tokens_renderable;

	}

	/**
	 * The common tokens. A callback method in each trigger via $this->set_tokens()
	 *
	 * @return array[] The list of tokens where array key is the token identifier.
	 */
	public function common_tokens() {

		return array(
			'__form_id'  => array(
				'name' => __( 'Form ID', 'uncanny-automator' ),
			),
			'FORM_TITLE' => array(
				'name' => __( 'Form title', 'uncanny-automator' ),
			),
			'__refer'    => array(
				'name' => __( 'Referer URL', 'uncanny-automator' ),
			),
		);

	}


	/**
	 * Populate the token with actual values.
	 *
	 * @param $args
	 * @param $trigger
	 *
	 * @return array
	 */
	public function hydrate_tokens( $parsed, $args, $trigger ) {

		$form_fields = isset( $args['trigger_args'][0]->action_handler->request_data ) ? $args['trigger_args'][0]->action_handler->request_data : array();

		if ( ! empty( $form_fields ) ) {
			foreach ( $form_fields as $name => $value ) {
				// Remove colons from the identifier by replacing it with empty string.
				$form_values[ str_replace( ':', '', $name ) ] = $this->format( $value );
			}
		}

		// Parse form title token.
		$form_values['FORM_TITLE'] = get_the_title( $args['trigger_args'][0]->form_id );

		return $parsed + $form_values;

	}

	/**
	 * Formatting value before it renders as token value.
	 *
	 * @return string A comma separated value if the provided value is array. Otherwise, the value itself.
	 */
	public function format( $value ) {

		if ( is_array( $value ) ) {

			$items = array();

			foreach ( $value as $value ) {

				// Repeater field support.
				if ( is_array( $value ) ) {
					$arr_value = array_values( $value );
					$items[]   = end( $arr_value );
				} else {
					$items[] = $value;
				}
			}

			return implode( ', ', $items );

		}

		return $value;

	}

}
