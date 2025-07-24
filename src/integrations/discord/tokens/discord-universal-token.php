<?php

namespace Uncanny_Automator\Integrations\Discord;

use Uncanny_Automator\Tokens\Universal_Token;

/**
 * Discord Universal Token
 *
 * @package Uncanny_Automator\Integrations\Discord
 */
class Discord_Universal_Token extends Universal_Token {

	/**
	 * Discord Snowflake ID
	 *
	 * @var string
	 */
	const DISCORD_SNOWFLAKE = 'DISCORD_SNOWFLAKE';

	/**
	 * Setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration   = 'DISCORD';
		$this->id            = self::DISCORD_SNOWFLAKE;
		$this->name          = esc_attr_x( 'Discord ID (Snowflake)', 'Discord', 'uncanny-automator' );
		$this->requires_user = true;
		$this->cacheable     = false;
	}

	/**
	 * Parse integration token
	 *
	 * @param mixed $default_return
	 * @param array $pieces
	 * @param int $recipe_id
	 * @param array $trigger_data
	 * @param int $user_id
	 * @param array $replace_args
	 *
	 * @return mixed|string
	 */
	public function parse_integration_token( $default_return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$token_id = $pieces[2];

		// Change the user ID to the current iterated user in the context of a Loop.
		if ( isset( $replace_args['loop'] ) && is_array( $replace_args['loop'] ) && isset( $replace_args['loop']['user_id'] ) ) {
			$user_id = absint( $replace_args['loop']['user_id'] );
		}

		// Handle Discord ID token
		if ( self::DISCORD_SNOWFLAKE === $token_id ) {
			$discord_id = get_user_meta( $user_id, Discord_Helpers::DISCORD_USER_MAPPING_META_KEY, true );
			return ! empty( $discord_id ) ? $discord_id : '';
		}

		return $default_return;
	}
}
