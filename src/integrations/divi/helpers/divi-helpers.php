<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Divi_Pro_Helpers;

/**
 * Divi integration helper file
 */
class Divi_Helpers {
	/**
	 * Store Divi options
	 *
	 * @var Divi_Helpers
	 */
	public $options;

	/**
	 * @var bool
	 */
	public $load_options = true;

	/**
	 * Store Divi Pro Helper instance
	 *
	 * @var Divi_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var string
	 */
	public static $string_joiner = '__';

	/**
	 * Divi_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * Set Divi options
	 *
	 * @param Divi_Helpers $options
	 */
	public function setOptions( Divi_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * Set Divi Pro Helper instance
	 *
	 * @param Divi_Pro_Helpers $pro
	 */
	public function setPro( Divi_Pro_Helpers $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	/**
	 * Fetch all Divi forms
	 *
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_divi_forms( $label = null, $option_code = 'DIVIMFORMS', $args = array() ) {

		$label = null === $label ? esc_attr__( 'Form', 'uncanny-automator' ) : $label;

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any'    => true,
				'uo_any_label'      => esc_attr__( 'Any form', 'uncanny-automator' ),
				'uo_update_form_id' => false,
			)
		);

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();
		$option       = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
			//'options_show_id' => false,
		);

		if ( ! Automator()->helpers->recipe->load_helpers ) {
			return apply_filters( 'uap_option_all_divi_forms', $option );
		}

		if ( $args['uo_include_any'] ) {
			$options['-1'] = $args['uo_any_label'];
		}

		$data = self::extract_forms( $args['uo_update_form_id'] );

		if ( $data ) {
			foreach ( $data as $form_id => $d ) {
				$options[ $form_id ] = $d['title'];
			}
		}

		$option['options'] = $options;

		return apply_filters( 'uap_option_all_divi_forms', $option );
	}

	/**
	 * Pseudo function copied from Divi
	 *
	 * @param $array
	 * @param $address
	 * @param string $default
	 *
	 * @return mixed|string
	 */
	public static function array_get( $array, $address, $default = '' ) {
		$keys  = is_array( $address ) ? $address : explode( '.', $address );
		$value = $array;

		foreach ( $keys as $key ) {
			if ( ! empty( $key ) && isset( $key[0] ) && '[' === $key[0] ) {
				$index = substr( $key, 1, - 1 );

				if ( is_numeric( $index ) ) {
					$key = (int) $index;
				}
			}

			if ( ! isset( $value[ $key ] ) ) {
				return $default;
			}

			$value = $value[ $key ];
		}

		return $value;
	}

	/**
	 *  Extract form info from Divi posts (Divi 4 shortcode and Divi 5 block formats).
	 *
	 * @param $update_form_id
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
		// scanned wins. Runtime matching is unaffected: match_condition_v2()
		// already has a loose-match pass that resolves by unique_id alone,
		// so a recipe configured against any instance fires for every page.
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
				$form_title = isset( $form_attrs['title'] ) ? $form_attrs['title'] : esc_html__( 'No form title', 'uncanny-automator' );

				$data[ $form_id ] = array(
					'title'  => sprintf( '%s - %s', $form_post->post_title, $form_title ),
					'fields' => self::extract_fields( $form[0] ),
				);
			}
			$form_index ++;
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
			$form_index ++;
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
	 * for a `divi/global-layout` wrapper it's `attrs.localAttrs`, which
	 * is where Divi stores the per-instance uniqueId and Meta-panel
	 * adminLabel for the global module on that page.
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
				'field_title'   => self::block_attr_value( $field_block['attrs'], array( 'fieldItem', 'innerContent' ), esc_html__( 'No title', 'uncanny-automator' ) ),
				'field_type'    => self::normalize_field_type( self::block_attr_value( $field_block['attrs'], array( 'fieldItem', 'advanced', 'type' ), 'text' ) ),
				'field_id'      => $field_id,
				'required_mark' => self::block_attr_value( $field_block['attrs'], array( 'fieldItem', 'advanced', 'required' ), 'on' ),
			);
		}

		return $fields;
	}

	/**
	 * Resolve a Divi 5 contact-form block's display label, preferring the
	 * frontend Display Title, then the Meta panel's Element Label.
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

		return esc_html__( 'No form title', 'uncanny-automator' );
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
	 * @param mixed $default
	 *
	 * @return mixed
	 */
	protected static function block_attr_value( $attrs, $path, $default = '' ) {
		$node = $attrs;
		foreach ( $path as $key ) {
			if ( ! is_array( $node ) || ! array_key_exists( $key, $node ) ) {
				return $default;
			}
			$node = $node[ $key ];
		}

		if ( is_array( $node ) && isset( $node['desktop']['value'] ) ) {
			return $node['desktop']['value'];
		}

		return is_scalar( $node ) ? $node : $default;
	}

	/**
	 * Normalize a Divi 5 field type to the value space used by the existing token layer.
	 *
	 * @param string $field_type
	 *
	 * @return string
	 */
	protected static function normalize_field_type( $field_type ) {
		// Divi 5 labels single-line text inputs as "input"; Divi 4 (and the
		// token layer) use "text".
		if ( 'input' === $field_type ) {
			return 'text';
		}

		return $field_type;
	}

	/**
	 * Extracting fields from the form shortcode
	 *
	 * @param $content_shortcode
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
				'field_title'   => self::array_get( $contact_field_attrs, 'field_title', esc_html__( 'No title', 'uncanny-automator' ) ),
				'field_type'    => self::array_get( $contact_field_attrs, 'field_type', 'text' ),
				'field_id'      => $field_id,
				'required_mark' => self::array_get( $contact_field_attrs, 'required_mark', 'on' ),
			);
		}

		return $fields;
	}

	/**
	 * Select form by ID
	 *
	 * @param $form_id
	 *
	 * @return array|mixed
	 */
	public static function get_form_by_id( $form_id, $updated_options = false ) {
		$forms = self::extract_forms( $updated_options );
		if ( empty( $forms ) ) {
			return array();
		}

		foreach ( $forms as $_form_id => $d ) {
			if ( (string) $_form_id === (string) $form_id ) {
				return $d['fields'];
			}
		}

		return array();
	}

	/**
	 * Match conditions in Divi triggers
	 *
	 * @param $form_id
	 * @param null $recipes
	 * @param null $trigger_meta
	 *
	 * @return array|false
	 */

	public static function match_condition( $form_id, $recipes = null, $trigger_meta = null ) {
		if ( empty( $recipes ) ) {
			return false;
		}

		$recipe_ids     = array();
		$entry_to_match = $form_id;

		foreach ( $recipes as $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				if ( ! array_key_exists( $trigger_meta, $trigger['meta'] ) ) {
					continue;
				}
				if ( ( (string) $trigger['meta'][ $trigger_meta ] === (string) $entry_to_match ) || ( intval( '-1' ) === intval( $trigger['meta'][ $trigger_meta ] ) ) ) {
					$recipe_ids[ $recipe['ID'] ] = $recipe['ID'];
				}
			}
		}

		if ( ! empty( $recipe_ids ) ) {
			return array(
				'recipe_ids' => $recipe_ids,
				'result'     => true,
			);
		}

		return false;
	}

	/**
	 * @param $contact_form_info
	 * @param $recipes
	 * @param $trigger_meta
	 *
	 * @return array|false
	 */
	public static function match_condition_v2( $contact_form_info, $recipes = null, $trigger_meta = null ) {

		if ( empty( $recipes ) ) {
			return false;
		}

		$recipe_ids  = array();
		$recipe_ids1 = array();
		$recipe_ids2 = array();

		// Strict match with post-id-unique-id-form-number
		$form_id     = self::resolve_form_id( $contact_form_info );
		$recipe_ids1 = self::match_condition_with_form_id( $form_id, $recipes, $trigger_meta );

		$form_id     = self::resolve_form_id( $contact_form_info, true );
		$recipe_ids2 = self::match_condition_with_form_id( $form_id, $recipes, $trigger_meta, true );

		$recipe_ids = $recipe_ids1 + $recipe_ids2;

		return array(
			'recipe_ids' => $recipe_ids,
			'form_id'    => $form_id,
			'result'     => true,
		);
	}

	/**
	 * @param $entry_to_match
	 * @param $recipes
	 * @param $trigger_meta
	 * @param $loose_match
	 *
	 * @return array
	 */
	public static function match_condition_with_form_id( $entry_to_match, $recipes = null, $trigger_meta = null, $loose_match = false ) {
		$recipe_ids = array();
		foreach ( $recipes as $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				if ( ! array_key_exists( $trigger_meta, $trigger['meta'] ) ) {
					continue;
				}

				$form_id_in_meta = (string) $trigger['meta'][ $trigger_meta ];

				if ( intval( '-1' ) === intval( $form_id_in_meta ) ) {
					$recipe_ids[ $recipe['ID'] ] = array(
						'recipe_id'  => $recipe['ID'],
						'trigger_id' => $trigger['ID'],
					);
					continue;
				}

				if ( self::match_form_ids( $entry_to_match, $form_id_in_meta, $loose_match ) ) {
					$recipe_ids[ $recipe['ID'] ] = array(
						'recipe_id'  => $recipe['ID'],
						'trigger_id' => $trigger['ID'],
					);
				}
			}
		}

		return $recipe_ids;
	}

