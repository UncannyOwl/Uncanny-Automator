<?php

namespace Uncanny_Automator\Integrations\Beaver_Builder;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Beaver_Builder_Helpers
 *
 * Shared option builders and token define/hydrate logic for the Beaver Builder
 * integration. The Pro integration composes this class directly (it adds no
 * methods of its own for the MVP form triggers), so the subscribe/contact token
 * methods here are the single source of truth for both the logged-in (Free) and
 * "Everyone" (Pro) trigger variants.
 *
 * Beaver Builder stores no global form registry — form modules are layout nodes
 * inside a post's serialized `_fl_builder_data` meta. The cheap, queryable
 * discriminator is the page (`post_id`) the form lives on, so the form triggers
 * filter by page rather than by individual form node.
 *
 * @package Uncanny_Automator\Integrations\Beaver_Builder
 */
class Beaver_Builder_Helpers extends Abstract_Helpers {

	/**
	 * "Any" sentinel for the form selector.
	 */
	const ANY_VALUE = '-1';

	/**
	 * Module type slugs (the directory-derived `settings->type` BB stores on a
	 * module node) for the two form modules the triggers target.
	 */
	const MODULE_CONTACT_FORM   = 'contact-form';
	const MODULE_SUBSCRIBE_FORM = 'subscribe-form';

	// =========================================================================
	// Remote_Data handlers — form pickers for the recipe builder.
	//
	// Route: POST /wp-json/uap/v2/remote-data/beaver_builder/{segment}
	// Reached via $this->{$method}() from Abstract_Helpers' dispatcher;
	// visibility is `protected` to keep the REST-reachable surface explicit.
	//
	// Beaver Builder keeps no global form registry — form modules are layout
	// nodes inside each post's serialized `_fl_builder_data` meta. So the picker
	// scans BB-enabled posts and lists each Contact / Subscribe Form node by its
	// label + page, keyed by the node ID (the stable per-form identifier the
	// submit handler echoes back in `$_POST['node_id']`).
	// =========================================================================

	/**
	 * Contact-form picker — every Contact Form module across BB-enabled posts,
	 * prefixed with an "Any contact form" sentinel. Search filters by label/page.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_contact_forms( $request ): array {
		return $this->remote_data_success(
			$this->build_form_options(
				self::MODULE_CONTACT_FORM,
				esc_html_x( 'Any contact form', 'Beaver Builder', 'uncanny-automator' ),
				$request->get_search_query()
			)
		);
	}

	/**
	 * Subscribe-form picker — every Subscribe Form module across BB-enabled
	 * posts, prefixed with an "Any subscribe form" sentinel.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_subscribe_forms( $request ): array {
		return $this->remote_data_success(
			$this->build_form_options(
				self::MODULE_SUBSCRIBE_FORM,
				esc_html_x( 'Any subscribe form', 'Beaver Builder', 'uncanny-automator' ),
				$request->get_search_query()
			)
		);
	}

	/**
	 * Build form options for a given module type by scanning the published
	 * layout data of every BB-enabled post. Option value is the module node ID
	 * (matched at runtime against `$_POST['node_id']`); option text is the
	 * module's label (or a type default) disambiguated by its page title.
	 *
	 * @param string $module_type  One of the MODULE_* slugs.
	 * @param string $any_label    Localized "Any …" sentinel label.
	 * @param string $search_query Optional case-insensitive filter on the text.
	 *
	 * @return array<int,array{text:string,value:string}>
	 */
	private function build_form_options( $module_type, $any_label, $search_query = '' ) {

		$options = array(
			array(
				'text'  => $any_label,
				'value' => self::ANY_VALUE,
			),
		);

		if ( ! class_exists( 'FLBuilderModel' ) ) {
			return $options;
		}

		$default_label = self::MODULE_SUBSCRIBE_FORM === $module_type
			? esc_html_x( 'Subscribe form', 'Beaver Builder', 'uncanny-automator' )
			: esc_html_x( 'Contact form', 'Beaver Builder', 'uncanny-automator' );

		$search_query = (string) $search_query;

		foreach ( $this->get_bb_enabled_posts() as $post ) {

			$nodes = \FLBuilderModel::get_layout_data( 'published', $post->ID );
			if ( ! is_array( $nodes ) ) {
				continue;
			}

			foreach ( $nodes as $node_id => $node ) {

				if ( ! is_object( $node ) || ! isset( $node->type ) || 'module' !== $node->type ) {
					continue;
				}

				if ( ! isset( $node->settings->type ) || $module_type !== $node->settings->type ) {
					continue;
				}

				$label      = ( isset( $node->settings->label ) && '' !== trim( (string) $node->settings->label ) )
					? (string) $node->settings->label
					: $default_label;
				$page_title = (string) $post->post_title;

				// Match the search query against the raw label/title before escaping.
				if ( '' !== $search_query && false === stripos( $label . ' ' . $page_title, $search_query ) ) {
					continue;
				}

				$options[] = array(
					/* translators: 1: Form label 2: Page title */
					'text'  => sprintf( esc_html_x( '%1$s (%2$s)', 'Beaver Builder', 'uncanny-automator' ), esc_html( $label ), esc_html( $page_title ) ),
					'value' => (string) $node_id,
				);
			}
		}

		return $options;
	}

