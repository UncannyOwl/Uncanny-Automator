<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Input_Parser
 *
 * @package Uncanny_Automator
 */
class Automator_Input_Parser {

	/**
	 * @var
	 */
	public static $instance;
	/**
	 * @var array|mixed|void
	 */
	public $defined_tokens = array();
	/**
	 * @var string
	 */
	public $url_regx = "/(?:(?:https?|ftp):\/\/)(?:\S+(?::\S*)?@)?(?:(?!(?:10|127)(?:\.\d{1,3}){3})(?!(?:169\.254|192\.168)(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]-*)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]-*)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,}))\.?)(?::\d{2,5})?(?:[\/?#]\S*)?$/u";

	/**
	 * Automator_Input_Parser constructor.
	 */
	public function __construct() {
		$this->defined_tokens = apply_filters(
			'automator_pre_defined_tokens',
			array(
				'recipe_total_run',
				'recipe_run',
			)
		);

		add_filter( 'automator_maybe_parse_token', array( $this, 'automator_maybe_parse_postmeta_token' ), 99999, 6 );

		// Attach the new trigger tokens arch for actions that are scheduled.
		add_filter( 'automator_pro_before_async_action_executed', array( $this, 'attach_trigger_tokens_hook' ), 10, 1 );

	}

	/**
	 * @return Automator_Input_Parser
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @param null $url
	 * @param null $recipe_id
	 * @param array $trigger_args
	 *
	 * @return string|string[]|null
	 */
	public function url( $url = null, $recipe_id = null, $trigger_args = array() ) {

		// Sanity check that there was a $field_text passed
		if ( null === $url ) {
			return null;
		}

		$original_url = $url;

		if ( '/' === (string) $url[0] ) {
			// Relative URL prefix with site url
			$url = '{{site_url}}' . $url;
		}

		// Replace Tokens
		$args = array(
			'field_text'  => $url,
			'meta_key'    => null,
			'user_id'     => null,
			'action_data' => null,
			'recipe_id'   => $recipe_id,
		);
		if ( ! empty( $trigger_args['trigger_log_id'] ) ) {
			$args['trigger_log_id'] = $trigger_args['trigger_log_id'];
		}
		if ( ! empty( $trigger_args['run_number'] ) ) {
			$args['run_number'] = $trigger_args['run_number'];
		}
		if ( ! empty( $trigger_args['recipe_log_id'] ) ) {
			$args['recipe_log_id'] = $trigger_args['recipe_log_id'];
		}
		if ( ! empty( $trigger_args['trigger_id'] ) ) {
			$args['trigger_id'] = $trigger_args['trigger_id'];
		}

		$url = $this->parse_vars( $args, $trigger_args );

		if ( ! preg_match( $this->url_regx, $url ) ) {
			// if the url is not valid and / isn't the first character then the url is missing the site url
			$url = '{{site_url}}/' . $url;
			// Replace Tokens
			$args = array(
				'field_text'  => $url,
				'meta_key'    => null,
				'user_id'     => null,
				'action_data' => null,
				'recipe_id'   => $recipe_id,
			);

			if ( ! empty( $trigger_args['trigger_log_id'] ) ) {
				$args['trigger_log_id'] = $trigger_args['trigger_log_id'];
			}
			if ( ! empty( $trigger_args['run_number'] ) ) {
				$args['run_number'] = $trigger_args['run_number'];
			}
			if ( ! empty( $trigger_args['recipe_log_id'] ) ) {
				$args['recipe_log_id'] = $trigger_args['recipe_log_id'];
			}
			if ( ! empty( $trigger_args['trigger_id'] ) ) {
				$args['trigger_id'] = $trigger_args['trigger_id'];
			}

			$url = $this->parse_vars( $args, $trigger_args );

		}

		// Replace all spaces with %20
		$url = str_replace( ' ', '%20', $url );

		if ( ! preg_match( $this->url_regx, $url ) ) {
			// if the url is not valid still then something when wrong...
			return null;
		}

		return $url;
	}

	/**
	 * @param $args
	 *
	 * @return int|null
	 */
	public function get_recipe_id( $args ) {
		return array_key_exists( 'recipe_id', $args ) ? absint( $args['recipe_id'] ) : null;
	}

	/**
	 * @param $args
	 *
	 * @return int|null
	 */
	public function get_recipe_log_id( $args ) {
		return array_key_exists( 'recipe_log_id', $args ) ? absint( $args['recipe_log_id'] ) : null;
	}

	/**
	 * @param $args
	 *
	 * @return int|null
	 */
	public function get_trigger_id( $args ) {
		return array_key_exists( 'trigger_id', $args ) ? absint( $args['trigger_id'] ) : null;
	}

