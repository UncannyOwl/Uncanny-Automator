<?php

namespace Uncanny_Automator\Integrations\Trello;

/**
 * Trait Trello_Card_Data
 *
 * Shared card options and data building for create/update card actions.
 *
 * @package Uncanny_Automator
 *
 * @property Trello_App_Helpers $helpers
 * @property Trello_Api_Caller  $api
 * @property bool               $is_update_action
 * @property string             $action_meta
 */
trait Trello_Card_Data {

	/**
	 * Get card options for the recipe builder.
	 *
	 * @return array
	 */
	public function get_card_options() {

		$board_meta = Trello_App_Helpers::ACTION_BOARD_META_KEY;
		$list_meta  = Trello_App_Helpers::ACTION_LIST_META_KEY;

		$options = array(
			$this->helpers->get_board_option_config( $board_meta ),
			$this->helpers->get_list_option_config( $list_meta, $board_meta ),
		);

		// Add card selector for update action.
		if ( $this->is_update_action ) {
			$options[] = $this->helpers->get_card_option_config( 'CARD', $list_meta, true );
		}

		// Card name.
		$options[] = array(
			'option_code' => $this->get_action_meta(),
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Name', 'Trello', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'tokens'      => true,
			'default'     => '',
		);

		// Description.
		$options[] = array(
			'option_code' => 'DESC',
			'input_type'  => 'textarea',
			'label'       => esc_html_x( 'Description', 'Trello', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'tokens'      => true,
			'default'     => '',
		);

		// Position.
		$options[] = array(
			'option_code'           => 'POS',
			'input_type'            => 'select',
			'label'                 => esc_html_x( 'Position', 'Trello', 'uncanny-automator' ),
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

		// Start date.
		$options[] = array(
			'option_code'     => 'START',
			'input_type'      => 'date',
			'label'           => esc_html_x( 'Start date', 'Trello', 'uncanny-automator' ),
			'supports_tokens' => true,
			'default'         => '',
		);

		// Due date.
		$options[] = array(
			'option_code'     => 'DUE',
			'input_type'      => 'date',
			'label'           => esc_html_x( 'Due date', 'Trello', 'uncanny-automator' ),
			'supports_tokens' => true,
			'default'         => '',
		);

		// Members.
		$options[] = $this->helpers->get_member_option_config( 'MEMBERS', $board_meta, false, true );

		// Labels.
		$options[] = $this->helpers->get_label_option_config( 'LABELS', $board_meta, false, true );

		// Custom fields repeater.
		$custom_fields_config = array(
			'option_code'     => 'CUSTOMFIELDS',
			'input_type'      => 'repeater',
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Custom fields', 'Trello', 'uncanny-automator' ),
			'required'        => false,
			'fields'          => array(
				array(
					'option_code' => 'FIELD_NAME',
					'label'       => esc_html_x( 'Field name', 'Trello', 'uncanny-automator' ),
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
					'label'       => esc_html_x( 'Value', 'Trello', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
				),
			),
			'hide_actions'    => true,
			'ajax'            => array(
				'endpoint'       => 'automator_trello_get_custom_fields_repeater',
				'event'          => 'parent_fields_change',
				'listen_fields'  => array( $board_meta ),
				'mapping_column' => 'FIELD_NAME',
			),
		);

		// Add description for update action.
		if ( $this->is_update_action ) {
			$custom_fields_config['description'] = esc_html_x(
				'Leaving a field value empty will not update the field. To delete a value from a field, set its value to [delete], including the square brackets.',
				'Trello',
				'uncanny-automator'
			);
		}

		$options[] = $custom_fields_config;

		return $options;
	}

	/**
	 * Build card data from parsed action data.
	 *
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param int   $user_id     The user ID.
	 * @param array $args        The args.
	 * @param array $parsed      The parsed data.
	 *
	 * @return array
	 */
	public function build_card_data( $action_data, $recipe_id, $user_id, $args, $parsed ) {

		return array(
			'idList'       => $action_data['meta']['LIST'],
			'name'         => Automator()->parse->text( $action_data['meta'][ $this->get_action_meta() ], $recipe_id, $user_id, $args ),
			'desc'         => Automator()->parse->text( $action_data['meta']['DESC'], $recipe_id, $user_id, $args ),
			'pos'          => $action_data['meta']['POS'],
			'start'        => Automator()->parse->text( $action_data['meta']['START'], $recipe_id, $user_id, $args ),
			'due'          => Automator()->parse->text( $action_data['meta']['DUE'], $recipe_id, $user_id, $args ),
			'idMembers'    => $this->helpers->comma_separated( $action_data['meta']['MEMBERS'] ),
			'idLabels'     => $this->helpers->comma_separated( $action_data['meta']['LABELS'] ),
			'customFields' => $this->parse_custom_fields_values( $action_data['meta']['CUSTOMFIELDS'], $recipe_id, $user_id, $args ),
		);
	}

	/**
	 * Parse custom fields values.
	 *
	 * @param string $value     The JSON value.
	 * @param int    $recipe_id The recipe ID.
	 * @param int    $user_id   The user ID.
	 * @param array  $args      The args.
	 *
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

	/**
	 * Get card token definitions.
	 *
	 * @return array
	 */
	public function get_card_token_definitions() {
		return array(
			'CARD_ID'  => array(
				'name' => esc_html_x( 'Card ID', 'Trello', 'uncanny-automator' ),
				'type' => 'text',
			),
			'CARD_URL' => array(
				'name' => esc_html_x( 'Card URL', 'Trello', 'uncanny-automator' ),
				'type' => 'url',
			),
		);
	}

	/**
	 * Hydrate card tokens from the API response.
	 *
	 * @param array $response The API response.
	 *
	 * @return void
	 */
	public function hydrate_card_tokens( $response ) {

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
}
