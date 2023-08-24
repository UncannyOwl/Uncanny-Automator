<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class Uoa_Tokens
 *
 * @package Uncanny_Automator
 */
class Uoa_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UOA';

	/**
	 * Wp_Tokens constructor.
	 */
	public function __construct() {

		// For readability. Feel free to remove the strtolower, but type the code in lowercase.
		$user_completes_recipe_with_error_trigger_code = strtolower( 'UOA_USER_RECIPE_COMPLETED_WITH_ERRORS' );

		// Attaches the possible tokens to the Trigger. This trigger is implemented with new framework so it might be a good idea to handle it from there.
		// But I'm leaving this for now so we just have one file.
		add_filter(
			'automator_maybe_trigger_uoa_' . $user_completes_recipe_with_error_trigger_code . '_tokens',
			array(
				$this,
				'possible_tokens',
			),
			9999,
			2
		);

		add_filter(
			'automator_maybe_trigger_uoa_uoarecipe_tokens',
			array(
				$this,
				'possible_recipe_tokens',
			),
			9999,
			2
		);

		add_filter(
			'automator_maybe_trigger_uoa_uoa_recipes_tokens',
			array(
				$this,
				'possible_recipe_tokens',
			),
			9999,
			2
		);

		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );

		add_filter(
			'automator_maybe_trigger_uoa_anonuoarecipes_tokens',
			array(
				$this,
				'possible_anon_recipe_tokens',
			),
			9999,
			2
		);
		add_filter(
			'automator_maybe_trigger_uoa_anonuoaerrors_tokens',
			array(
				$this,
				'possible_anon_recipe_tokens',
			),
			9999,
			2
		);

		add_filter( 'automator_maybe_parse_token', array( $this, 'uoa_token' ), 20, 6 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'uoa_anonymous_token' ), 20, 6 );
	}

	/**
	 * save_token_data
	 *
	 * @param mixed $args
	 * @param mixed $trigger
	 *
	 * @return void
	 */
	public function save_token_data( $args, $trigger ) {
		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {
			return;
		}

		if ( 'UOA_RECIPE_COMPLETED' === $args['entry_args']['code'] || 'UOA_USER_RECIPE_COMPLETED_WITH_ERRORS' === $args['entry_args']['code'] ) {

			$recipe_id         = $args['trigger_args'][0];
			$trigger_log_entry = $args['trigger_entry'];

			$tokens = array(
				'status'        => $args['trigger_args'][3]['completed'],
				'error_message' => $args['trigger_args'][3]['completed'],
			);

			if ( ! empty( $recipe_id ) ) {
				Automator()->db->token->save( $args['entry_args']['code'] . '_TOKENS', maybe_serialize( $tokens ), $args['entry_args'] );
				Automator()->db->token->save( 'recipe_id', $recipe_id, $args['trigger_args'] );
			}
		}
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$new_tokens = array();

		$new_tokens[] = array(
			'tokenId'         => 'UOAERRORS',
			'tokenName'       => esc_attr__( 'Recipe ID', 'uncanny-automator' ),
			'tokenType'       => 'int',
			'tokenIdentifier' => 'UOAERRORS_recipe_id',
		);

		$new_tokens[] = array(
			'tokenId'         => 'UOAERRORS',
			'tokenName'       => esc_attr__( 'Recipe title', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'UOAERRORS_recipe_title',
		);

		$new_tokens[] = array(
			'tokenId'         => 'UOAERRORS',
			'tokenName'       => esc_attr__( 'Recipe status', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'UOAERRORS_recipe_status',
		);

		$new_tokens[] = array(
			'tokenId'         => 'UOAERRORS',
			'tokenName'       => esc_attr__( 'Recipe edit link', 'uncanny-automator' ),
			'tokenType'       => 'url',
			'tokenIdentifier' => 'UOAERRORS_recipe_edit_link',
		);

		$new_tokens[] = array(
			'tokenId'         => 'UOAERRORS',
			'tokenName'       => esc_attr__( 'Recipe log URL', 'uncanny-automator' ),
			'tokenType'       => 'url',
			'tokenIdentifier' => 'UOAERRORS_recipe_log_url',
		);

		$new_tokens[] = array(
			'tokenId'         => 'UOAERRORS',
			'tokenName'       => esc_attr__( 'Action log URL', 'uncanny-automator' ),
			'tokenType'       => 'url',
			'tokenIdentifier' => 'UOAERRORS_action_log_url',
		);

		$new_tokens[] = array(
			'tokenId'         => 'UOAERRORS',
			'tokenName'       => esc_attr__( 'Trigger log URL', 'uncanny-automator' ),
			'tokenType'       => 'url',
			'tokenIdentifier' => 'UOAERRORS_trigger_log_url',
		);

		return array_merge( $tokens, $new_tokens );
	}

	/**
	 * @param       $value
	 * @param       $pieces
	 * @param       $recipe_id
	 * @param       $trigger_data
	 * @param       $user_id
	 * @param array $replace_args
	 *
	 * @return string|null
	 */
	public function uoa_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args = array() ) {

		/**
		 * Specific Number of times token
		 */
		if ( in_array( 'RECIPENUMTIMES', $pieces, true ) ) {
			if ( empty( $trigger_data ) ) {
				return $value;
			}
			$trigger_data = array_shift( $trigger_data );
			if ( isset( $trigger_data['meta'][ $pieces[2] ] ) ) {
				return $trigger_data['meta'][ $pieces[2] ];
			}
		}

		/**
		 * Recipe ID fix
		 */
		if ( in_array( 'UOARECIPE', $pieces, true ) ) {
			if ( empty( $trigger_data ) ) {
				return $value;
			}
			$trigger_data = array_shift( $trigger_data );
			if ( isset( $trigger_data['meta'][ $pieces[2] ] ) ) {
				return $trigger_data['meta'][ $pieces[2] . '_readable' ];
			}
		}

		if ( ! in_array( 'UOAERRORS', $pieces, true ) &&
			 ! in_array( 'UOARECIPES', $pieces, true ) &&
			 ! in_array( 'UOARECIPESSTATUS', $pieces, true ) ) {
			return $value;
		}

		$get_recipe_id = Automator()->db->token->get( 'recipe_id', $replace_args );
		if ( ! empty( $get_recipe_id ) ) {
			$recipe_id = $get_recipe_id;
		}

		switch ( $pieces[1] ) {
			case 'UOAERRORS_recipe_id':
			case 'UOARECIPES_recipe_id':
				$value = $recipe_id;
				break;
			case 'UOARECIPESSTATUS':
			case 'UOARECIPES_recipe_status':
			case 'UOAERRORS_recipe_status':
				$recipe_log_id = $replace_args['recipe_log_id'];
				$run_number    = $replace_args['recipe_log_id'];
				$trigger_id    = $replace_args['trigger_id'];
				$value         = absint( Automator()->get->mayabe_get_token_meta_value_from_trigger_log( $trigger_id, $run_number, $recipe_id, 'recipe_status', 0, $recipe_log_id ) );
				$value         = Automator_Status::name( $value );
				break;
			case 'UOAERRORS_recipe_title':
			case 'UOAERRORS_recipe_edit_link':
			case 'UOAERRORS_recipe_log_url':
			case 'UOAERRORS_action_log_url':
			case 'UOAERRORS_trigger_log_url':
			case 'UOARECIPES_recipe_title':
			case 'UOARECIPES_recipe_edit_link':
			case 'UOARECIPES_recipe_log_url':
			case 'UOARECIPES_action_log_url':
			case 'UOARECIPES_trigger_log_url':
				$args = array(
					'log_dialog_id'  => $replace_args['recipe_log_id'],
					'log_recipe_id'  => $recipe_id,
					'log_run_number' => $replace_args['run_number'],
				);

				if ( 'UOAERRORS_recipe_log_url' === $pieces[1] || 'UOARECIPES_recipe_log_url' === $pieces[1] ) {
					$value = $this->generate_log_url( 'recipe', $args );
				}
				if ( 'UOAERRORS_trigger_log_url' === $pieces[1] || 'UOARECIPES_trigger_log_url' === $pieces[1] ) {
					$value = $this->generate_log_url( 'trigger', $args );
				}
				if ( 'UOAERRORS_action_log_url' === $pieces[1] || 'UOARECIPES_action_log_url' === $pieces[1] ) {
					$value = $this->generate_log_url( 'action', $args );
				}
				if ( 'UOAERRORS_recipe_edit_link' === $pieces[1] || 'UOARECIPES_recipe_edit_link' === $pieces[1] ) {
					$value = get_edit_post_link( $recipe_id );
				}
				if ( 'UOAERRORS_recipe_title' === $pieces[1] || 'UOARECIPES_recipe_title' === $pieces[1] ) {
					$value = get_the_title( $recipe_id );
				}
				break;
		}

		if ( empty( $value ) ) {
			if ( $trigger_data ) {
				foreach ( $trigger_data as $trigger ) {
					global $wpdb;
					$meta_field = $pieces[1];
					$trigger_id = $trigger['ID'];
					$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key LIKE %s AND automator_trigger_id = %d ORDER BY ID DESC LIMIT 0,1", "%%$meta_field%%", $trigger_id ) );
					if ( ! empty( $meta_value ) ) {
						$value = maybe_unserialize( $meta_value );

						if ( 'UOAERRORS_recipe_log_url' === $meta_field || 'UOARECIPES_recipe_log_url' === $meta_field ) {
							$value = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-admin-logs&' . $value;
						}
						if ( 'UOAERRORS_trigger_log_url' === $meta_field || 'UOARECIPES_trigger_log_url' === $meta_field ) {
							$value = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-admin-logs&' . $value;
						}
						if ( 'UOAERRORS_action_log_url' === $meta_field || 'UOARECIPES_action_log_url' === $meta_field ) {
							$value = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-admin-logs&' . $value;
						}
					}
				}
			}
		}

		return $value;
	}

	/**
	 * @param       $value
	 * @param       $pieces
	 * @param       $recipe_id
	 * @param       $trigger_data
	 * @param       $user_id
	 * @param array $replace_args
	 *
	 * @return string|null
	 */
	public function uoa_anonymous_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args = array() ) {

		if ( in_array( 'ANONUOAERRORS', $pieces, true )
			 || in_array( 'ANONUOARECIPES', $pieces, true ) ) {
			global $wpdb;

			switch ( $pieces[1] ) {
				case 'UOAERRORS_recipe_id':
				case 'UOARECIPES_recipe_id':
					$value = $recipe_id;
					break;
				case 'UOAERRORS_recipe_title':
				case 'UOAERRORS_recipe_edit_link':
				case 'UOAERRORS_recipe_log_url':
				case 'UOAERRORS_action_log_url':
				case 'UOARECIPES_recipe_title':
				case 'UOARECIPES_recipe_edit_link':
				case 'UOARECIPES_recipe_log_url':
				case 'UOARECIPES_action_log_url':
					$value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE automator_trigger_log_id = %d && meta_key = %s", $replace_args['trigger_log_id'], $pieces[1] ) );

					if ( 'UOAERRORS_recipe_log_url' === $pieces[1] || 'UOARECIPES_recipe_log_url' === $pieces[1] ) {
						$value = admin_url( 'edit.php' ) . "?post_type=uo-recipe&page=uncanny-automator-recipe-log&$value";
					}
					if ( 'UOAERRORS_action_log_url' === $pieces[1] || 'UOARECIPES_action_log_url' === $pieces[1] ) {
						$value = admin_url( 'edit.php' ) . "?post_type=uo-recipe&page=uncanny-automator-action-log&$value";
					}
					break;
			}
		}

		return $value;
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function possible_recipe_tokens( $tokens = array(), $args = array() ) {
		//      if ( ! automator_do_identify_tokens() ) {
		//          return $tokens;
		//      }

		$new_tokens = array();

		$new_tokens[] = array(
			'tokenId'         => 'UOARECIPES',
			'tokenName'       => esc_attr__( 'Recipe ID', 'uncanny-automator' ),
			'tokenType'       => 'int',
			'tokenIdentifier' => 'UOARECIPES_recipe_id',
		);

		$new_tokens[] = array(
			'tokenId'         => 'UOARECIPES',
			'tokenName'       => esc_attr__( 'Recipe title', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'UOARECIPES_recipe_title',
		);

		$new_tokens[] = array(
			'tokenId'         => 'UOARECIPES',
			'tokenName'       => esc_attr__( 'Recipe status', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'UOARECIPES_recipe_status',
		);

		$new_tokens[] = array(
			'tokenId'         => 'UOARECIPES',
			'tokenName'       => esc_attr__( 'Recipe edit link', 'uncanny-automator' ),
			'tokenType'       => 'url',
			'tokenIdentifier' => 'UOARECIPES_recipe_edit_link',
		);

		$new_tokens[] = array(
			'tokenId'         => 'UOARECIPES',
			'tokenName'       => esc_attr__( 'Recipe log URL', 'uncanny-automator' ),
			'tokenType'       => 'url',
			'tokenIdentifier' => 'UOARECIPES_recipe_log_url',
		);

		$new_tokens[] = array(
			'tokenId'         => 'UOARECIPES',
			'tokenName'       => esc_attr__( 'Action log URL', 'uncanny-automator' ),
			'tokenType'       => 'url',
			'tokenIdentifier' => 'UOARECIPES_action_log_url',
		);

		$new_tokens[] = array(
			'tokenId'         => 'UOARECIPES',
			'tokenName'       => esc_attr__( 'Trigger log URL', 'uncanny-automator' ),
			'tokenType'       => 'url',
			'tokenIdentifier' => 'UOARECIPES_trigger_log_url',
		);

		return array_merge( $tokens, $new_tokens );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function possible_anon_recipe_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$new_tokens = array(
			array(
				'tokenId'         => $trigger_meta,
				'tokenName'       => esc_attr__( 'User ID', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'UOARECIPES_user_id',
			),
			array(
				'tokenId'         => $trigger_meta,
				'tokenName'       => esc_attr__( 'Username', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'UOARECIPES_username',
			),
			array(
				'tokenId'         => $trigger_meta,
				'tokenName'       => esc_attr__( 'User email', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'UOARECIPES_user_email',
			),
			array(
				'tokenId'         => $trigger_meta,
				'tokenName'       => esc_attr__( 'Recipe ID', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'UOARECIPES_recipe_id',
			),
			array(
				'tokenId'         => $trigger_meta,
				'tokenName'       => esc_attr__( 'Recipe title', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'UOARECIPES_recipe_title',
			),
			array(
				'tokenId'         => $trigger_meta,
				'tokenName'       => esc_attr__( 'Recipe edit link', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'UOARECIPES_recipe_edit_link',
			),
			array(
				'tokenId'         => $trigger_meta,
				'tokenName'       => esc_attr__( 'Recipe log URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'UOARECIPES_recipe_log_url',
			),
			array(
				'tokenId'         => $trigger_meta,
				'tokenName'       => esc_attr__( 'Action log URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'UOARECIPES_action_log_url',
			),
		);

		return array_merge( $tokens, $new_tokens );
	}

	/**
	 * Generates a log URL
	 *
	 * @param string  $tab  - Can be 'trigger','recipe', or 'action'
	 * @param mixed[] $args - The query vars to pass.
	 *
	 * @return string The specific log URL
	 */
	private function generate_log_url( $tab = 'recipe', $args = array() ) {

		$defaults = array(
			'post_type' => 'uo-recipe',
			'page'      => 'uncanny-automator-admin-logs',
			'tab'       => $tab,
		);

		$query_vars = array_merge( $args, $defaults );

		return add_query_arg(
			$query_vars,
			admin_url( 'edit.php' )
		);

	}

}
