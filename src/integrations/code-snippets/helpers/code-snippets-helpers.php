<?php

namespace Uncanny_Automator\Integrations\Code_Snippets;

use function Code_Snippets\code_snippets;
use function Code_Snippets\get_all_snippet_tags;

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
	 * @param $type
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
	 * @return array[]
	 */
	public function get_action_common_tokens() {

		return array(
			'SNIPPET_NAME'        => array(
				'name' => _x( 'Title', 'Code Snippets', 'uncanny-automator-pro' ),
				'type' => 'text',
			),
			'SNIPPET_CODE'        => array(
				'name' => _x( 'Code', 'Code Snippets', 'uncanny-automator-pro' ),
				'type' => 'text',
			),
			'SNIPPET_TYPE'        => array(
				'name' => _x( 'Type', 'Code Snippets', 'uncanny-automator-pro' ),
				'type' => 'text',
			),
			'SNIPPET_DESCRIPTION' => array(
				'name' => _x( 'Description', 'Code Snippets', 'uncanny-automator-pro' ),
				'type' => 'text',
			),
			'SNIPPET_TAGS'        => array(
				'name' => _x( 'Tags', 'Code Snippets', 'uncanny-automator-pro' ),
				'type' => 'text',
			),
			'SNIPPET_PRIORITY'    => array(
				'name' => _x( 'Priority', 'Code Snippets', 'uncanny-automator-pro' ),
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
