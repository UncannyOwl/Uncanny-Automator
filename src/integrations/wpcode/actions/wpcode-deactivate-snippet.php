<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class WPCODE_DEACTIVATE_SNIPPET
 *
 * @package Uncanny_Automator
 */
class WPCODE_DEACTIVATE_SNIPPET {

	use Recipe\Actions;
	use Recipe\Action_Tokens;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->setup_action();
		$this->set_helpers( new Wpcode_Helpers() );
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	protected function setup_action() {
		$this->set_integration( 'WPCODE_IHAF' );
		$this->set_action_code( 'SNIPPET_DEACTIVATED' );
		$this->set_action_meta( 'WP_CODE_SNIPPETS' );
		$this->set_requires_user( true );
		/* translators: Action - WPCode IHAF */
		$this->set_sentence( sprintf( esc_attr_x( 'Deactivate {{a snippet:%1$s}}', 'WPCode', 'uncanny-automator' ), $this->get_action_meta() ) );
		/* translators: Action - WPCode IHAF */
		$this->set_readable_sentence( esc_attr_x( 'Deactivate {{a snippet}}', 'WPCode', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_action_tokens(
			array(
				'SNIPPET_NAME'  => array(
					'name' => _x( 'Snippet name', 'WPCode', 'uncanny-automator' ),
					'type' => 'text',
				),
				'SNIPPET_CODE'  => array(
					'name' => _x( 'Snippet code', 'WPCode', 'uncanny-automator' ),
					'type' => 'text',
				),
				'CODE_TYPE'     => array(
					'name' => _x( 'Code type', 'WPCode', 'uncanny-automator' ),
					'type' => 'text',
				),
				'DEVICE_TYPE'   => array(
					'name' => _x( 'Device type', 'WPCode', 'uncanny-automator' ),
					'type' => 'text',
				),
				'INSERT_METHOD' => array(
					'name' => _x( 'Insert method', 'WPCode', 'uncanny-automator' ),
					'type' => 'text',
				),
				'LOCATION'      => array(
					'name' => _x( 'Location', 'WPCode', 'uncanny-automator' ),
					'type' => 'text',
				),
				'AUTHOR'        => array(
					'name' => _x( 'Author', 'WPCode', 'uncanny-automator' ),
					'type' => 'text',
				),
				'TAGS'          => array(
					'name' => _x( 'Tags', 'WPCode', 'uncanny-automator' ),
					'type' => 'text',
				),
				'PRIORITY'      => array(
					'name' => _x( 'Priority', 'WPCode', 'uncanny-automator' ),
					'type' => 'text',
				),
				'NOTE'          => array(
					'name' => _x( 'Note', 'WPCode', 'uncanny-automator' ),
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
	 * @return array
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					$this->get_helpers()->get_wpcode_snippets(
						array(
							'option_code'           => $this->get_action_meta(),
							'supports_custom_value' => true,
						)
					),
				),
			)
		);

	}

	/**
	 * Process the action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$snippet_id = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';
		$snippet    = new \WPCode_Snippet( absint( $snippet_id ) );

		if ( empty( $snippet->id ) ) {
			$action_data['complete_with_errors'] = true;
			$error_message                       = sprintf( esc_attr_x( 'The snippet id: %s is not valid.', 'WPCode', 'uncanny-automator' ), $snippet_id );
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		if ( false === $snippet->is_active() ) {
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			$error_message                       = sprintf( esc_attr_x( 'The snippet %s is already inactive.', 'WPCode', 'uncanny-automator' ), $snippet->get_title() );
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->posts,
			array(
				'post_status' => 'draft',
			),
			array(
				'ID' => $snippet_id,
			)
		);
		//Clearing cache
		wpcode()->cache->cache_all_loaded_snippets();

		$this->hydrate_tokens(
			array(
				'SNIPPET_NAME'  => $snippet->get_title(),
				'SNIPPET_CODE'  => $snippet->get_code(),
				'CODE_TYPE'     => $snippet->get_code_type(),
				'DEVICE_TYPE'   => $snippet->get_device_type(),
				'INSERT_METHOD' => ( $snippet->get_auto_insert() ) ? 'Auto Insert' : 'Shortcode',
				'LOCATION'      => $snippet->get_location(),
				'AUTHOR'        => get_the_author_meta( 'display_name', $snippet->get_snippet_author() ),
				'TAGS'          => join( ', ', $snippet->get_tags() ),
				'PRIORITY'      => $snippet->get_priority(),
				'NOTE'          => $snippet->get_note(),
			)
		);

		Automator()->complete->action( $user_id, $action_data, $recipe_id );
	}

}
