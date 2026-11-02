<?php

namespace Uncanny_Automator;

/**
 * Class Populate_From_Query.
 *
 * Partner quick-create entry: turns URL query params on a freshly created
 * recipe (post-new.php quick links) into a typed recipe with one trigger.
 * Parses and authorizes the request, then delegates every recipe mutation
 * to the src/app framework via automator_prepopulate_recipe().
 *
 * @package Uncanny_Automator
 */
class Populate_From_Query {

	/**
	 * WP Post object.
	 *
	 * @var \WP_Post
	 */
	static $post;

	/**
	 * Nonce name.
	 *
	 * @var string
	 */
	static $nonce = 'Uncanny Automator';

	/**
	 * Figure out if this post needs to be populated from the query params.
	 *
	 * @param $post_id
	 * @param \WP_Post $post
	 * @param $update
	 *
	 * @return bool
	 */
	public static function maybe_populate( $post_id, $post, $update ) {

		self::init( $post );

		try {
			if ( self::is_new_recipe() ) {
				if ( self::query_args_exist( array( 'action', 'nonce' ) ) ) {
					if ( self::is_authorized() ) {
						return self::populate();
					}
				}

				return false;
			}
		} catch ( \Exception $e ) {
			Automator()->wp_error->add_error( 555, 'Uncanny_Automator\Populate_From_Query: ' . $e->getMessage() );
		}

		return true;
	}

	/**
	 * Initialize class, populate the variables from the hook call.
	 *
	 * @param \WP_Post $post
	 */
	public static function init( $post ) {

		self::$post = $post;
	}

	/**
	 * Check if the post is an Automator recipe and is newly created.
	 *
	 * @return bool
	 */
	protected static function is_new_recipe() {

		if ( AUTOMATOR_POST_TYPE_RECIPE !== self::$post->post_type ) {

			return false;
		}

		if ( 'auto-draft' !== self::$post->post_status ) {

			return false;
		}

		return true;
	}

	/**
	 * Check if query arguments exist in the GET array.
	 *
	 * @param $args
	 *
	 * @return bool
	 */
	protected static function query_args_exist( $args ) {

		foreach ( $args as $arg ) {

			if ( ! automator_filter_has_var( $arg ) ) {

				return false;
			}
		}

		return true;
	}

	/**
	 * Checks the nonce and capabilities.
	 *
	 * The nonce uses the partner-facing action string (self::$nonce). The
	 * capability check uses the modern seam: automator_get_capability()
	 * plus the automator_save_setting_permissions filter. Unlike the
	 * legacy flow this returns a strict boolean, so a capability failure
	 * actually blocks population.
	 *
	 * @return bool
	 * @throws Automator_Exception
	 */
	private static function is_authorized() {

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), self::$nonce ) ) {

			throw new Automator_Exception( 'Invalid nonce.' );

		}

		if ( ! current_user_can( automator_get_capability() ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from filter.

			return false;
		}

		return (bool) apply_filters( 'automator_save_setting_permissions', true );
	}

	/**
	 * Populates the recipe.
	 *
	 * @return bool
	 * @throws Automator_Exception
	 */
	protected static function populate() {

		switch ( automator_filter_input( 'action' ) ) {

			case 'add-new-trigger':
				if ( self::query_args_exist( array( 'item_code' ) ) ) {

					return self::add_new_trigger();

				} else {

					throw new Automator_Exception( 'Missing item code.' );

				}

				break;

			default:
				throw new Automator_Exception( 'Unknown action.' );

				break;
		}
	}

	/**
	 * Creates a trigger through the src/app framework.
	 *
	 * Builds the normalized parameter map from the query string and hands
	 * it to automator_prepopulate_recipe(). The legacy `integration`
	 * query param is intentionally ignored — the trigger registry
	 * resolves the integration from the trigger code.
	 *
	 * @return bool
	 * @throws Automator_Exception
	 */
	public static function add_new_trigger() {

		$params = array(
			'trigger_code' => automator_filter_input( 'item_code' ),
			'is_anon'      => automator_filter_has_var( 'is_anon' ),
		);

		if ( self::query_args_exist( array( 'optionCode', 'optionValue', 'optionValue_readable' ) ) ) {
			$params['option_code']           = automator_filter_input( 'optionCode' );
			$params['option_value']          = automator_filter_input( 'optionValue' );
			$params['option_value_readable'] = urldecode( automator_filter_input( 'optionValue_readable' ) );
		}

		$trigger_id = automator_prepopulate_recipe( (int) self::$post->ID, $params );

		if ( is_wp_error( $trigger_id ) ) {
			throw new Automator_Exception( esc_html( $trigger_id->get_error_message() ) );
		}

		// add do_action for external hooks
		do_action_deprecated(
			'uap_trigger_populated_from_query',
			array(
				self::$post,
				$trigger_id,
				$_GET, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			),
			'3.0',
			'automator_trigger_populated_from_query'
		);
		do_action( 'automator_trigger_populated_from_query', self::$post, $trigger_id, $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return true;
	}
}
