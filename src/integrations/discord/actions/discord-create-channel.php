<?php
namespace Uncanny_Automator\Integrations\Discord;

use Exception;

/**
 * Class DISCORD_CREATE_CHANNEL
 *
 * @package Uncanny_Automator
 *
 * @property Discord_App_Helpers $helpers
 * @property Discord_Api_Caller $api
 */
class DISCORD_CREATE_CHANNEL extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Prefix for action code / meta.
	 *
	 * @var string
	 */
	public $prefix = 'DISCORD_CREATE_CHANNEL';

	/**
	 * Server meta key.
	 *
	 * @var string
	 */
	private $server_key;

	/**
	 * Set up action.
	 *
	 * @return void
	 */
	public function setup_action() {
		// Set server key property.
		$this->server_key = $this->helpers->get_const( 'ACTION_SERVER_META_KEY' );

		$this->set_integration( 'DISCORD' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/discord/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s Channel name
				esc_attr_x( 'Create {{a channel:%1$s}}', 'Discord', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Create {{a channel}}', 'Discord', 'uncanny-automator' ) );
		$this->set_action_tokens(
			array(
				'SERVER_ID'    => array(
					'name' => esc_html_x( 'Server ID', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
				'SERVER_NAME'  => array(
					'name' => esc_html_x( 'Server name', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
				'CHANNEL_NAME' => array(
					'name' => esc_html_x( 'Channel name', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
				'CHANNEL_ID'   => array(
					'name' => esc_html_x( 'Channel ID', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
				'CHANNEL_TYPE' => array(
					'name' => esc_html_x( 'Channel type', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define options
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_server_select_config( $this->server_key ),
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Channel name', 'Discord', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
				'description' => esc_html_x( 'Enter a channel name no longer than 100 characters', 'Discord', 'uncanny-automator' ),
			),
			array(
				'option_code'           => 'CHANNEL_TYPE',
				'label'                 => esc_html_x( 'Channel type', 'Discord', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'options_show_id'       => false,
				'relevant_tokens'       => array(),
				'ajax'                  => array(
					'endpoint'      => 'automator_discord_get_allowed_channel_types',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( $this->server_key ),
				),
				'description'           => sprintf(
					// translators: %1$s Opening anchor tag, %2$s Closing anchor tag
					esc_html_x( 'To create Announcement, Stage, Forum or Media channels, your Discord server must have Community features enabled %1$sLearn more.%2$s', 'Discord', 'uncanny-automator' ),
					'<a href="https://support.discord.com/hc/en-us/articles/360047132851-Enabling-Your-Community-Server" target="_blank">',
					'</a>'
				),
			),
			array(
				'option_code'        => 'TOPIC',
				'label'              => esc_html_x( 'Topic', 'Discord', 'uncanny-automator' ),
				'input_type'         => 'text',
				'dynamic_visibility' => $this->get_channel_type_visibility_rule( $this->valid_topic_channel_types ),
				'description'        => esc_html_x( 'Optional topic no longer than 1024 characters', 'Discord', 'uncanny-automator' ),
			),
			array(
				'option_code'        => 'USER_RATE_LIMIT',
				'label'              => esc_html_x( 'Rate limit per user', 'Discord', 'uncanny-automator' ),
				'input_type'         => 'int',
				'default_value'      => 0,
				'dynamic_visibility' => $this->get_channel_type_visibility_rule( $this->valid_rate_channel_types ),
				'description'        => esc_html_x( 'Optional amount of seconds a user must wait before sending another message (0-21600)', 'Discord', 'uncanny-automator' ),
			),
			array(
				'option_code'   => 'POSITION',
				'label'         => esc_html_x( 'Position', 'Discord', 'uncanny-automator' ),
				'input_type'    => 'int',
				'default_value' => 0,
				'description'   => esc_html_x( 'Optional sorting position for the channel (0-1000)', 'Discord', 'uncanny-automator' ),
			),
			array(
				'option_code'        => 'NSFW',
				'label'              => esc_html_x( 'NSFW', 'Discord', 'uncanny-automator' ),
				'input_type'         => 'checkbox',
				'is_toggle'          => true,
				'dynamic_visibility' => $this->get_channel_type_visibility_rule( $this->valid_nsfw_channel_types ),
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
	 * @throws Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		// Required fields - throws error if not set and valid.
		$server_id = $this->helpers->get_server_id_from_parsed( $parsed, $this->server_key );
		$name      = trim( $this->get_parsed_meta_value( $this->get_action_meta(), false ) );
		// Validate the channel name length.
		if ( strlen( $name ) > 100 ) {
			throw new Exception( esc_html_x( 'Channel name must be no longer than 100 characters', 'Discord', 'uncanny-automator' ) );
		}
		$type = absint( $this->get_parsed_meta_value( 'CHANNEL_TYPE', 0 ) );
		// Validate the channel type.
		$type_ids = wp_list_pluck( $this->helpers->get_channel_types(), 'value' );
		if ( ! in_array( $type, $type_ids, true ) ) {
			throw new Exception( esc_html_x( 'Invalid channel type', 'Discord', 'uncanny-automator' ) );
		}

		// Start building the conditional properties.
		$conditional = array();

		// Optional fields.

		$position = absint( $this->get_parsed_meta_value( 'POSITION', 0 ) );
		// Validate the position.
		if ( $position < 0 || $position > 1000 ) {
			throw new Exception( esc_html_x( 'Position must be between 0 and 1000', 'Discord', 'uncanny-automator' ) );
		}
		$conditional['position'] = $position;

		// Conditional fields.

		if ( in_array( $type, $this->valid_topic_channel_types, true ) ) {
			$topic = $this->get_parsed_meta_value( 'TOPIC', '' );
			// Validate the topic length.
			if ( strlen( $topic ) > 1024 ) {
				throw new Exception( esc_html_x( 'Topic must be no longer than 1024 characters', 'Discord', 'uncanny-automator' ) );
			}
			$conditional['topic'] = $topic;
		}

		if ( in_array( $type, $this->valid_rate_channel_types, true ) ) {
			$user_rate_limit = absint( $this->get_parsed_meta_value( 'USER_RATE_LIMIT', 0 ) );
			// Validate the user rate limit.
			if ( $user_rate_limit < 0 || $user_rate_limit > 21600 ) {
				throw new Exception( esc_html_x( 'User rate limit must be between 0 and 21600', 'Discord', 'uncanny-automator' ) );
			}
			$conditional['rate_limit_per_user'] = $user_rate_limit;
		}

		if ( in_array( $type, $this->valid_nsfw_channel_types, true ) ) {
			$nsfw                = $this->helpers->get_bool_value( $this->get_parsed_meta_value( 'NSFW', false ) );
			$conditional['nsfw'] = $nsfw;
		}

		// Create the channel.
		$body     = array(
			'action'      => 'create_channel',
			'name'        => $name,
			'type'        => $type,
			'conditional' => $conditional,
		);
		$response = $this->api->discord_request( $body, $action_data, $server_id );

		// REVIEW - Check for errors.
		$channel_id = $response['data']['id'];

		// Hydrate tokens.
		$this->hydrate_tokens(
			array(
				'SERVER_ID'    => $server_id,
				'SERVER_NAME'  => $parsed[ $this->server_key . '_readable' ],
				'CHANNEL_ID'   => $channel_id,
				'CHANNEL_TYPE' => $parsed['CHANNEL_TYPE_readable'],
			)
		);

		return true;
	}

	/**
	 * Valid topic channel types.
	 *
	 * @var array
	 */
	private $valid_topic_channel_types = array( 0, 5, 15, 16 );

	/**
	 * Valid rate channel types.
	 *
	 * @var array
	 */
	private $valid_rate_channel_types = array( 0, 2, 13, 15, 16 );

	/**
	 * Valid nsfw channel types.
	 *
	 * @var array
	 */
	private $valid_nsfw_channel_types = array( 0, 2, 5, 13, 15 );

	/**
	 * Get channel type visibility rule.
	 *
	 * @param array $channel_types
	 *
	 * @return array
	 */
	private function get_channel_type_visibility_rule( $channel_types ) {

		$rule = array(
			'default_state'    => 'hidden',
			'visibility_rules' => array(
				array(
					'operator'             => 'OR',
					'rule_conditions'      => array(),
					'resulting_visibility' => 'show',
				),
			),
		);

		foreach ( $channel_types as $channel_type ) {
			$rule['visibility_rules'][0]['rule_conditions'][] = array(
				'option_code' => 'CHANNEL_TYPE',
				'compare'     => '==',
				'value'       => $channel_type,
			);
		}

		return $rule;
	}
}