	/**
	 * @param $args
	 * @param $trigger_id
	 *
	 * @return int|null
	 */
	public function get_trigger_log_id( $args, $trigger_id ) {
		$trigger_log_id = array_key_exists( 'trigger_log_id', $args ) ? absint( $args['trigger_log_id'] ) : null;
		if ( ! isset( $args['recipe_triggers'] ) ) {
			return $trigger_log_id;
		}
		/**
		 * src/core/lib/process/class-automator-recipe-process-complete.php before $this->complete_actions()
		 *
		 * @since 4.3
		 */
		if ( ! isset( $args['recipe_triggers'][ $trigger_id ] ) || ! isset( $args['recipe_triggers'][ $trigger_id ]['trigger_log_id'] ) ) {
			return $trigger_log_id;
		}

		return absint( $args['recipe_triggers'][ $trigger_id ]['trigger_log_id'] );
	}

	/**
	 * @param $args
	 *
	 * @return int|null
	 */
	public function get_run_number( $args ) {
		return array_key_exists( 'run_number', $args ) ? absint( $args['run_number'] ) : null;
	}

	/**
	 * @param $args
	 *
	 * @return int|null
	 */
	public function get_user_id( $args ) {
		return array_key_exists( 'user_id', $args ) ? absint( $args['user_id'] ) : null;
	}