	/**
	 * @param $form_id
	 * @param $id_in_meta
	 * @param $loose_match
	 *
	 * @return bool
	 */
	public static function match_form_ids( $form_id, $id_in_meta, $loose_match = false ) {
		// Explode the strings by self::$string_joiner
		$form_parts = explode( self::$string_joiner, $form_id );
		$meta_parts = explode( self::$string_joiner, $id_in_meta );

		if ( ! $loose_match ) {
			// Strict match: Check if $form_id is fully present in $id_in_meta
			return $form_id === implode( self::$string_joiner, array_slice( $meta_parts, 0, count( $form_parts ) ) );
		}

		return in_array( $form_parts[0], $meta_parts, true );
	}

	/**
	 * Saving tokens
	 *
	 * @param $result
	 * @param $fields_values
	 * @param $form_id
	 * @param $trigger_meta
	 * @param $user_id
	 */
	public static function save_tokens( $result, $fields_values, $form_id, $trigger_meta, $user_id ) {

		if ( empty( $result ) ) {
			return;
		}

		if ( false === $result['result'] ) {
			return;
		}

		$all_fields = array();

		foreach ( $fields_values as $k => $v ) {
			$field_id                = "$form_id|$k";
			$all_fields[ $field_id ] = $v['value'];
		}
		$trigger_id     = $result['args']['trigger_id'];
		$trigger_log_id = absint( $result['args']['trigger_log_id'] );
		$run_number     = absint( $result['args']['run_number'] );
		$meta_key       = sprintf( '%d:%s', $trigger_id, $trigger_meta );

		$trigger_meta = array(
			'user_id'        => $user_id,
			'trigger_id'     => $trigger_id,
			'trigger_log_id' => $trigger_log_id,
			'run_number'     => $run_number,
		);

		Automator()->db->token->save( $meta_key, maybe_serialize( $all_fields ), $trigger_meta );
	}

