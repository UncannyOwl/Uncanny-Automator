<?php

namespace Uncanny_Automator\Integrations\Divi;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Divi_Helpers
 *
 * Modern helper for the Divi integration — owns all form-discovery,
 * matching, and token-keyspace logic. The legacy
 * \Uncanny_Automator\Divi_Helpers stays on disk as a thin delegator
 * shim so old Pro's `extends` clauses and direct static calls keep
 * resolving until that file is finally deleted.
 *
 * @package Uncanny_Automator\Integrations\Divi
 */
class Divi_Helpers extends Abstract_Helpers {

	/**
	 * @var string
	 */
	public static $string_joiner = '__';

	/**
	 * Lazy tokens accessor — single source of truth for token IDs.
	 *
	 * @var Divi_Tokens|null
	 */
	private $tokens = null;

	/**
	 * Lazy accessor for the dedicated Divi_Tokens class.
	 *
	 * @return Divi_Tokens
	 */
	public function tokens() {
		if ( null === $this->tokens ) {
			$this->tokens = new Divi_Tokens( $this );
		}

		return $this->tokens;
	}

	// =========================================================================
	// Trigger option-field builders — shared across all Divi triggers.
	// =========================================================================

	/**
	 * Form-picker option field. Used by every Divi trigger.
	 *
	 * @param string $option_code Stored picker meta key (typically self::TRIGGER_META = 'DIVIFORM').
	 *
	 * @return array
	 */
	public function form_select_config( $option_code = 'DIVIFORM' ) {
		return array(
			'option_code' => $option_code,
			'label'       => esc_html_x( 'Form', 'Divi', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => array(),
			'remote_data' => $this->remote_data_load_config( 'forms' ),
		);
	}

	// =========================================================================
	// Validate gate — shared across all Divi triggers' validate() bodies.
	// =========================================================================

	/**
	 * Resolve the picker-format form_id ({post_id}__{uid}__{idx} or
	 * {uid}__{idx} for Theme Builder) for the form that just submitted.
	 *
	 * Re-runs extract_forms() and substring-matches by unique_id so the
	 * returned form_id is byte-identical to what was emitted at picker-build
	 * time. The framework's Token_Identifier_Partitioner needs hydrate keys
	 * to match define-time tokenIds exactly — any drift between Divi's
	 * runtime contact_form_number and our extract_forms iteration order
	 * would otherwise produce dead tokens.
	 *
	 * @param array $contact_form_info The hook arg's $contact_form_info payload.
	 *
	 * @return string Picker form_id, or '' when no match.
	 */
	public static function picker_form_id_for_runtime( array $contact_form_info ) {
		$unique_id = $contact_form_info['contact_form_unique_id'] ?? '';
		if ( '' === $unique_id ) {
			return '';
		}

		foreach ( self::extract_forms( true ) as $form_id => $_form ) {
			if ( false !== strpos( (string) $form_id, $unique_id ) ) {
				return (string) $form_id;
			}
		}

		return '';
	}

	/**
	 * Run the gate every Divi form trigger shares: error/unique-id checks,
	 * the "-1" Any-form sentinel short-circuit, then prefix-match the runtime
	 * form id against the recipe's picker meta.
	 *
	 * Pro field triggers chain this with field_value_matches() for the
	 * field/value comparison; Free triggers use it as the whole validate().
	 *
	 * @param array  $hook_args      Hook args: [ $fields_values, $et_contact_error, $contact_form_info ].
	 * @param string $selected_form  The recipe's picker meta (e.g. "4083__uid__0" or "-1" for Any).
	 *
	 * @return bool
	 */
	public static function matches_form_selection( array $hook_args, $selected_form ) {
		$et_contact_error  = $hook_args[1] ?? null;
		$contact_form_info = $hook_args[2] ?? array();

		if ( true === $et_contact_error ) {
			return false;
		}

		if ( ! isset( $contact_form_info['contact_form_unique_id'] ) ) {
			return false;
		}

		if ( '-1' === (string) $selected_form ) {
			return true;
		}

		$post_id         = $contact_form_info['post_id'] ?? '';
		$unique_id       = $contact_form_info['contact_form_unique_id'];
		$runtime_form_id = $post_id . self::$string_joiner . $unique_id;

		return self::match_form_ids( $runtime_form_id, (string) $selected_form, false );
	}

	// =========================================================================
	// Remote_Data handlers
	//
	// Resolved via REST: POST /wp-json/uap/v2/remote-data/divi/{segment}.
	// =========================================================================

	/**
	 * Remote-data handler: list of Divi contact forms across the site
	 * (both Divi 4 shortcode and Divi 5 block formats).
	 *
	 * @param \Uncanny_Automator\App\Transports\Restful\Remote_Data\Remote_Data_Request $request
	 *
	 * @return array
	 */
	protected function remote_data_get_forms( $request ): array {
		$forms = self::extract_forms( true );

		$options = array(
			array(
				'value' => '-1',
				'text'  => esc_html_x( 'Any form', 'Divi', 'uncanny-automator' ),
			),
		);

		foreach ( $forms as $form_id => $form ) {
			$options[] = array(
				'value' => (string) $form_id,
				'text'  => $form['title'],
			);
		}

		return $this->remote_data_success( $options );
	}

	// =========================================================================
	// Form discovery — DB scan + Divi 4 shortcode + Divi 5 block parsing.
	// =========================================================================

	/**
	 * Extract form info from Divi posts (Divi 4 shortcode + Divi 5 block formats).
	 *
	 * @param bool $update_form_id Use the post-id__unique-id__form-index keyspace when true.
	 *
	 * @return array
	 */
	public static function extract_forms( $update_form_id = false ) {
		global $wpdb;
		$form_posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `ID`, `post_content`, `post_title` FROM $wpdb->posts
				 WHERE post_status NOT IN('trash', 'inherit', 'auto-draft')
				 AND post_type IS NOT NULL
				 AND post_type NOT LIKE %s
				 AND ( post_content LIKE %s OR post_content LIKE %s )",
				'revision',
				'%%et_pb_contact_form%%',
				'%%wp:divi/contact-form%%'
			)
		);

