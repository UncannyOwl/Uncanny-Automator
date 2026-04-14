<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class TEAMS_ADD_TAG_MEMBER
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_App_Helpers $helpers
 * @property Microsoft_Teams_Api_Caller $api
 */
class TEAMS_ADD_TAG_MEMBER extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'TEAMS_ADD_TAG_MEMBER_CODE' );
		$this->set_action_meta( 'TEAMS_TAG_MEMBER_TAG_ID' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/microsoft-teams/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Add {{a user}} to {{a tag}} in {{a team}}', 'Microsoft Teams', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: 1: User, 2: Tag, 3: Team
				esc_attr_x( 'Add {{a user:%1$s}} to {{a tag:%2$s}} in {{a team:%3$s}}', 'Microsoft Teams', 'uncanny-automator' ),
				'TEAMS_TAG_MEMBER_USER:' . $this->get_action_meta(),
				$this->get_action_meta(),
				'TEAMS_TAG_MEMBER_TEAM:' . $this->get_action_meta()
			)
		);
		$this->set_action_tokens(
			array(
				'TEAMS_TAG_MEMBER_ID' => array(
					'name' => esc_html_x( 'Tag member ID', 'Microsoft Teams', 'uncanny-automator' ),
					'type' => 'text',
				),
				'TEAMS_TAG_NAME'      => array(
					'name' => esc_html_x( 'Tag name', 'Microsoft Teams', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define the options for the action.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_team_select_option_config( 'TEAMS_TAG_MEMBER_TEAM' ),
			$this->get_tag_select_option_config(),
			$this->helpers->get_user_option_config( 'TEAMS_TAG_MEMBER_USER' ),
		);
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
	 * @return bool
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$team_id = $this->helpers->get_team_id_from_parsed( $parsed, 'TEAMS_TAG_MEMBER_TEAM' );
		$tag_id  = $this->helpers->get_text_value_from_parsed( $parsed, $this->get_action_meta(), esc_html_x( 'Tag ID', 'Microsoft Teams', 'uncanny-automator' ) );

		$body = array(
			'action'  => 'add_tag_member',
			'team_id' => $team_id,
			'tag_id'  => $tag_id,
			'member'  => $this->helpers->get_user_identifier_from_parsed( $parsed, 'TEAMS_TAG_MEMBER_USER' ),
		);

		$response = $this->api->api_request( $body, $action_data );
		$data     = $response['data'] ?? array();

		$this->hydrate_tokens(
			array(
				'TEAMS_TAG_MEMBER_ID' => $data['id'] ?? '',
				'TEAMS_TAG_NAME'      => $this->resolve_tag_name( $parsed, $team_id, $tag_id ),
			)
		);

		return true;
	}

	/**
	 * Get the tag select field configuration.
	 *
	 * @return array
	 */
	private function get_tag_select_option_config() {
		return array(
			'option_code'              => $this->get_action_meta(),
			'input_type'               => 'select',
			'label'                    => esc_html_x( 'Tag', 'Microsoft Teams', 'uncanny-automator' ),
			'placeholder'              => esc_html_x( 'Select a tag', 'Microsoft Teams', 'uncanny-automator' ),
			'required'                 => true,
			'options'                  => array(),
			'supports_custom_value'    => true,
			'custom_value_description' => esc_html_x( 'Tag ID', 'Microsoft Teams', 'uncanny-automator' ),
			'options_show_id'          => false,
			'ajax'                     => array(
				'endpoint'      => 'automator_microsoft_teams_get_team_tags',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'TEAMS_TAG_MEMBER_TEAM' ),
			),
		);
	}

	/**
	 * Resolve a tag name for the token.
	 *
	 * 1. Use the dropdown readable label if it's a real tag name.
	 * 2. Match the tag ID against cached dropdown options.
	 * 3. Refresh the cache from the API and try once more.
	 * 4. Fall back to the raw tag ID.
	 *
	 * @param array  $parsed  The parsed meta values.
	 * @param string $team_id The team ID.
	 * @param string $tag_id  The resolved tag ID.
	 *
	 * @return string The tag name, or the tag ID if it cannot be resolved.
	 */
	private function resolve_tag_name( $parsed, $team_id, $tag_id ) {

		// 1. The readable value is the dropdown label — use it unless it's the
		//    "Use a token/custom value" placeholder or the raw tag ID.
		$readable = $parsed[ $this->get_action_meta() . '_readable' ] ?? '';

		if ( ! empty( $readable ) && $readable !== $tag_id && ! $this->helpers->is_token_custom_value_text( $readable ) ) {
			return sanitize_text_field( $readable );
		}

		// 2. Try to match the tag ID in cached options.
		$name = $this->find_tag_name_in_cache( $team_id, $tag_id );

		if ( ! empty( $name ) ) {
			return $name;
		}

		// 3. Cache may be stale — refresh from the API and try once more.
		try {
			$tags       = $this->api->get_team_tags( $team_id );
			$option_key = $this->helpers->get_option_key( 'tags_' . $team_id );
			$this->helpers->save_app_option( $option_key, $tags );

			$name = $this->find_tag_name_in_cache( $team_id, $tag_id );

			if ( ! empty( $name ) ) {
				return $name;
			}
		} catch ( \Exception $e ) {
			automator_log( $e->getMessage() );
		}

		return $tag_id;
	}

	/**
	 * Look up a tag name by ID in the cached dropdown options.
	 *
	 * @param string $team_id The team ID.
	 * @param string $tag_id  The tag ID to find.
	 *
	 * @return string The tag name, or empty string if not found.
	 */
	private function find_tag_name_in_cache( $team_id, $tag_id ) {

		$option_key = $this->helpers->get_option_key( 'tags_' . $team_id );
		$cached     = $this->helpers->get_app_option( $option_key );

		if ( ! empty( $cached['data'] ) ) {
			foreach ( $cached['data'] as $option ) {
				if ( isset( $option['value'] ) && $option['value'] === $tag_id ) {
					return sanitize_text_field( $option['text'] );
				}
			}
		}

		return '';
	}
}
