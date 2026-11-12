<?php

namespace Uncanny_Automator\Integrations\Code_Snippets;

use WP_Error;
use function Code_Snippets\activate_snippet;
use function Code_Snippets\code_snippets;
use function Code_Snippets\deactivate_snippet;
use function Code_Snippets\get_all_snippet_tags;
use function Code_Snippets\get_snippet;

/**
 * Class Code_Snippets_Helpers
 */
class Code_Snippets_Helpers {

	/**
	 * @param $is_any
	 * @param $is_all
	 *
	 * @return array
	 */
	public function get_all_code_snippets_by_status( $is_any = false, $is_all = false ) {
		$options = array();

		if ( true === $is_any ) {
			$options[] = array(
				'text'  => esc_attr_x( 'Any snippet', 'Code Snippets', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		if ( true === $is_all ) {
			$options[] = array(
				'text'  => esc_attr_x( 'All snippets', 'Code Snippets', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		if ( function_exists( 'Code_Snippets\code_snippets' ) ) {
			$table_name = code_snippets()->db->get_table_name();
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Code Snippets' own table; the plugin offers no API for this query. Table name comes from the plugin's own get_table_name().
			$all_snippets = $wpdb->get_results( "SELECT id,name FROM $table_name", ARRAY_A );
			foreach ( $all_snippets as $snippet ) {
				$options[] = array(
					'text'  => $snippet['name'],
					'value' => $snippet['id'],
				);
			}
		}

		return $options;
	}

	/**
	 * @return array[]
	 */
	public function get_code_types() {
		$options = array(
			array(
				'text'  => esc_attr_x( 'Functions (PHP)', 'Code Snippets', 'uncanny-automator' ),
				'value' => 'php',
			),
			array(
				'text'  => esc_attr_x( 'Content (HTML)', 'Code Snippets', 'uncanny-automator' ),
				'value' => 'html',
			),
		);
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( is_plugin_active( 'code-snippets-pro/code-snippets.php' ) ) {
			$pro_options = array(
				array(
					'text'  => esc_attr_x( 'Styles (CSS)', 'Code Snippets', 'uncanny-automator' ),
					'value' => 'css',
				),
				array(
					'text'  => esc_attr_x( 'Scripts (JS)', 'Code Snippets', 'uncanny-automator' ),
					'value' => 'js',
				),
			);
			$options     = array_merge( $options, $pro_options );
		}

		return $options;

	}

	/**
	 * Legacy wp_ajax handler for the scope dropdown.
	 *
	 * @deprecated Only services Pro's create-snippet action, which now owns this in Code_Snippets_Pro_Helpers.
	 *
	 * @return void
	 */
	public function get_all_scopes_by_code_types() {
		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();
		$options = array();
		if ( ! automator_filter_has_var( 'value', INPUT_POST ) || empty( automator_filter_input( 'value', INPUT_POST ) ) ) {
			echo wp_json_encode( $options );
			die();
		}
		$code_type = automator_filter_input( 'value', INPUT_POST );
		$options   = $this->get_scope_options_by_types( $code_type );

		echo wp_json_encode( $options );
		die();
	}

	/**
	 * Scope options for a code type.
	 *
	 * @deprecated Only services Pro's create-snippet action, which now owns this in Code_Snippets_Pro_Helpers.
	 *
	 * @param string $type Code type.
	 *
	 * @return array|array[]
	 */
	protected function get_scope_options_by_types( $type ) {
		$scopes = array();
		switch ( $type ) {
			case 'php':
				$scopes = array(
					array(
						'text'  => esc_attr_x( 'Run snippet everywhere', 'Code Snippets', 'uncanny-automator' ),
						'value' => 'global',
					),
					array(
						'text'  => esc_attr_x( 'Only run in administration area', 'Code Snippets', 'uncanny-automator' ),
						'value' => 'admin',
					),
					array(
						'text'  => esc_attr_x( 'Only run on site front-end', 'Code Snippets', 'uncanny-automator' ),
						'value' => 'front-end',
					),
					array(
						'text'  => esc_attr_x( 'Only run once', 'Code Snippets', 'uncanny-automator' ),
						'value' => 'single-use',
					),
				);
				break;
			case 'html':
				$scopes = array(
					array(
						'text'  => esc_attr_x( 'Only display when inserted into a post or page.', 'Code Snippets', 'uncanny-automator' ),
						'value' => 'content',
					),
					array(
						'text'  => esc_attr_x( 'Display in site <head> section.', 'Code Snippets', 'uncanny-automator' ),
						'value' => 'head-content',
					),
					array(
						'text'  => esc_attr_x( 'Display at the end of the <body> section, in the footer.', 'Code Snippets', 'uncanny-automator' ),
						'value' => 'footer-content',
					),
				);
				break;
			case 'css':
				$scopes = array(
					array(
						'text'  => esc_attr_x( 'Site front-end styles', 'Code Snippets', 'uncanny-automator' ),
						'value' => 'admin-css',
					),
					array(
						'text'  => esc_attr_x( 'Administration area styles', 'Code Snippets', 'uncanny-automator' ),
						'value' => 'site-css',
					),
				);
				break;
			case 'js':
				$scopes = array(
					array(
						'text'  => esc_attr_x( 'Load JS at the end of the <body> section', 'Code Snippets', 'uncanny-automator' ),
						'value' => 'site-head-js',
					),
					array(
						'text'  => esc_attr_x( 'Load JS in the <head> section', 'Code Snippets', 'uncanny-automator' ),
						'value' => 'site-footer-js',
					),
				);
				break;
		}

		return $scopes;
	}

	/**
	 * @return array|void
	 */
	public function get_all_code_snippet_tags() {
		$options = array();
		if ( ! function_exists( 'Code_Snippets\get_all_snippet_tags' ) ) {
			return $options;
		}
		$all_tags = get_all_snippet_tags();
		foreach ( $all_tags as $tag ) {
			$options[] = array(
				'text'  => $tag,
				'value' => $tag,
			);
		}

		return $options;
	}

	/**
	 * The class Code Snippets builds snippets with: Code_Snippets\Model\Snippet from 3.10.0, Code_Snippets\Snippet
	 * before it. The one place that knows which.
	 *
	 * @since 8.0.4
	 *
	 * @return string The class name, or '' when neither class is loaded.
	 */
	public function get_snippet_class() {
		if ( class_exists( 'Code_Snippets\Model\Snippet' ) ) {
			return 'Code_Snippets\Model\Snippet';
		}

		if ( class_exists( 'Code_Snippets\Snippet' ) ) {
			return 'Code_Snippets\Snippet';
		}

		return '';
	}

	/**
	 * Whether a value Code Snippets returned is one of its snippets.
	 *
	 * @since 8.0.4
	 *
	 * @param mixed $value The value to test.
	 *
	 * @return bool
	 */
	public function is_snippet( $value ) {
		$class = $this->get_snippet_class();

		return '' !== $class && $value instanceof $class;
	}

	/**
	 * The snippet with this ID.
	 *
	 * @since 8.0.4
	 *
	 * @param int $snippet_id The snippet ID.
	 *
	 * @return object|null The snippet, or null when Code Snippets has none with this ID.
	 */
	public function get_snippet( $snippet_id ) {
		$snippet = get_snippet( $snippet_id );

		return $this->is_snippet( $snippet ) ? $snippet : null;
	}

	/**
	 * Activates the snippet with this ID.
	 *
	 * @since 8.0.4
	 *
	 * @param int $snippet_id The snippet ID.
	 *
	 * @return object|WP_Error The activated snippet, or the error Code Snippets reported.
	 */
	public function activate_snippet( $snippet_id ) {
		$result = activate_snippet( $snippet_id );

		return $this->is_snippet( $result ) ? $result : new WP_Error( 'code_snippets_activate_failed', is_string( $result ) ? $result : '' );
	}

	/**
	 * Deactivates the snippet with this ID.
	 *
	 * @since 8.0.4
	 *
	 * @param int $snippet_id The snippet ID.
	 *
	 * @return object|null The deactivated snippet, or null when Code Snippets could not deactivate it.
	 */
	public function deactivate_snippet( $snippet_id ) {
		$result = deactivate_snippet( $snippet_id );

		return $this->is_snippet( $result ) ? $result : null;
	}

	/**
	 * @return array[]
	 */
	public function get_action_common_tokens() {

		return array(
			'SNIPPET_NAME'        => array(
				'name' => _x( 'Title', 'Code Snippets', 'uncanny-automator' ),
				'type' => 'text',
			),
			'SNIPPET_CODE'        => array(
				'name' => _x( 'Code', 'Code Snippets', 'uncanny-automator' ),
				'type' => 'text',
			),
			'SNIPPET_TYPE'        => array(
				'name' => _x( 'Type', 'Code Snippets', 'uncanny-automator' ),
				'type' => 'text',
			),
			'SNIPPET_DESCRIPTION' => array(
				'name' => _x( 'Description', 'Code Snippets', 'uncanny-automator' ),
				'type' => 'text',
			),
			'SNIPPET_TAGS'        => array(
				'name' => _x( 'Tags', 'Code Snippets', 'uncanny-automator' ),
				'type' => 'text',
			),
			'SNIPPET_PRIORITY'    => array(
				'name' => _x( 'Priority', 'Code Snippets', 'uncanny-automator' ),
				'type' => 'int',
			),
		);

	}

	/**
	 * @param $snippet_obj
	 *
	 * @return array
	 */
	public function parse_action_tokens(
		$snippet_obj
	) {

		return array(
			'SNIPPET_NAME'        => $snippet_obj->display_name,
			'SNIPPET_CODE'        => $snippet_obj->code,
			'SNIPPET_TYPE'        => $snippet_obj->type,
			'SNIPPET_DESCRIPTION' => $snippet_obj->desc,
			'SNIPPET_PRIORITY'    => $snippet_obj->priority,
			'SNIPPET_TAGS'        => join( ', ', $snippet_obj->tags ),
		);
	}
}
