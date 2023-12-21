<?php

namespace Uncanny_Automator\Integrations\Seedprod;

use Uncanny_Automator\Seedprod_Helpers;

/**
 * Class OPTIN_FORM_SUBMITTED
 *
 * @package Uncanny_Automator
 */
class OPTIN_FORM_SUBMITTED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {

		$this->set_integration( 'SEEDPROD' );
		$this->set_trigger_code( 'SEEDPROD_OPTIN_FORM_SUBMITTED' );
		$this->set_trigger_meta( 'SEEDPROD_OPTIN_FORM_SUBMITTED_META' );
		$this->set_trigger_type( 'anonymous' );

		$this->set_sentence(
			sprintf(
				/* translators: Trigger sentence */
				esc_attr_x(
					'An Optin Form is submitted on {{a page:%1$s}}',
					'SeedProd',
					'uncanny-automator'
				),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			/* translators: Trigger sentence */
			esc_attr_x(
				'An Optin Form is submitted on {{a page}}',
				'SeedProd',
				'uncanny-automator'
			)
		);

		$this->add_action( 'seedprod_add_subscriber', 10, 1 );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'input_type'      => 'select',
				'label'           => _x( 'Page', 'Seedprod', 'uncanny-automator' ),
				'required'        => true,
				'options'         => Seedprod_Helpers::get_landing_pages(),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$selected_landing_page_id = intval( $trigger['meta'][ $this->get_trigger_meta() ] );

		// Any condition.
		if ( -1 === intval( $selected_landing_page_id ) ) {
			return true;
		}

		$trigger_hook_args = $hook_args[0] ?? null;

		if ( empty( $trigger_hook_args ) ) {
			return false;
		}

		$submitted_landing_page_id = $trigger_hook_args['page_id'] ?? 0;

		// Specific landing page condition.
		return intval( $submitted_landing_page_id ) === intval( $selected_landing_page_id );

	}

	/**
	 * Define Tokens.
	 *
	 * @param array $tokens
	 * @param array $trigger - options selected in the current recipe/trigger
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$tokens_from_fields = array();

		$fields = array(
			'fname'      => esc_attr_x( 'First name', 'SeedProd', 'uncanny-automator' ),
			'lname'      => esc_attr_x( 'Last name', 'SeedProd', 'uncanny-automator' ),
			'email'      => esc_attr_x( 'Email', 'SeedProd', 'uncanny-automator' ),
			'ip'         => esc_attr_x( 'IP Address', 'SeedProd', 'uncanny-automator' ),
			'page_id'    => esc_attr_x( 'Page ID', 'SeedProd', 'uncanny-automator' ),
			'page_title' => esc_attr_x( 'Page title', 'SeedProd', 'uncanny-automator' ),
			'page_url'   => esc_attr_x( 'Page URL', 'SeedProd', 'uncanny-automator' ),
			'page_uuid'  => esc_attr_x( 'Page UUID', 'SeedProd', 'uncanny-automator' ),
		);

		foreach ( $fields as $token_id => $token_label ) {

			$tokens_from_fields[] = array(
				'tokenId'   => $token_id,
				'tokenName' => $token_label,
				'tokenType' => 'email' === $token_id ? 'email' : 'text',
			);
		}

		return array_merge( $tokens, $tokens_from_fields );
	}

	/**
	 * Populate the tokens with actual values when a trigger runs.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$trigger_hook_args = $hook_args[0] ?? null;

		$parsed_token_values = wp_parse_args(
			$trigger_hook_args,
			array(
				'fname'   => '',
				'lname'   => '',
				'email'   => '',
				'ip'      => '',
				'page_id' => '',
			)
		);

		$page_id = absint( $parsed_token_values['page_id'] );

		$parsed_token_values['page_title'] = get_the_title( $page_id );
		$parsed_token_values['page_url']   = get_the_permalink( $page_id );

		return $parsed_token_values;

	}

}