		$data = array();
		if ( empty( $form_posts ) ) {
			return $data;
		}

		// Track unique_ids we've already catalogued so a Divi Global Module
		// used on N pages produces ONE picker entry instead of N. First post
		// scanned wins. Runtime matching is unaffected: the loose-match
		// resolver works by unique_id alone, so a recipe configured against
		// any instance fires for every page.
		$seen_uids = array();

		foreach ( $form_posts as $form_post ) {
			$is_theme_builder = self::is_theme_builder_post( $form_post );

			if ( false !== strpos( $form_post->post_content, 'wp:divi/contact-form' ) ) {
				$data += self::extract_forms_from_blocks( $form_post, $update_form_id, $is_theme_builder, $seen_uids );
			}

			if ( false !== strpos( $form_post->post_content, '[et_pb_contact_form' ) ) {
				$data += self::extract_forms_from_shortcode( $form_post, $update_form_id, $is_theme_builder, $seen_uids );
			}
		}

		return $data;
	}

	/**
	 * Return one form's fields (or [] when unknown).
	 *
	 * @param string $form_id
	 * @param bool   $updated_options
	 *
	 * @return array
	 */
	public static function get_form_by_id( $form_id, $updated_options = false ) {
		$forms = self::extract_forms( $updated_options );

		foreach ( $forms as $_form_id => $d ) {
			if ( (string) $_form_id === (string) $form_id ) {
				return $d['fields'];
			}
		}

		return array();
	}

	/**
	 * Detect whether a post represents a Theme Builder layout.
	 *
	 * @param object $form_post
	 *
	 * @return bool
	 */
	protected static function is_theme_builder_post( $form_post ) {
		if ( false !== strpos( $form_post->post_content, 'theme_builder_area="' ) ) {
			return true;
		}

		return false !== strpos( $form_post->post_title, 'Theme Builder' );
	}

	/**
	 * Extract Divi 4 shortcode-based contact forms from a post.
	 *
	 * @param object $form_post
	 * @param bool   $update_form_id
	 * @param bool   $is_theme_builder
	 * @param array  $seen_uids Mutable set of unique_ids already catalogued by an earlier post — Divi Global Modules dedupe here so the picker shows one entry per form.
	 *
	 * @return array
	 */
	protected static function extract_forms_from_shortcode( $form_post, $update_form_id, $is_theme_builder, array &$seen_uids = array() ) {
		$data = array();

		$pattern_regex = '/\[et_pb_contact_form(.*?)](.+?)\[\/et_pb_contact_form]/';
		preg_match_all( $pattern_regex, $form_post->post_content, $forms, PREG_SET_ORDER );
		if ( empty( $forms ) ) {
			return $data;
		}

		$form_index = 0;
		foreach ( $forms as $form ) {
			$pattern_form = get_shortcode_regex( array( 'et_pb_contact_form' ) );
			preg_match_all( "/$pattern_form/", $form[0], $forms_extracted, PREG_SET_ORDER );

			if ( empty( $forms_extracted ) ) {
				continue;
			}

			foreach ( $forms_extracted as $form_extracted ) {
				$form_attrs = shortcode_parse_atts( $form_extracted[3] );
				$unique_id  = isset( $form_attrs['_unique_id'] ) ? $form_attrs['_unique_id'] : '';

				if ( empty( $unique_id ) ) {
					continue;
				}

				if ( isset( $seen_uids[ $unique_id ] ) ) {
					continue;
				}
				$seen_uids[ $unique_id ] = true;

				$form_id    = ( true === $update_form_id )
					? self::generate_divi_form_unique_id( $form_post->ID, $form_attrs, $form_index, $is_theme_builder )
					: sprintf( '%d-%s', $form_post->ID, $unique_id );
				$form_title = isset( $form_attrs['title'] ) ? $form_attrs['title'] : esc_html_x( 'No form title', 'Divi', 'uncanny-automator' );

				$data[ $form_id ] = array(
					'title'  => sprintf( '%s - %s', $form_post->post_title, $form_title ),
					'fields' => self::extract_fields( $form[0] ),
				);
			}
			++$form_index;
		}

		return $data;
	}

	/**
	 * Extract Divi 5 block-based contact forms from a post.
	 *
	 * @param object $form_post
	 * @param bool   $update_form_id
	 * @param bool   $is_theme_builder
	 * @param array  $seen_uids Mutable set of unique_ids already catalogued by an earlier post — Divi Global Modules dedupe here so the picker shows one entry per form.
	 *
	 * @return array
	 */
	protected static function extract_forms_from_blocks( $form_post, $update_form_id, $is_theme_builder, array &$seen_uids = array() ) {
		$data = array();

		if ( ! function_exists( 'parse_blocks' ) ) {
			return $data;
		}

		$form_blocks = self::find_contact_form_instances( parse_blocks( $form_post->post_content ) );
		$form_index  = 0;

		foreach ( $form_blocks as $form_block ) {
			$unique_id = self::form_block_unique_id( $form_block );
			if ( empty( $unique_id ) ) {
				continue;
			}

			if ( isset( $seen_uids[ $unique_id ] ) ) {
				continue;
			}
			$seen_uids[ $unique_id ] = true;

			$form_id = ( true === $update_form_id )
				? self::generate_divi_form_unique_id( $form_post->ID, array( '_unique_id' => $unique_id ), $form_index, $is_theme_builder )
				: sprintf( '%d-%s', $form_post->ID, $unique_id );

			// Prefer the Display Title (rendered as a heading), then the Element Label set in
			// the module's Meta panel, then a generic fallback.
			$form_title = self::resolve_block_form_title( $form_block );

			$data[ $form_id ] = array(
				'title'  => sprintf( '%s - %s', $form_post->post_title, $form_title ),
				'fields' => self::extract_fields_from_block( $form_block ),
			);
			++$form_index;
		}

		return $data;
	}

	/**
	 * Recursively find every contact-form instance in a parsed-blocks tree.
	 *
	 * Matches two block shapes Divi 5 emits for the same logical form:
	 *
	 *   1. `divi/contact-form` — the form is inlined directly on the page
	 *      (or in an `et_pb_layout` post when it's the source of a global).
	 *   2. `divi/global-layout` with `attrs.blockName === 'divi/contact-form'`
	 *      — a page references a Library-saved global contact form. The
	 *      block's own `attrs.localAttrs` carries the same `uniqueId` and
	 *      `meta.adminLabel` overrides the underlying form would have, and
	 *      the innerBlocks contain the contact-field blocks marked with a
	 *      `globalParent` ID.
	 *
	 * Returning both shapes lets extract_forms_from_blocks() catalogue the
	 * form on every post that references it — origin page, additional pages
	 * using the global, and the `et_pb_layout` post itself. The seen_uids
	 * dedupe in extract_forms() then collapses them into a single picker
	 * entry whose form_id pins to the lowest-id post (typically the origin
	 * page where the recipe was first configured).
	 *
	 * @param array $blocks
	 *
	 * @return array
	 */
	protected static function find_contact_form_instances( array $blocks ) {
		$matches = array();

		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';

			if ( 'divi/contact-form' === $name ) {
				$matches[] = $block;
			} elseif ( 'divi/global-layout' === $name && 'divi/contact-form' === ( $block['attrs']['blockName'] ?? '' ) ) {
				$matches[] = $block;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$matches = array_merge( $matches, self::find_contact_form_instances( $block['innerBlocks'] ) );
			}
		}

		return $matches;
	}

	/**
	 * Read the unique_id from a contact-form instance regardless of whether
	 * it's a direct `divi/contact-form` block or a `divi/global-layout`
	 * wrapper (which stores the override at `attrs.localAttrs.module
	 * .advanced.uniqueId`).
	 *
	 * @param array $form_block
	 *
	 * @return string
	 */
	protected static function form_block_unique_id( array $form_block ) {
		$attrs_source = self::form_block_attrs_source( $form_block );

		return (string) self::block_attr_value( $attrs_source, array( 'module', 'advanced', 'uniqueId' ) );
	}

	/**
	 * Return the attrs container for a contact-form instance.
	 *
	 * For a direct `divi/contact-form` block it's the block's own attrs;
	 * for a `divi/global-layout` wrapper it's `attrs.localAttrs`, which is
	 * where Divi stores the per-instance uniqueId and Meta-panel adminLabel
	 * for the global module on that page.
	 *
	 * @param array $form_block
	 *
	 * @return array
	 */
	protected static function form_block_attrs_source( array $form_block ) {
		$name = $form_block['blockName'] ?? '';

		if ( 'divi/global-layout' === $name ) {
			return is_array( $form_block['attrs']['localAttrs'] ?? null ) ? $form_block['attrs']['localAttrs'] : array();
		}

		return is_array( $form_block['attrs'] ?? null ) ? $form_block['attrs'] : array();
	}

	/**
	 * Extract Divi 5 contact-field blocks from a parsed contact-form block.
	 *
	 * @param array $form_block
	 *
	 * @return array
	 */
	protected static function extract_fields_from_block( $form_block ) {
		$fields = array();

		if ( empty( $form_block['innerBlocks'] ) ) {
			return $fields;
		}

		foreach ( self::find_blocks( $form_block['innerBlocks'], 'divi/contact-field' ) as $field_block ) {
			$field_id = strtolower( self::block_attr_value( $field_block['attrs'], array( 'fieldItem', 'advanced', 'id' ) ) );
			if ( empty( $field_id ) ) {
				continue;
			}

			$fields[] = array(
				'field_title'   => self::block_attr_value( $field_block['attrs'], array( 'fieldItem', 'innerContent' ), esc_html_x( 'No title', 'Divi', 'uncanny-automator' ) ),
				'field_type'    => self::normalize_field_type( self::block_attr_value( $field_block['attrs'], array( 'fieldItem', 'advanced', 'type' ), 'text' ) ),
				'field_id'      => $field_id,
				'required_mark' => self::block_attr_value( $field_block['attrs'], array( 'fieldItem', 'advanced', 'required' ), 'on' ),
			);
		}

		return $fields;
	}

	/**
	 * Resolve a Divi 5 contact-form block's display label.
	 *
	 * Order: frontend Display Title → Meta panel Element Label → fallback.
	 *
	 * @param array $form_block
	 *
	 * @return string
	 */
	protected static function resolve_block_form_title( $form_block ) {
		// Reads through form_block_attrs_source() so global-layout instances
		// (where Divi stores per-page overrides in attrs.localAttrs) resolve
		// the right adminLabel for the page using the global module.
		$attrs_source = self::form_block_attrs_source( $form_block );

		$display_title = self::block_attr_value( $attrs_source, array( 'title', 'innerContent' ) );
		if ( '' !== $display_title ) {
			return $display_title;
		}

		$element_label = self::block_attr_value( $attrs_source, array( 'module', 'meta', 'adminLabel' ) );
		if ( '' !== $element_label ) {
			return $element_label;
		}

		return esc_html_x( 'No form title', 'Divi', 'uncanny-automator' );
	}

	/**
	 * Recursively collect blocks matching $block_name from a parsed-blocks tree.
	 *
	 * @param array  $blocks
	 * @param string $block_name
	 *
	 * @return array
	 */
	protected static function find_blocks( $blocks, $block_name ) {
		$matches = array();

		foreach ( $blocks as $block ) {
			if ( isset( $block['blockName'] ) && $block_name === $block['blockName'] ) {
				$matches[] = $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$matches = array_merge( $matches, self::find_blocks( $block['innerBlocks'], $block_name ) );
			}
		}

		return $matches;
	}

	/**
	 * Read a Divi 5 block-attribute value by path, unwrapping the
	 * "{ desktop: { value: <scalar> } }" responsive wrapper when present.
	 *
	 * @param array $attrs
	 * @param array $path
	 * @param mixed $fallback Returned when the path doesn't resolve to a scalar.
	 *
	 * @return mixed
	 */
	protected static function block_attr_value( $attrs, $path, $fallback = '' ) {
		$node = $attrs;
		foreach ( $path as $key ) {
			if ( ! is_array( $node ) || ! array_key_exists( $key, $node ) ) {
				return $fallback;
			}
			$node = $node[ $key ];
		}

		if ( is_array( $node ) && isset( $node['desktop']['value'] ) ) {
			return $node['desktop']['value'];
		}

		return is_scalar( $node ) ? $node : $fallback;
	}

	/**
	 * Normalize a Divi 5 field type to the value space used by the token layer.
	 *
	 * @param string $field_type
	 *
	 * @return string
	 */
	protected static function normalize_field_type( $field_type ) {
		// Divi 5 labels single-line text inputs as "input"; Divi 4 (and the token layer) use "text".
		if ( 'input' === $field_type ) {
			return 'text';
		}

		return $field_type;
	}

	/**
	 * Extract fields from a Divi 4 contact-form shortcode body.
	 *
	 * @param string $content_shortcode
	 *
	 * @return array
	 */
	public static function extract_fields( $content_shortcode ) {
		$fields  = array();
		$pattern = get_shortcode_regex( array( 'et_pb_contact_field' ) );

		preg_match_all( "/$pattern/", $content_shortcode, $contact_fields, PREG_SET_ORDER );

		if ( empty( $contact_fields ) ) {
			return $fields;
		}

		foreach ( $contact_fields as $contact_field ) {
			$contact_field_attrs = shortcode_parse_atts( $contact_field[3] );
			$field_id            = strtolower( self::array_get( $contact_field_attrs, 'field_id' ) );

			$fields[] = array(
				'field_title'   => self::array_get( $contact_field_attrs, 'field_title', esc_html_x( 'No title', 'Divi', 'uncanny-automator' ) ),
				'field_type'    => self::array_get( $contact_field_attrs, 'field_type', 'text' ),
				'field_id'      => $field_id,
				'required_mark' => self::array_get( $contact_field_attrs, 'required_mark', 'on' ),
			);
		}

		return $fields;
	}

	/**
	 * Build the stored picker-meta form id ({post_id}__{uid}__{idx}, or {uid}__{idx} in Theme Builder).
	 *
	 * @param int   $post_id
	 * @param array $attrs
	 * @param int   $form_index
	 * @param bool  $is_theme_builder
	 *
	 * @return string
	 */
	public static function generate_divi_form_unique_id( $post_id, $attrs, $form_index, $is_theme_builder = false ) {
		$unique_id = isset( $attrs['_unique_id'] ) ? $attrs['_unique_id'] : uniqid( 'et_pb_contact_form_', true );

		if ( $is_theme_builder ) {
			return $unique_id . self::$string_joiner . $form_index;
		}

		return $post_id . self::$string_joiner . $unique_id . self::$string_joiner . $form_index;
	}

	/**
	 * Prefix-match runtime form id against stored picker meta.
	 *
	 * @param string $form_id
	 * @param string $id_in_meta
	 * @param bool   $loose_match
	 *
	 * @return bool
	 */
	public static function match_form_ids( $form_id, $id_in_meta, $loose_match = false ) {
		$form_parts = explode( self::$string_joiner, $form_id );
		$meta_parts = explode( self::$string_joiner, $id_in_meta );

		if ( ! $loose_match ) {
			return implode( self::$string_joiner, array_slice( $meta_parts, 0, count( $form_parts ) ) ) === $form_id;
		}

		return in_array( $form_parts[0], $meta_parts, true );
	}

	// =========================================================================
	// Generic dot/array-path getter (also reusable by D5 attr lookups).
	// =========================================================================

	/**
	 * Generic dot-path or array-path getter (copied from Divi's `et_()->array_get`).
	 *
	 * @param array        $source
	 * @param string|array $address
	 * @param mixed        $fallback
	 *
	 * @return mixed
	 */
	public static function array_get( $source, $address, $fallback = '' ) {
		$keys  = is_array( $address ) ? $address : explode( '.', $address );
		$value = $source;

		foreach ( $keys as $key ) {
			if ( ! empty( $key ) && isset( $key[0] ) && '[' === $key[0] ) {
				$index = substr( $key, 1, - 1 );

				if ( is_numeric( $index ) ) {
					$key = (int) $index;
				}
			}

			if ( ! isset( $value[ $key ] ) ) {
				return $fallback;
			}

			$value = $value[ $key ];
		}

		return $value;
	}
}