	/**
	 * Parse field text by replacing variable with real data
	 *
	 * @param $args
	 * @param $trigger_args
	 *
	 * @return string
	 */
	public function parse_vars( $args, $trigger_args = array() ) {

		$parsed_tokens_record = Automator()->parsed_token_records();

		$field_text     = $args['field_text'];
		$meta_key       = $args['meta_key'];
		$action_data    = $args['action_data'];
		$user_id        = isset( $trigger_args['user_id'] ) ? $trigger_args['user_id'] : null; // The user ID by default should be the Trigger's user ID. Passes null as default to not accidentally run as everyone type.
		$recipe_id      = $this->get_recipe_id( $args );
		$recipe_log_id  = $this->get_recipe_log_id( $args );
		$trigger_id     = $this->get_trigger_id( $args );
		$trigger_log_id = $this->get_trigger_log_id( $args, $trigger_id );
		$run_number     = $this->get_run_number( $args );

		// Find brackets and replace with real data.
		preg_match_all( '/{{\s*(.*?)\s*}}/', $field_text, $arr );

		if ( empty( $arr ) ) {
			return str_replace( array( '{{', '}}' ), '', $field_text );
		}

		$matches = $arr[1];

		foreach ( $matches as $match ) {

			$replaceable = '';

			if ( false !== strpos( $match, ':' ) ) {

				// This section is for user meta tokens.
				if ( preg_match( '/(USERMETA)/', $match ) ) {

					$user_meta_uid = $args['user_id']; // The user meta should be based from the user which owns the action.

					// Attempt user ID recovery from current logged-in user for nulled $user_id.
					if ( is_null( $user_id ) || 0 === absint( $user_id ) ) {
						$user_meta_uid = wp_get_current_user()->ID;
					}

					if ( 0 !== $user_meta_uid ) {

						$pieces = explode( ':', $match );

						switch ( $pieces[0] ) {

							case 'USERMETAEMAIL':
								$user_meta   = get_user_meta( $user_meta_uid, $pieces[1], true );
								$replaceable = is_email( $user_meta ) ? $user_meta : '';

								break;

							case 'USERMETA':
								$user_data = get_userdata( $user_meta_uid );
								$user_data = (array) $user_data->data;

								if ( isset( $user_data[ $pieces[1] ] ) ) {
									$replaceable = $user_data[ $pieces[1] ];
								} else {
									$user_meta   = get_user_meta( $user_meta_uid, $pieces[1], true );
									$replaceable = $user_meta;
								}

								if ( is_array( $replaceable ) ) {
									$replaceable = join( ', ', $replaceable );
								}

								$trigger_meta_key = $meta_key;
								$user_meta_key    = $pieces[1];

								$replaceable = apply_filters(
									'automator_usermeta_token_parsed',
									$replaceable,
									$user_meta_uid,
									$user_meta_key,
									$trigger_meta_key,
									$args,
									$trigger_args
								);

								break;

							default:
								$replace_args = array(
									'pieces'          => $pieces,
									'recipe_id'       => $recipe_id,
									'recipe_log_id'   => $recipe_log_id,
									'trigger_id'      => $trigger_id,
									'trigger_log_id'  => $trigger_log_id,
									'run_number'      => $run_number,
									'user_id'         => $user_meta_uid,
									'recipe_triggers' => array(),
								);

								if ( isset( $args['recipe_triggers'] ) ) {
									$replace_args['recipe_triggers'] = $args['recipe_triggers'];
								}

								$replaceable = $this->replace_recipe_variables( $replace_args, $trigger_args, $trigger_id );

								break;

						}
					}
					$field_text = apply_filters( 'automator_maybe_parse_field_text', $field_text, $match, $replaceable );
					$field_text = str_replace( '{{' . $match . '}}', $replaceable, $field_text );
				} else {
					/**
					 * This section is for non-usermeta via "else". However, this is actually the Trigger tokens section.
					 *
					 * @todo Refactor this condition to not rely on "else" it should have its own condition. Or should be the default one.
					 */
					$parse_args = array(
						'user_id'        => $user_id,
						'trigger_id'     => $trigger_id,
						'trigger_log_id' => $trigger_log_id,
						'run_number'     => $run_number,
					);

					$parsed_data = Automator()->db->token->get( 'parsed_data', $parse_args );
					$run_func    = true;

					if ( ! empty( $parsed_data ) ) {
						$parsed_data = maybe_unserialize( $parsed_data );
						if ( key_exists( '{{' . $match . '}}', $parsed_data ) && ! empty( $parsed_data[ '{{' . $match . '}}' ] ) ) {
							$replaceable = $parsed_data[ '{{' . $match . '}}' ];
							$run_func    = false;
						} else {
							$run_func = true;
						}
					}
					if ( empty( $replaceable ) ) {
						$run_func = true;
					}
					if ( $run_func ) {
						$pieces = explode( ':', $match );
						if ( $pieces ) {
							$replace_args = array(
								'pieces'          => $pieces,
								'recipe_id'       => $recipe_id,
								'recipe_log_id'   => $recipe_log_id,
								'trigger_id'      => $trigger_id,
								'trigger_log_id'  => $trigger_log_id,
								'run_number'      => $run_number,
								'user_id'         => $user_id,
								'recipe_triggers' => array(),
							);
							if ( isset( $args['recipe_triggers'] ) ) {
								$replace_args['recipe_triggers'] = $args['recipe_triggers'];
							}

							if ( isset( $trigger_args['loop'] ) && is_array( $trigger_args['loop'] ) ) {
								$replace_args['loop'] = $trigger_args['loop'];
							}

							$replaceable = $this->replace_recipe_variables( $replace_args, $trigger_args, $trigger_id );
						}
					}
				}
			} elseif ( in_array( $match, $this->defined_tokens, true ) ) {
				/**
				 * ☝️☝️☝️ This section of code is for the "Common tokens"
				 *
				 * @todo Refactor this IF condition because the primary IF condition is not related to the "elseif" at all.
				 *
				 * The $args['user_id'] is the user ID that is passed into the action.
				 * While $trigger_args['user_id'] is the user ID of the user who fired the Trigger.
				 */
				if ( null === $args['user_id'] ) {
					$current_user = wp_get_current_user();
				} else {
					$current_user = get_user_by( 'ID', $args['user_id'] );
				}

				switch ( $match ) {
					case 'recipe_total_run':
						$replaceable = Automator()->get->recipe_completed_times( $recipe_id );
						break;

					case 'recipe_run':
						$replaceable = $run_number;
						break;

					default:
						$replaceable = apply_filters( "automator_maybe_parse_{$match}", $replaceable, $field_text, $match, $current_user, $args );
						break;
				}
			}

			$replaceable = apply_filters( "automator_maybe_parse_{$match}", $replaceable, $field_text, $match, $user_id, $args );

			$replaceable = apply_filters( 'automator_maybe_parse_replaceable', $replaceable );

			/**
			 * Rare instance when an action token is not "yet" parsed Trigger tokens try to parse it.
			 *
			 * This occurs when there is a nested tokens, or a token inside a token.
			 *
			 * @since 5.0.1
			 */
			if ( false === strpos( $match, 'ACTION_META' ) ) {
				// Record the token raw vs replaceable with respect to $args for log details consumption.
				$parsed_tokens_record->record_token( '{{' . $match . '}}', $replaceable, $args );
			}

			$field_text = apply_filters( 'automator_maybe_parse_field_text', $field_text, $match, $replaceable, $args );

			/**
			 * @since 5.3 Parsing 3rd-party tokens here. Loop is considered as 3rd-party.
			 */
			if ( str_starts_with( $match, 'TOKEN_EXTENDED' ) ) {
				// Each token parts is separated by a ':' colon.
				$token_parts = (array) explode( ':', strtolower( $match ) );
				// We need to extract the first and second argument.
				list( $extended_flag, $extension_identifier ) = $token_parts;
				// Then use it as a filter so we dont have to check it. It is also safer.
				$field_text = apply_filters( "automator_token_parser_extended_{$extension_identifier}", $field_text, $match, $args, $trigger_args );
			}

			$field_text = str_replace( '{{' . $match . '}}', $replaceable, $field_text );

		} // End foreach.

		// Only replace open/close curly brackets if it's {{TOKEN}} style structure.
		// This avoids the erroneous replacement of the JSON closing brackets.
		// Example a:2:{i:0;s:12:"Sample array";i:1;a:2:{i:0;s:5:"Apple";i:1;s:6:"Orange";}}
		// changing to a:2:{i:0;s:12:"Sample array";i:1;a:2:{i:0;s:5:"Apple";i:1;s:6:"Orange";
		return preg_replace( '/({{(.+?)}})/', '$2', $field_text );
	}

