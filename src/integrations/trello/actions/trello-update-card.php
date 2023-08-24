<?php

namespace Uncanny_Automator;

/**
 * Class TRELLO_UPDATE_CARD
 *
 * @package Uncanny_Automator
 */
class TRELLO_UPDATE_CARD {

	use Recipe\Actions;
	use Recipe\Action_Tokens;

	/**
	 * @var Trello_Functions
	 */
	private $functions;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {

		$this->functions = new Trello_Functions();

		$this->setup_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function setup_action() {

		$this->set_integration( 'TRELLO' );
		$this->set_action_code( 'UPDATE_CARD' );
		$this->set_action_meta( 'CARD_NAME' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/trello/' ) );
		$this->set_requires_user( false );
		/* translators: card name */
		$this->set_sentence( sprintf( esc_attr__( 'Update {{a card:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr__( 'Update {{a card}}', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_background_processing( true );

		$this->set_buttons(
			array(
				array(
					'show_in'     => $this->action_meta,
					'text'        => __( 'Get custom fields', 'uncanny-automator' ),
					'css_classes' => 'uap-btn uap-btn--red',
					'on_click'    => 'automator_trello_get_custom_fields',
					'modules'     => array( 'modal', 'markdown' ),
				),
			)
		);

		$this->register_action();
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {

		$user_boards_field = array(
			'option_code'           => 'BOARD',
			'label'                 => __( 'Board', 'uncanny-automator' ),
			'token_name'            => __( 'Board ID', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'is_ajax'               => false,
			'options'               => $this->functions->user_boards_options(),
			'supports_custom_value' => false,
		);

		$board_lists_field = array(
			'option_code'           => 'LIST',
			'label'                 => __( 'List', 'uncanny-automator' ),
			'token_name'            => __( 'List ID', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'is_ajax'               => false,
			'options'               => array(),
			'supports_custom_value' => false,
			'ajax'                  => array(
				'endpoint'      => 'automator_trello_get_board_lists',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'BOARD' ),
			),
		);

		$list_cards_field = array(
			'option_code'           => 'CARD',
			'label'                 => __( 'Card', 'uncanny-automator' ),
			'token_name'            => __( 'Card ID', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'supports_custom_value' => true,
			'ajax'                  => array(
				'endpoint'      => 'automator_trello_get_list_cards',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'LIST' ),
			),
		);

		$card_name_field = array(
			'option_code' => $this->action_meta,
			'input_type'  => 'text',
			'label'       => esc_attr__( 'Name', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'tokens'      => true,
			'default'     => '',
		);

		$card_description_field = array(
			'option_code' => 'DESC',
			'input_type'  => 'textarea',
			'label'       => esc_attr__( 'Description', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'tokens'      => true,
			'default'     => '',
		);

		$card_position_field = array(
			'option_code'           => 'POS',
			'input_type'            => 'select',
			'label'                 => esc_attr__( 'Position', 'uncanny-automator' ),
			'placeholder'           => '',
			'description'           => '',
			'tokens'                => false,
			'options'               => array(
				array(
					'value' => 'top',
					'text'  => 'Top',
				),
				array(
					'value' => 'bottom',
					'text'  => 'Bottom',
				),

			),
			'default'               => '',
			'supports_custom_value' => false,
			'options_show_id'       => false,
		);

		$card_start_date_field = array(
			'option_code'     => 'START',
			'input_type'      => 'date',
			'label'           => esc_attr__( 'Start date', 'uncanny-automator' ),
			'placeholder'     => '',
			'description'     => '',
			'supports_tokens' => true,
			'default'         => '',
		);

		$card_due_date_field = array(
			'option_code'     => 'DUE',
			'input_type'      => 'date',
			'label'           => esc_attr__( 'Due date', 'uncanny-automator' ),
			'placeholder'     => '',
			'description'     => '',
			'supports_tokens' => true,
			'default'         => '',
		);

		$card_members_field = array(
			'option_code'              => 'MEMBERS',
			'input_type'               => 'select',
			'supports_multiple_values' => true,
			'options'                  => array(),
			'label'                    => esc_attr__( 'Members', 'uncanny-automator' ),
			'placeholder'              => __( 'No member', 'uncanny-automator' ),
			'tokens'                   => true,
			'default'                  => '',
			'ajax'                     => array(
				'endpoint'      => 'automator_trello_get_board_members',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'BOARD' ),
			),
		);

		$card_labels_field = array(
			'option_code'              => 'LABELS',
			'input_type'               => 'select',
			'supports_multiple_values' => true,
			'options'                  => array(),
			'label'                    => esc_attr__( 'Labels', 'uncanny-automator' ),
			'placeholder'              => __( 'No label', 'uncanny-automator' ),
			'tokens'                   => true,
			'default'                  => '',
			'options_show_id'          => false,
			'ajax'                     => array(
				'endpoint'      => 'automator_trello_get_board_labels',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'BOARD' ),
			),
		);

		$custom_fields = array(
			'option_code'   => 'CUSTOMFIELDS',
			'input_type'    => 'repeater',
			'label'         => __( 'Custom fields', 'uncanny-automator' ),
			'description'   => __( 'Leaving a field value empty will not update the field. To delete a value from a field, set its value to [delete], including the square brackets.', 'uncanny-automator' ),
			'required'      => false,
			'default_value' => array(
				array(
					'FIELD_NAME'  => '',
					'FIELD_OBJ'   => '',
					'FIELD_VALUE' => '',
				),
			),
			'fields'        => array(
				array(
					'option_code' => 'FIELD_NAME',
					'label'       => __( 'Field name', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
					'read_only'   => true,
					'options'     => array(),
				),
				array(
					'option_code' => 'FIELD_OBJ',
					'input_type'  => 'text',
					'required'    => false,
					'read_only'   => true,
					'is_hidden'   => true,
					'options'     => array(),
				),
				array(
					'option_code' => 'FIELD_VALUE',
					'label'       => __( 'Value', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
				),
			),
			'hide_actions'  => true,
		);

		return array(
			'options_group' => array(
				$this->action_meta => array(
					$user_boards_field,
					$board_lists_field,
					$list_cards_field,
					$card_name_field,
					$card_description_field,
					$card_position_field,
					$card_start_date_field,
					$card_due_date_field,
					$card_members_field,
					$card_labels_field,
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
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$card_id = Automator()->parse->text( $action_data['meta']['CARD'] );

		$card = array(
			'idList'       => $action_data['meta']['LIST'],
			'name'         => Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args ),
			'desc'         => Automator()->parse->text( $action_data['meta']['DESC'], $recipe_id, $user_id, $args ),
			'pos'          => $action_data['meta']['POS'],
			'start'        => Automator()->parse->text( $action_data['meta']['START'], $recipe_id, $user_id, $args ),
			'due'          => Automator()->parse->text( $action_data['meta']['DUE'], $recipe_id, $user_id, $args ),
			'idMembers'    => $action_data['meta']['MEMBERS'],
			'idLabels'     => $action_data['meta']['LABELS'],
			'customFields' => $this->parse_custom_fields_values( $action_data['meta']['CUSTOMFIELDS'], $recipe_id, $user_id, $args ),
		);

		$error_msg = '';

		try {

			$response = $this->functions->api->update_card( $card_id, $card );
			$this->process_tokens( $response );

		} catch ( \Exception $e ) {
			$error_msg                           = $e->getMessage();
			$action_data['complete_with_errors'] = true;
		}

		return Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_msg );
	}

	/**
	 * process_tokens
	 *
	 * @param  mixed $response
	 * @return void
	 */
	public function process_tokens( $response ) {

		$tokens = array();

		if ( ! empty( $response['data']['id'] ) ) {
			$tokens['CARD_ID'] = $response['data']['id'];
		}

		if ( ! empty( $response['data']['shortUrl'] ) ) {
			$tokens['CARD_URL'] = $response['data']['shortUrl'];
		}

		if ( ! empty( $tokens ) ) {
			$this->hydrate_tokens( $tokens );
		}

	}

	/**
	 * parse_custom_fields_values
	 *
	 * @param $value
	 * @param $recipe_id
	 * @param $user_id
	 * @param $args
	 * @return array
	 */
	public function parse_custom_fields_values( $value, $recipe_id, $user_id, $args ) {

		$fields = json_decode( $value, true );

		if ( ! $fields ) {
			return array();
		}

		$output = array();

		foreach ( $fields as $field ) {
			$output[] = array(
				'object' => $field['FIELD_OBJ'],
				'value'  => Automator()->parse->text( $field['FIELD_VALUE'], $recipe_id, $user_id, $args ),
			);
		}

		return $output;
	}
}
