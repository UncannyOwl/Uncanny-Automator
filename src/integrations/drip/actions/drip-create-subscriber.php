<?php

namespace Uncanny_Automator;

/**
 * Class DRIP_CREATE_SUBSCRIBER
 *
 * @package Uncanny_Automator
 */
class DRIP_CREATE_SUBSCRIBER {

	use Recipe\Actions;
	use Recipe\Action_Tokens;

	/**
	 * @var Drip_Functions
	 */
	private $functions;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {

		$this->functions = new Drip_Functions();

		$this->setup_action();
	}


	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function setup_action() {

		$this->set_integration( 'DRIP' );
		$this->set_action_code( 'CREATE_SUBSCRIBER' );
		$this->set_action_meta( 'EMAIL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/drip/' ) );
		$this->set_requires_user( false );
		/* translators: email */
		$this->set_sentence( sprintf( esc_attr__( 'Create or update {{a subscriber:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr__( 'Create or update {{a subscriber}}', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_background_processing( true );

		$this->set_action_tokens(
			array(
				'SUBSCRIBER_ID' => array(
					'name' => __( 'Drip subscriber ID', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);

		$this->register_action();
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {

		$email = array(
			'option_code'     => $this->action_meta,
			'label'           => __( 'Email', 'uncanny-automator' ),
			'input_type'      => 'email',
			'required'        => true,
			'supports_tokens' => true,
		);

		$custom_fields = array(
			'option_code'       => 'FIELDS',
			'label'             => __( 'Fields', 'uncanny-automator' ),
			'input_type'        => 'repeater',
			'fields'            => array(
				array(
					'option_code'     => 'FIELD_NAME',
					'label'           => __( 'Field', 'uncanny-automator' ),
					'input_type'      => 'select',
					'supports_tokens' => false,
					'required'        => true,
					'read_only'       => false,
					'options_show_id' => false,
					'options'         => $this->functions->get_fields_options(),
					'placeholder'     => __( 'Select a field', 'uncanny-automator' ),
				),
				array(
					'option_code'     => 'FIELD_VALUE',
					'label'           => __( 'Value', 'uncanny-automator' ),
					'input_type'      => 'text',
					'supports_tokens' => true,
					'required'        => false,
					'read_only'       => false,
				),
			),
			'add_row_button'    => __( 'Add field', 'uncanny-automator' ),
			'remove_row_button' => __( 'Remove field', 'uncanny-automator' ),
			'hide_actions'      => false,
		);

		return array(
			'options_group' => array(
				$this->action_meta => array(
					$email,
					$custom_fields,
				),
			),
		);
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return null
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$email = Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args );

		$fields = json_decode( $action_data['meta']['FIELDS'], true );

		$fields = $this->parse_repeater_fields( $fields, $recipe_id, $user_id, $args );

		$error_msg = '';

		try {

			$response = $this->functions->create_subscriber( $email, $fields, $action_data );

			$subscriber = array_shift( $response['data']['subscribers'] );

			$this->hydrate_tokens(
				array(
					'SUBSCRIBER_ID' => $subscriber['id'],
				)
			);

		} catch ( \Exception $e ) {
			$error_msg                           = $e->getMessage();
			$action_data['complete_with_errors'] = true;
		}

		return Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_msg );
	}

	/**
	 * @param $fields
	 * @param $recipe_id
	 * @param $user_id
	 * @param $args
	 *
	 * @return array
	 */
	private function parse_repeater_fields( $fields, $recipe_id, $user_id, $args ) {
		if ( empty( $fields ) ) {
			return array();
		}

		$parsed = array();

		foreach ( $fields as $field ) {

			if ( empty( $field['FIELD_NAME'] ) || ! isset( $field['FIELD_VALUE'] ) ) {
				continue;
			}

			$key = sanitize_text_field( Automator()->parse->text( $field['FIELD_NAME'], $recipe_id, $user_id, $args ) );

			$value = sanitize_text_field( Automator()->parse->text( $field['FIELD_VALUE'], $recipe_id, $user_id, $args ) );

			$parsed[] = array(
				'FIELD_NAME'  => $key,
				'FIELD_VALUE' => $value,
			);
		}

		return $parsed;
	}
}
