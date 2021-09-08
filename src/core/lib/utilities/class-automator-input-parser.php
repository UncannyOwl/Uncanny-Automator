<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Input_Parser
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
				'site_name',
				'user_id',
				'user_username',
				'user_firstname',
				'user_lastname',
				'user_email',
				'user_displayname',
				'reset_pass_link',
				'admin_email',
				'site_url',
				'recipe_name',
				'current_date',
				'current_time',
			)
		);
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
		$args = [
			'field_text'  => $url,
			'meta_key'    => null,
			'user_id'     => null,
			'action_data' => null,
			'recipe_id'   => $recipe_id,
		];
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
			$url = '{{site_url}}' . '/' . $url;
			// Replace Tokens
			$args = [
				'field_text'  => $url,
				'meta_key'    => null,
				'user_id'     => null,
				'action_data' => null,
				'recipe_id'   => $recipe_id,
			];

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
	 * Parse field text by replacing variable with real data
	 *
	 * @param $args
	 * @param $trigger_args
	 *
	 * @return string
	 */
	public function parse_vars( $args, $trigger_args = array() ) {
		$field_text     = $args['field_text'];
		$meta_key       = $args['meta_key'];
		$user_id        = $args['user_id'];
		$action_data    = $args['action_data'];
		$recipe_id      = $args['recipe_id'];
		$trigger_log_id = array_key_exists( 'trigger_log_id', $args ) ? absint( $args['trigger_log_id'] ) : null;
		$run_number     = array_key_exists( 'run_number', $args ) ? absint( $args['run_number'] ) : null;
		$recipe_log_id  = array_key_exists( 'recipe_log_id', $args ) ? absint( $args['recipe_log_id'] ) : null;
		$trigger_id     = array_key_exists( 'trigger_id', $args ) ? absint( $args['trigger_id'] ) : null;

		// find brackets and replace with real data
		preg_match_all( '/{{\s*(.*?)\s*}}/', $field_text, $arr );
		if ( empty( $arr ) ) {
			return str_replace( array( '{{', '}}' ), '', $field_text );
		}

		$matches = $arr[1];
		foreach ( $matches as $match ) {

			$replaceable = '';

			if ( false !== strpos( $match, ':' ) ) {
				if ( preg_match( '/(USERMETA)/', $match ) ) {
					//Usermeta found!!
					if ( is_null( $user_id ) && 0 !== absint( $user_id ) ) {
						$user_id = wp_get_current_user()->ID;
					}
					if ( 0 !== $user_id ) {
						$pieces = explode( ':', $match );
						switch ( $pieces[0] ) {
							case 'USERMETAEMAIL':
								$user_meta = get_user_meta( $user_id, $pieces[1], true );

								$replaceable = is_email( $user_meta ) ? $user_meta : '';
								break;
							case 'USERMETA':
								$user_data = get_userdata( $user_id );
								$user_data = (array) $user_data->data;
								if ( isset( $user_data[ $pieces[1] ] ) ) {
									$replaceable = $user_data[ $pieces[1] ];
								} else {
									$user_meta   = get_user_meta( $user_id, $pieces[1], true );
									$replaceable = $user_meta;
								}
								break;
						}
					}
					$field_text = apply_filters( 'automator_maybe_parse_field_text', $field_text, $match, $replaceable );
					$field_text = str_replace( $match, $replaceable, $field_text );
				} else {
					//Non usermeta
					global $wpdb;
					$qq          = "SELECT meta_value
										FROM {$wpdb->prefix}uap_trigger_log_meta
										WHERE 1=1
										  AND meta_key = %s
										  AND automator_trigger_log_id = %d
										  AND user_id = %d
										  AND run_number = %d";
					$qq          = $wpdb->prepare( $qq, 'parsed_data', $trigger_log_id, $user_id, $run_number );
					$parsed_data = $wpdb->get_var( $qq );
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
								'pieces'         => $pieces,
								'recipe_id'      => $recipe_id,
								'recipe_log_id'  => $recipe_log_id,
								'trigger_id'     => $trigger_id,
								'trigger_log_id' => $trigger_log_id,
								'run_number'     => $run_number,
								'user_id'        => $user_id,
							);

							$replaceable = $this->replace_recipe_variables( $replace_args, $trigger_args );
						}
					}
				}
			} elseif ( in_array( $match, $this->defined_tokens, true ) ) {
				if ( null === $user_id ) {
					$current_user = wp_get_current_user();
				} else {
					$current_user = get_user_by( 'ID', $user_id );
				}

				switch ( $match ) {
					case 'site_name':
						$replaceable = get_bloginfo( 'name' );
						break;

					case 'user_username':
						$replaceable = isset( $current_user->user_login ) ? $current_user->user_login : '';
						break;

					case 'user_id':
						$replaceable = isset( $current_user->ID ) ? $current_user->ID : 0;
						break;

					case 'user_firstname':
						$replaceable = isset( $current_user->first_name ) ? $current_user->first_name : '';
						break;

					case 'user_lastname':
						$replaceable = isset( $current_user->last_name ) ? $current_user->last_name : '';
						break;

					case 'user_email':
						$replaceable = isset( $current_user->user_email ) ? $current_user->user_email : '';
						break;

					case 'user_displayname':
						$replaceable = isset( $current_user->display_name ) ? $current_user->display_name : '';
						break;

					case 'reset_pass_link':
						$replaceable = $this->generate_reset_token( $user_id );
						break;

					case 'admin_email':
						$replaceable = get_bloginfo( 'admin_email' );
						break;

					case 'site_url':
						$replaceable = get_site_url();
						break;

					case 'current_date':
						if ( function_exists( 'wp_date' ) ) {
							$replaceable = wp_date( get_option( 'date_format' ) );
						} else {
							$replaceable = date_i18n( get_option( 'date_format' ) );
						}

						break;

					case 'current_time':
						if ( function_exists( 'wp_date' ) ) {
							$replaceable = wp_date( get_option( 'time_format' ) );
						} else {
							$replaceable = date_i18n( get_option( 'time_format' ) );
						}

						break;

					case 'recipe_name':
						$recipe = get_post( $recipe_id );
						if ( null !== $recipe ) {
							$replaceable = $recipe->post_title;
						}
						break;
					default:
						$replaceable = apply_filters( "automator_maybe_parse_{$match}", $replaceable, $field_text, $match, $current_user );
						break;
				}
			}

			$replaceable = apply_filters( "automator_maybe_parse_{$match}", $replaceable, $field_text, $match, $user_id );
			$field_text  = apply_filters( 'automator_maybe_parse_field_text', $field_text, $match, $replaceable );
			$field_text  = str_replace( '{{' . $match . '}}', $replaceable, $field_text );
		}


		return str_replace( [ '{{', '}}' ], '', $field_text );
	}

	/**
	 * @param $replace_args
	 * @param $args
	 *
	 * @return string
	 */
	public function replace_recipe_variables( $replace_args, $args = array() ) {
		$pieces         = $replace_args['pieces'];
		$recipe_id      = $replace_args['recipe_id'];
		$trigger_log_id = $replace_args['trigger_log_id'];
		$run_number     = $replace_args['run_number'];
		$user_id        = $replace_args['user_id'];
		$trigger_id     = absint( $pieces[0] );
		$trigger        = Automator()->get_trigger_data( $recipe_id, $trigger_id );
		$trigger_data   = array( $trigger );
		$return         = '';

		// save trigger ID in the $replace_args
		$replace_args['trigger_id'] = $trigger_id;

		if ( is_null( $user_id ) && 0 !== absint( $user_id ) ) {
			$user_id = wp_get_current_user()->ID;
		}

		foreach ( $pieces as $piece ) {
			$is_relevant_token = false;
			if ( strpos( $piece, '_ID' ) !== false ||
			     strpos( $piece, '_URL' ) !== false ||
			     strpos( $piece, '_THUMB_URL' ) !== false ||
			     strpos( $piece, '_THUMB_ID' ) !== false ) {
				$is_relevant_token = true;
				$sub_piece         = explode( '_', $piece, 2 );
				$piece             = $sub_piece[0];
			}

			if ( ! isset( $trigger['meta'] ) ) {
				continue;
			}
			if ( ! key_exists( $piece, $trigger['meta'] ) ) {
				continue;
			}
			if ( is_numeric( $trigger['meta'][ $piece ] ) ) {

				if ( intval( '-1' ) === intval( $trigger['meta'][ $piece ] ) ) {
					$post_id = Automator()->get->maybe_get_meta_value_from_trigger_log( $piece, $trigger_id, $trigger_log_id, $run_number, $user_id );
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
							}
						} else {
							$return = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
						}
						break;
					case 'NUMTIMES':
						$return = $trigger['meta'][ $piece ];
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
		$return = $this->v3_parser( $return, $replace_args, $args );

		$return = apply_filters( 'automator_maybe_parse_token', $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );

		/*
		 * May be run a do_shortcode on the field itself if it contains a shortcode?
		 * Ticket# 22255
		 * @since 3.0
		 */

		return do_shortcode( $return );
	}

	/**
	 * @param $return
	 * @param $replace_args
	 * @param array $args
	 *
	 * @return false|mixed
	 */
	public function v3_parser( $return, $replace_args, $args = array() ) {
		$pieces     = $replace_args['pieces'];
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
	 * @param $user_id
	 *
	 * @return bool|string
	 */
	public function generate_reset_token( $user_id ) {

		$user = get_user_by( 'ID', $user_id );
		if ( $user ) {
			$adt_rp_key = get_password_reset_key( $user );
			$user_login = $user->user_login;
			$url        = network_site_url( "wp-login.php?action=rp&key=$adt_rp_key&login=" . rawurlencode( $user_login ), 'login' );
			$text       = esc_attr__( 'Click here to reset your password.', 'uncanny-automator' );
			$rp_link    = sprintf( '<a href="%s">%s</a>', $url, $text );
		} else {
			$rp_link = '';
		}

		return $rp_link;

	}

	/**
	 * @param null $field_text
	 * @param null $recipe_id
	 * @param null $user_id
	 *
	 * @param null $trigger_args
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

		$return = apply_filters( 'automator_text_field_parsed', $this->parse_vars( $args, $trigger_args ), $args );

		/**
		 * May be run a do_shortcode on the field itself if it contains a shortcode?
		 * Ticket# 22255
		 * @since 3.0
		 */
		return do_shortcode( stripslashes( $return ) );
	}

}