	/**
	 * Published posts with Beaver Builder enabled (`_fl_builder_enabled` meta).
	 * The form pickers unserialize each one's layout data, so the count is
	 * capped to keep the recipe-builder request bounded.
	 *
	 * @param int $limit Max posts to scan.
	 *
	 * @return \WP_Post[]
	 */
	private function get_bb_enabled_posts( $limit = 200 ) {

		return get_posts(
			array(
				'post_type'        => 'any',
				'post_status'      => 'publish',
				'numberposts'      => $limit,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_fl_builder_enabled',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);
	}

	/**
	 * Token definitions shared by both subscribe-form trigger variants.
	 *
	 * @return array<int,array{tokenId:string,tokenName:string,tokenType:string}>
	 */
	public function subscribe_form_tokens() {
		return array_merge(
			array(
				$this->token( 'BB_SUBSCRIBE_FORM_NAME', esc_html_x( 'Subscribe form name', 'Beaver Builder', 'uncanny-automator' ) ),
				$this->token( 'BB_SUBSCRIBE_FORM_ID', esc_html_x( 'Subscribe form ID', 'Beaver Builder', 'uncanny-automator' ) ),
				$this->token( 'BB_SUBSCRIBE_EMAIL', esc_html_x( 'Subscriber email', 'Beaver Builder', 'uncanny-automator' ), 'email' ),
				$this->token( 'BB_SUBSCRIBE_NAME', esc_html_x( 'Subscriber name', 'Beaver Builder', 'uncanny-automator' ) ),
				$this->token( 'BB_SUBSCRIBE_TEMPLATE_ID', esc_html_x( 'Template ID', 'Beaver Builder', 'uncanny-automator' ) ),
				$this->token( 'BB_SUBSCRIBE_TERMS_CHECKED', esc_html_x( 'Terms accepted', 'Beaver Builder', 'uncanny-automator' ) ),
				$this->token( 'BB_SUBSCRIBE_SERVICE_RESPONSE', esc_html_x( 'Email service response', 'Beaver Builder', 'uncanny-automator' ) ),
			),
			$this->page_tokens()
		);
	}

	/**
	 * Map a completed subscribe-form submission to its token values.
	 *
	 * Hook signature: `( $response, $settings, $email, $name, $template_id, $post_id )`.
	 *
	 * @param array $hook_args
	 *
	 * @return array<string,mixed>
	 */
	public function hydrate_subscribe_form_tokens( $hook_args ) {

		$response    = isset( $hook_args[0] ) && is_array( $hook_args[0] ) ? $hook_args[0] : array();
		$email       = isset( $hook_args[2] ) ? sanitize_email( $hook_args[2] ) : '';
		$name        = isset( $hook_args[3] ) ? sanitize_text_field( $hook_args[3] ) : '';
		$template_id = isset( $hook_args[4] ) ? sanitize_text_field( (string) $hook_args[4] ) : '';
		$post_id     = isset( $hook_args[5] ) ? absint( $hook_args[5] ) : 0;

		$terms_checked = '1' === (string) automator_filter_input( 'terms_checked', INPUT_POST );
		$service_msg   = isset( $response['message'] ) && is_string( $response['message'] ) ? $response['message'] : '';

		$node_id = sanitize_text_field( (string) automator_filter_input( 'node_id', INPUT_POST ) );

		return array_merge(
			array(
				'BB_SUBSCRIBE_FORM_NAME'        => $this->get_form_node_label( $post_id, $node_id, esc_html_x( 'Subscribe form', 'Beaver Builder', 'uncanny-automator' ) ),
				'BB_SUBSCRIBE_FORM_ID'          => $node_id,
				'BB_SUBSCRIBE_EMAIL'            => $email,
				'BB_SUBSCRIBE_NAME'             => $name,
				'BB_SUBSCRIBE_TEMPLATE_ID'      => $template_id,
				'BB_SUBSCRIBE_TERMS_CHECKED'    => $terms_checked ? esc_html_x( 'Yes', 'Beaver Builder', 'uncanny-automator' ) : esc_html_x( 'No', 'Beaver Builder', 'uncanny-automator' ),
				'BB_SUBSCRIBE_SERVICE_RESPONSE' => $service_msg,
			),
			$this->hydrate_page_tokens( $post_id )
		);
	}

	/**
	 * Token definitions shared by both contact-form trigger variants.
	 *
	 * @return array<int,array{tokenId:string,tokenName:string,tokenType:string}>
	 */
	public function contact_form_tokens() {
		return array_merge(
			array(
				$this->token( 'BB_CONTACT_FORM_NAME', esc_html_x( 'Contact form name', 'Beaver Builder', 'uncanny-automator' ) ),
				$this->token( 'BB_CONTACT_FORM_ID', esc_html_x( 'Contact form ID', 'Beaver Builder', 'uncanny-automator' ) ),
				$this->token( 'BB_CONTACT_SENDER_NAME', esc_html_x( 'Sender name', 'Beaver Builder', 'uncanny-automator' ) ),
				$this->token( 'BB_CONTACT_SENDER_EMAIL', esc_html_x( 'Sender email', 'Beaver Builder', 'uncanny-automator' ), 'email' ),
				$this->token( 'BB_CONTACT_SENDER_PHONE', esc_html_x( 'Sender phone', 'Beaver Builder', 'uncanny-automator' ) ),
				$this->token( 'BB_CONTACT_SUBJECT', esc_html_x( 'Subject', 'Beaver Builder', 'uncanny-automator' ) ),
				$this->token( 'BB_CONTACT_MESSAGE', esc_html_x( 'Message', 'Beaver Builder', 'uncanny-automator' ) ),
				$this->token( 'BB_CONTACT_EMAIL_BODY', esc_html_x( 'Email body', 'Beaver Builder', 'uncanny-automator' ) ),
				$this->token( 'BB_CONTACT_MAILTO', esc_html_x( 'Recipient email', 'Beaver Builder', 'uncanny-automator' ), 'email' ),
				$this->token( 'BB_CONTACT_SEND_RESULT', esc_html_x( 'Send result', 'Beaver Builder', 'uncanny-automator' ) ),
			),
			$this->page_tokens()
		);
	}

	/**
	 * Map a completed contact-form submission to its token values.
	 *
	 * Hook signature: `( $mailto, $subject, $template, $headers, $settings, $result )`.
	 * Sender fields (incl. the raw message) and the page ID are read from the
	 * (nonce-verified upstream) AJAX `$_POST` payload — the hook does not carry
	 * them. `$hook_args[2]` is the *rendered* admin-notification body (name,
	 * email, subject and message concatenated), surfaced separately as the
	 * "Email body" token; "Message" is the raw field the visitor typed.
	 *
	 * @param array $hook_args
	 *
	 * @return array<string,mixed>
	 */
	public function hydrate_contact_form_tokens( $hook_args ) {

		$mailto     = isset( $hook_args[0] ) ? sanitize_email( $hook_args[0] ) : '';
		$subject    = isset( $hook_args[1] ) ? sanitize_text_field( $hook_args[1] ) : '';
		$email_body = isset( $hook_args[2] ) ? sanitize_textarea_field( $hook_args[2] ) : '';
		$result     = ! empty( $hook_args[5] );

		$post_id = absint( automator_filter_input( 'post_id', INPUT_POST ) );
		$node_id = sanitize_text_field( (string) automator_filter_input( 'node_id', INPUT_POST ) );

		return array_merge(
			array(
				'BB_CONTACT_FORM_NAME'    => $this->get_form_node_label( $post_id, $node_id, esc_html_x( 'Contact form', 'Beaver Builder', 'uncanny-automator' ) ),
				'BB_CONTACT_FORM_ID'      => $node_id,
				'BB_CONTACT_SENDER_NAME'  => sanitize_text_field( (string) automator_filter_input( 'name', INPUT_POST ) ),
				'BB_CONTACT_SENDER_EMAIL' => sanitize_email( (string) automator_filter_input( 'email', INPUT_POST ) ),
				'BB_CONTACT_SENDER_PHONE' => sanitize_text_field( (string) automator_filter_input( 'phone', INPUT_POST ) ),
				'BB_CONTACT_SUBJECT'      => $subject,
				'BB_CONTACT_MESSAGE'      => sanitize_textarea_field( (string) automator_filter_input( 'message', INPUT_POST ) ),
				'BB_CONTACT_EMAIL_BODY'   => $email_body,
				'BB_CONTACT_MAILTO'       => $mailto,
				'BB_CONTACT_SEND_RESULT'  => $result ? esc_html_x( 'Sent', 'Beaver Builder', 'uncanny-automator' ) : esc_html_x( 'Failed', 'Beaver Builder', 'uncanny-automator' ),
			),
			$this->hydrate_page_tokens( $post_id )
		);
	}

	/**
	 * Shared page-context token definitions (page ID / title / URL).
	 *
	 * @return array<int,array{tokenId:string,tokenName:string,tokenType:string}>
	 */
	private function page_tokens() {
		return array(
			$this->token( 'BB_FORM_PAGE_ID', esc_html_x( 'Page ID', 'Beaver Builder', 'uncanny-automator' ), 'int' ),
			$this->token( 'BB_FORM_PAGE_TITLE', esc_html_x( 'Page title', 'Beaver Builder', 'uncanny-automator' ) ),
			$this->token( 'BB_FORM_PAGE_URL', esc_html_x( 'Page URL', 'Beaver Builder', 'uncanny-automator' ), 'url' ),
		);
	}

	/**
	 * Shared page-context token values. Returns the full keyset even for an
	 * invalid post so a recipe never sees a partial token map.
	 *
	 * @param int $post_id
	 *
	 * @return array<string,mixed>
	 */
	private function hydrate_page_tokens( $post_id ) {

		$post_id = absint( $post_id );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;

		return array(
			'BB_FORM_PAGE_ID'    => $post_id,
			'BB_FORM_PAGE_TITLE' => $post instanceof \WP_Post ? $post->post_title : '',
			'BB_FORM_PAGE_URL'   => $post instanceof \WP_Post ? (string) get_permalink( $post ) : '',
		);
	}

	/**
	 * Resolve a form module's display label from its node ID within a post's
	 * published layout. Falls back to `$default_label` when the post/node can't
	 * be resolved (e.g. a global-template form whose node lives elsewhere).
	 *
	 * @param int    $post_id
	 * @param string $node_id
	 * @param string $default_label
	 *
	 * @return string
	 */
	private function get_form_node_label( $post_id, $node_id, $default_label = '' ) {

		$post_id = absint( $post_id );
		$node_id = (string) $node_id;

		if ( 0 === $post_id || '' === $node_id || ! class_exists( 'FLBuilderModel' ) ) {
			return $default_label;
		}

		$nodes = \FLBuilderModel::get_layout_data( 'published', $post_id );
		if ( ! is_array( $nodes ) || ! isset( $nodes[ $node_id ] ) ) {
			return $default_label;
		}

		$node = $nodes[ $node_id ];

		return ( is_object( $node ) && isset( $node->settings->label ) && '' !== trim( (string) $node->settings->label ) )
			? (string) $node->settings->label
			: $default_label;
	}

	/**
	 * Build a single trigger-token definition row.
	 *
	 * @param string $id
	 * @param string $name
	 * @param string $type
	 *
	 * @return array{tokenId:string,tokenName:string,tokenType:string}
	 */
	private function token( $id, $name, $type = 'text' ) {
		return array(
			'tokenId'   => $id,
			'tokenName' => $name,
			'tokenType' => $type,
		);
	}
}