	/**
	 * @param $replace_args
	 * @param array $args
	 * @param int $source_trigger_id
	 *
	 * @return string
	 */
	public function replace_recipe_variables( $replace_args, $args = array(), $source_trigger_id = 0 ) {
		$pieces    = $this->sanitize_token_pieces( $this->parse_inner_token( $replace_args['pieces'], $replace_args ) );
		$recipe_id = $this->get_recipe_id( $args );

		/**
		 * Global tokens do not have Trigger ID. (e.g. POSTMETA:<POST_ID>:<POST_META>)
		 **/
		$trigger_id = absint( $pieces[0] );

		/**
		 * Skips processing for recipe trigger logic: `any` if the source trigger ID does not match the token's ID
		 *
		 * Continues the process when the token has no trigger ID.
		 */
		if ( $this->should_bail_for_logic_any( $trigger_id, $source_trigger_id ) ) {
			return null;
		}

		$trigger_log_id = $this->get_trigger_log_id( $replace_args, $trigger_id );
		$run_number     = $this->get_run_number( $replace_args );
		$user_id        = $this->get_user_id( $replace_args );
		$trigger        = Automator()->get_trigger_data( $recipe_id, $trigger_id );
		$trigger_data   = array( $trigger );
		$return         = '';

		// save trigger ID in the $replace_args
		$replace_args['trigger_id']     = $trigger_id;
		$replace_args['trigger_log_id'] = $trigger_log_id;

		if ( is_null( $user_id ) && 0 !== absint( $user_id ) ) {
			$user_id = wp_get_current_user()->ID;
		}
		foreach ( $pieces as $piece ) {
			$is_relevant_token = false;
			if ( strpos( $piece, '_ID' ) !== false
				|| strpos( $piece, '_URL' ) !== false
				|| strpos( $piece, '_EXCERPT' ) !== false
				|| strpos( $piece, '_THUMB_URL' ) !== false
				|| strpos( $piece, '_THUMB_ID' ) !== false ) {
				$is_relevant_token = true;
				$sub_piece         = explode( '_', $piece, 2 );
				$piece             = $sub_piece[0];
			}

			if ( ! isset( $trigger['meta'] ) ) {
				continue;
			}
			if ( ! key_exists( $piece, $trigger['meta'] ) && 'NUMTIMES' !== $piece ) {
				continue;
			}
			/**
			 * Added fallback 1 if NUMTIMES is not set.
			 * See issue #1914 any page and any post trigger
			 *
			 * @updated v4.0 by Saad
			 */
			if ( 'NUMTIMES' === (string) $piece ) {
				$return = isset( $trigger['meta'][ $piece ] ) && ! empty( $trigger['meta'][ $piece ] ) ? $trigger['meta'][ $piece ] : 1;
			} elseif ( is_numeric( $trigger['meta'][ $piece ] ) ) {
				if ( intval( '-1' ) === intval( $trigger['meta'][ $piece ] ) ) {
					$post_id = Automator()->db->trigger->get_token_meta( $piece, $replace_args );
				} else {
					$post_id = $trigger['meta'][ $piece ];
				}

				switch ( $piece ) {
					case 'WPPOST':
					case 'WPPAGE':
						if ( isset( $sub_piece ) && key_exists( 1, $sub_piece ) ) {
							if ( 'ID' === $sub_piece[1] ) {
								$return = $post_id;
							} elseif ( 'URL' === $sub_piece[1] ) {
								$return = get_permalink( $post_id );
							} elseif ( 'THUMB_URL' === $sub_piece[1] ) {
								$return = get_the_post_thumbnail_url( $post_id, 'full' );
							} elseif ( 'THUMB_ID' === $sub_piece[1] ) {
								$return = get_post_thumbnail_id( $post_id );
							} elseif ( 'EXCERPT' === $sub_piece[1] ) {
								$return = Automator()->utilities->automator_get_the_excerpt( $post_id );
							}
						} else {
							$return = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
						}
						break;
					case 'NUMTIMES':
						/**
						 * Added fallback 1 if NUMTIMES is not set.
						 * See issue #1914 any page and any post trigger
						 *
						 * @updated v4.0 by Saad
						 */
						$return = isset( $trigger['meta'][ $piece ] ) && ! empty( $trigger['meta'][ $piece ] ) ? $trigger['meta'][ $piece ] : 1;
						break;
					case 'WPUSER':
						$user_id = absint( $trigger['meta'][ $piece ] );
						$user    = get_user_by( 'ID', $user_id );
						if ( $user ) {
							$return = $user->user_email;
						} else {
							$return = '';
						}
						break;
					default:
						if ( intval( '-1' ) === intval( $trigger['meta'][ $piece ] ) ) {
							//Find stored post_id for piece, i.e., LDLESSON, LDTOPIC set to Any
							if ( is_numeric( $post_id ) ) {
								if ( $is_relevant_token ) {
									if ( 'ID' === $sub_piece[1] ) {
										$return = $post_id;
									} elseif ( 'URL' === $sub_piece[1] ) {
										$return = get_the_permalink( $post_id );
									} elseif ( 'THUMB_URL' === $sub_piece[1] ) {
										$return = get_the_post_thumbnail_url( $post_id, 'full' );
									} elseif ( 'THUMB_ID' === $sub_piece[1] ) {
										$return = get_post_thumbnail_id( $post_id );
									} elseif ( 'EXCERPT' === $sub_piece[1] ) {
										$return = Automator()->utilities->automator_get_the_excerpt( $post_id );
									}
								} else {
									$return = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
								}
							} else {
								/* translators: Article. Fallback. Any type of content (post, page, media, etc) */
								$return = esc_attr__( 'Any', 'uncanny-automator' );
							}
						} elseif ( ! preg_match( '/ANON/', $piece ) ) {
							if ( $is_relevant_token ) {
								if ( 'ID' === $sub_piece[1] ) {
									$return = $trigger['meta'][ $piece ];
								} elseif ( 'URL' === $sub_piece[1] ) {
									$return = get_the_permalink( $trigger['meta'][ $piece ] );
								} elseif ( 'THUMB_URL' === $sub_piece[1] ) {
									$return = get_the_post_thumbnail_url( $trigger['meta'][ $piece ], 'full' );
								} elseif ( 'THUMB_ID' === $sub_piece[1] ) {
									$return = get_post_thumbnail_id( $trigger['meta'][ $piece ] );
								} elseif ( 'EXCERPT' === $sub_piece[1] ) {
									$return = Automator()->utilities->automator_get_the_excerpt( $trigger['meta'][ $piece ] );
								}
							} else {
								$return = html_entity_decode( get_the_title( $trigger['meta'][ $piece ] ), ENT_QUOTES, 'UTF-8' );
							}
						} else {
							$return = '';
						}
						break;
				}
			} else {
				//Non numeric data.. passed custom post type
				switch ( $piece ) {
					case 'WPPOSTTYPES':
						$return = key_exists( 'post_type_label', $args ) && ! empty( $args['post_type_label'] ) ? $args['post_type_label'] : '';
						break;
				}
			}
		}
		/**
		 * Added POSTMETA token type
		 *
		 * @since 3.5
		 */
		if ( in_array( 'POSTMETA', $pieces, true ) ) {

			// Postmeta token found.
			$post_id  = $pieces[1];
			$meta_key = $pieces[2];

			$return = get_post_meta( $post_id, $meta_key, true );

			if ( is_array( $return ) ) {
				$return = join( ', ', $return );
			}
		}

		$return = $this->v3_parser( $return, $replace_args, $args );

		if ( isset( $args['loop'] ) && is_array( $args['loop'] ) ) {
			$replace_args['loop'] = $args['loop'];
		}

		$return = apply_filters( 'automator_maybe_parse_token', $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );

		/**
		 * Filter automator_parse_token_for_trigger_{{integration}}_{{trigger_code}}
		 *
		 * {{integration}} - The integration name of the trigger you are attaching the tokens into.
		 * {{trigger_code}}- The trigger code of the trigger you are attaching the tokens into.
		 *
		 * @param mixed $return The current return value.
		 * @param array $pieces The current token pieces.
		 * @param int $recipe_id The current recipe ID.
		 * @param array $trigger_data The data of the current trigger.
		 * @param int $user_id The ID of the user.
		 * @param array $replace_args The replacement arguments.
		 *
		 * @since 4.3
		 */
		if ( ! empty( $trigger['meta']['code'] ) && ! empty( $trigger['meta']['integration'] ) ) {

			$filter = strtr(
				'automator_parse_token_for_trigger_{{integration}}_{{trigger_code}}',
				array(
					'{{integration}}'  => strtolower( $trigger['meta']['integration'] ),
					'{{trigger_code}}' => strtolower( $trigger['meta']['code'] ),
				)
			);

			$return = apply_filters(
				$filter,
				$return,
				$pieces,
				$recipe_id,
				$trigger_data,
				$user_id,
				$replace_args
			);

		}

		// Handle fatal error if in case the data ends up being an Array
		if ( ! is_array( $return ) ) {
			/**
			 * Maybe run a do_shortcode on the field itself if it contains a shortcode?
			 *
			 * @ticket #2255
			 * @since 3.0
			 */

			if ( apply_filters(
				'automator_replace_recipe_variables_do_shortcode',
				true,
				$return,
				$pieces,
				$recipe_id,
				$trigger_data,
				$user_id,
				$replace_args
			)
			) {
				$return = do_shortcode( $return );
			}

			return $return;
		}

		// Handle Array if in case the data ends up being an Array (Edge cases)
		return join( ', ', $return );
	}