	/**
	 * @param $post_id
	 * @param $attrs
	 * @param $form_index
	 * @param $is_theme_builder
	 *
	 * @return string
	 */
	public static function generate_divi_form_unique_id( $post_id, $attrs, $form_index, $is_theme_builder = false ) {
		// Extract the existing unique ID from the attributes, if available
		$unique_id = isset( $attrs['_unique_id'] ) ? $attrs['_unique_id'] : uniqid( 'et_pb_contact_form_', true );

		// If the form is part of a theme builder layout, don't include the post ID
		if ( $is_theme_builder ) {
			$generated_unique_id = $unique_id . self::$string_joiner . $form_index;
		} else {
			// Combine the post ID, unique ID, and form index to ensure uniqueness across the page
			$generated_unique_id = $post_id . self::$string_joiner . $unique_id . self::$string_joiner . $form_index;
		}

		return $generated_unique_id;
	}

	/**
	 * @param $attr
	 * @param $loose_match
	 *
	 * @return mixed|string
	 */
	public static function resolve_form_id( $attr, $loose_match = false ) {

		//$contact_form_number = isset($attr['contact_form_number']) ? $attr['contact_form_number'] : 0;
		$contact_form_unique_id = isset( $attr['contact_form_unique_id'] ) ? $attr['contact_form_unique_id'] : '';
		$post_id                = isset( $attr['post_id'] ) ? $attr['post_id'] : '';

		if ( $loose_match ) {
			$contact_form_id = $contact_form_unique_id;
		} else {
			$contact_form_id = $post_id . self::$string_joiner . $contact_form_unique_id;
		}

		return $contact_form_id;
	}
}