	/**
	 * Sanitize pieces. No piece should contain {{ or }} in the value to avoid
	 * following situation
	 * SELECT meta_value FROM wp_uap_trigger_log_meta
	 * WHERE meta_key = 'ANONWPFFFORMS' AND automator_trigger_log_id = 1009
	 * AND automator_trigger_id = {{8347
	 * LIMIT 0, 1
	 *
	 * @since v4.2.1+
	 */
	public function sanitize_token_pieces( $pieces = array() ) {

		$pieces = array_map(
			function ( $piece ) {
				return str_replace( array( '{', '}' ), '', $piece );
			},
			$pieces
		);

		return $pieces;

	}

	/**
	 * @param $return
	 * @param $replace_args
	 * @param array $args
	 *
	 * @return false|mixed
	 */
	public function v3_parser( $return, $replace_args, $args = array() ) {
		$pieces     = $this->parse_inner_token( $replace_args['pieces'], $args );
		$recipe_id  = $replace_args['recipe_id'];
		$trigger_id = absint( $pieces[0] );
		$trigger    = Automator()->get_trigger_data( $recipe_id, $trigger_id );
		if ( empty( $trigger ) ) {
			return $return;
		}
		$trigger_code = $trigger['meta']['code'];
		$token_parser = Automator()->get->value_from_trigger_meta( $trigger['meta']['code'], 'token_parser' );

		if ( ! empty( $token_parser ) ) {
			$token_args = array(
				'trigger_code' => $trigger_code,
				'replace_args' => $replace_args,
				'args'         => $args,
			);

			return call_user_func( $token_parser, $return, $token_args );
		}

		return $return;
	}

	/**
	 * Generates reset password URL from token.
	 *
	 * @param int $user_id
	 *
	 * @return string Returns empty string if provided $user_id is not found.
	 */
	public function reset_password_url_token( $user_id = 0 ) {

		$user = get_user_by( 'ID', $user_id );

		if ( false !== $user && $user instanceof \WP_User ) {

			return add_query_arg(
				array(
					'action' => 'rp',
					'key'    => get_password_reset_key( $user ),
					'login'  => $user->user_login,
				),
				wp_login_url()
			);

		}

		return ''; // Returns empty string if user is not valid.

	}

	/**
	 * Generates reset password HTML link.
	 *
	 * @param int $user_id
	 *
	 * @see automator_token_reset_password_link_html
	 *
	 * @return string
	 */
	public function generate_reset_token( $user_id = 0 ) {

		$text = esc_attr_x( 'Click here to reset your password.', 'Reset password token text', 'uncanny-automator' );

		$reset_pw_url = $this->reset_password_url_token( $user_id );

		return apply_filters(
			'automator_token_reset_password_link_html',
			'<a href="' . esc_url( $reset_pw_url ) . '" title="' . esc_attr( $text ) . '">'
				. esc_html( $text )
			. '</a>',
			$user_id
		);

	}

	/**
	 * @param null $field_text
	 * @param null $recipe_id
	 * @param null $user_id
	 *
	 * @param null $args
	 *
	 * @return null|string
	 */
	public function text( $field_text = null, $recipe_id = null, $user_id = null, $trigger_args = null ) {

		// Sanity check that there was a $field_text passed
		if ( null === $field_text ) {
			return null;
		}

		$args = array(
			'field_text'  => $field_text,
			'meta_key'    => null,
			'user_id'     => $user_id,
			'action_data' => isset( $trigger_args['action_meta'] ) ? $trigger_args['action_meta'] : null,
			'recipe_id'   => $recipe_id,
		);

		$args['field_text'] = apply_filters( 'automator_action_token_input_parser_text_field_text', $args['field_text'], $args, $trigger_args );

		if ( ! empty( $trigger_args['trigger_log_id'] ) ) {
			$args['trigger_log_id'] = $trigger_args['trigger_log_id'];
		}
		if ( ! empty( $trigger_args['run_number'] ) ) {
			$args['run_number'] = $trigger_args['run_number'];
		}
		if ( ! empty( $trigger_args['recipe_log_id'] ) ) {
			$args['recipe_log_id'] = $trigger_args['recipe_log_id'];
		}
		if ( ! empty( $trigger_args['trigger_id'] ) ) {
			$args['trigger_id'] = $trigger_args['trigger_id'];
		}
		if ( isset( $trigger_args['recipe_triggers'] ) ) {
			$args['recipe_triggers'] = $trigger_args['recipe_triggers'];
		}
		if ( isset( $trigger_args['action_meta'] ) ) {
			$args['action_meta'] = $trigger_args['action_meta'];
		}

		$field_text = apply_filters( 'automator_text_field_parsed', $this->parse_vars( $args, $trigger_args ), $args );

		return $this->maybe_parse_shortcodes_in_fields( $field_text, $recipe_id, $user_id, $args );
	}

	/**
	 * @param $field_text
	 * @param $recipe_id
	 * @param $user_id
	 * @param $args
	 *
	 * @return mixed|string|null
	 */
	public function maybe_parse_shortcodes_in_fields( $field_text, $recipe_id = null, $user_id = null, $args = array() ) {

		$skip_do_shortcode_actions = apply_filters(
			'automator_skip_do_shortcode_parse_in_fields',
			array(
				'CREATEPOST',
				'BULKUPDATE_CODE',
			)
		);

		$action_meta_code = isset( $args['action_meta'] ) && isset( $args['action_meta']['code'] ) ? $args['action_meta']['code'] : '';
		if ( true === apply_filters( 'automator_skip_cslashing_value', false, $field_text, $action_meta_code, $recipe_id, $args ) ) {
			return $field_text;
		}

		// If filter is set to true OR action meta matches
		if ( in_array( $action_meta_code, $skip_do_shortcode_actions, true ) || true === apply_filters( 'automator_skip_do_action_field_parsing', $field_text, $recipe_id, $user_id, $args ) ) {
			// The function stripcslashes preserves the \a, \b, \f, \n, \r, \t and \v characters.
			return apply_filters( 'automator_parse_token_parse_text', stripcslashes( $field_text ), $field_text, $args );
		}

		/**
		 * May be run a do_shortcode on the field itself if it contains a shortcode?
		 * Ticket# 22255
		 *
		 * @since 3.0
		 */
		return do_shortcode( apply_filters( 'automator_parse_token_parse_text', stripcslashes( $field_text ), $field_text, $args ) );
	}

	/**
	 * This function parses inner token(s) {{POSTMETA:[[TOKEN]]:meta_key}} and
	 * replace its value in actual token
	 *
	 * @param $pieces
	 * @param $args
	 *
	 * @return mixed
	 * @sinc 3.5
	 */
	public function parse_inner_token( $pieces, $args ) {
		if ( empty( $pieces ) ) {
			return $pieces;
		}
		$pieces = $this->parse_inner_token_post_id_part( $pieces, $args );
		$pieces = $this->parse_inner_token_meta_key_part( $pieces, $args );

		return $pieces;
	}

	/**
	 * This function parses "post ID" part of inner token
	 * {{POSTMETA:[[TOKEN]]:[[meta_key]]}} and replace its value in actual
	 * token
	 *
	 * @param $pieces
	 * @param $args
	 *
	 * @return mixed
	 */
	public function parse_inner_token_post_id_part( $pieces, $args ) {
		if ( ! array_key_exists( 1, $pieces ) ) {
			return $pieces;
		}
		if ( ! preg_match( '/\[\[(.+)\]\]/', $pieces[1], $arr ) ) {
			return $pieces;
		}
		$recipe_id    = $args['recipe_id'];
		$user_id      = $args['user_id'];
		$trigger_args = $args;
		unset( $trigger_args['pieces'] );
		$token     = str_replace(
			array( '[', ']', ';' ),
			array(
				'{',
				'}',
				':',
			),
			$arr[0]
		);
		$parsed    = $this->text( $token, $recipe_id, $user_id, $trigger_args );
		$pieces[1] = apply_filters( 'automator_parse_inner_token', $parsed, $token, $pieces, $args );

		return $pieces;
	}

	/**
	 * This function parses "meta_key" part of inner token
	 * {{POSTMETA:[[TOKEN]]:[[meta_key]]}} and replace its value in actual
	 * token
	 *
	 * @param $pieces
	 * @param $args
	 *
	 * @return mixed
	 */
	public function parse_inner_token_meta_key_part( $pieces, $args ) {
		if ( ! array_key_exists( 2, $pieces ) ) {
			return $pieces;
		}
		if ( ! preg_match( '/\[\[(.+)\]\]/', $pieces[2], $arr ) ) {
			return $pieces;
		}
		$recipe_id    = $args['recipe_id'];
		$user_id      = $args['user_id'];
		$trigger_args = $args;
		unset( $trigger_args['pieces'] );
		$token     = str_replace(
			array( '[', ']', ';' ),
			array(
				'{',
				'}',
				':',
			),
			$arr[0]
		);
		$parsed    = $this->text( $token, $recipe_id, $user_id, $trigger_args );
		$pieces[2] = apply_filters( 'automator_parse_inner_token', $parsed, $token, $pieces, $args );

		return $pieces;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed|string
	 */
	public function automator_maybe_parse_postmeta_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( ! in_array( 'POSTMETA', $pieces, true ) ) {
			return $value;
		}
		$pieces  = $this->parse_inner_token( $pieces, $replace_args );
		$post_id = isset( $pieces[1] ) ? absint( $pieces[1] ) : null;
		if ( null === $post_id ) {
			return $value;
		}
		$meta_key = sanitize_text_field( $pieces[2] );
		$value    = get_post_meta( $post_id, $meta_key, true );
		if ( is_array( $value ) ) {
			$value = join( ', ', $value );
		}

		return apply_filters(
			'automator_postmeta_token_parsed',
			$value,
			$post_id,
			$meta_key,
			array(
				'value'        => $value,
				'pieces'       => $pieces,
				'recipe_id'    => $recipe_id,
				'trigger_data' => $trigger_data,
				'user_id'      => $user_id,
				'replace_args' => $replace_args,
			)
		);
	}

	/**
	 * Determines whether should bail processing for `any` logic types of triggers.
	 *
	 * @param int $trigger_id The token's trigger ID.
	 * @param int $source_trigger_id The token's trigger `source`. The ID of the trigger where the token is processed for.
	 *
	 * @return boolean True if should bail. Otherwise, false.
	 */
	protected function should_bail_for_logic_any( $trigger_id = 0, $source_trigger_id = 0 ) {

		/**
		 * Account for tokens that has no Trigger ID. (e.g POSTMETA:<POST_ID>:<META_KEY>)
		 *
		 * Global tokens should be parsed regardless whether they are coming from Trigger or not,
		 * and they should be parse regardless of Any or And.
		 *
		 * Return false immediately if that is the case.
		 */
		if ( 0 === $trigger_id ) {
			return false;
		}

		// Determines whether the token's trigger ID matches the 'source' trigger ID.
		$is_source_trigger_matches_token_trigger = ( $trigger_id === $source_trigger_id );

		// Determines whether the recipe trigger token matches 'any'.
		$is_recipe_logic_any = 'any' === Automator()->db->trigger->get_recipe_triggers_logic_by_child_id( $source_trigger_id );

		// Return true if source trigger does not match the token's trigger ID `and` recipe logic is equals to `any`.
		return ! $is_source_trigger_matches_token_trigger && $is_recipe_logic_any;

	}

	/**
	 * Attach the trigger token hooks.
	 *
	 * @param array $action The action array.
	 *
	 * @return void.
	 */
	public function attach_trigger_tokens_hook( $action ) {

		$code = isset( $action['args']['code'] ) ? $action['args']['code'] : '';

		if ( empty( $code ) ) {
			return;
		}

		$filter = strtr(
			'automator_parse_token_for_trigger_{{integration}}_{{trigger_code}}',
			array(
				'{{integration}}'  => strtolower( Automator()->get->value_from_trigger_meta( $code, 'integration' ) ),
				'{{trigger_code}}' => strtolower( $code ),
			)
		);

		// Get the token value when `automator_parse_token_for_trigger_{{integration}}_{{trigger_code}}`.
		add_filter( $filter, array( $this, 'fetch_trigger_tokens' ), 20, 6 );

		return $action;

	}

	/**
	 * This method was copied from the Trigger_Tokens Traits.
	 *
	 * @return string The token value.
	 * @todo Move this method to a separate class or function for reusability. E.g Uncanny_Automator\Trigger\Token_Handler::fetch_trigger_tokens()
	 */
	public function fetch_trigger_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_arg ) {

		if ( empty( $trigger_data ) || ! isset( $trigger_data[0] ) ) {
			return $value;
		}

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		// Assign the $pieces indexes to their respective variables.
		list( $recipe_id, $token_identifier, $token_id ) = $pieces;

		$data = Automator()->db->token->get( $token_identifier, $replace_arg );
		$data = is_array( $data ) ? $data : json_decode( $data, true );

		if ( isset( $data[ $token_id ] ) ) {
			return $data[ $token_id ];
		}

		return $value;

	}

}
