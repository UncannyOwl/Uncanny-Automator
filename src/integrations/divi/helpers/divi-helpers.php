<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Integrations\Divi\Divi_Helpers as Modern_Divi_Helpers;
use Uncanny_Automator_Pro\Divi_Pro_Helpers;

/**
 * Legacy Divi_Helpers — thin delegator shim.
 *
 * All logic now lives in
 * \Uncanny_Automator\Integrations\Divi\Divi_Helpers. This class stays on
 * disk for two backward-compat scenarios that still reference the
 * global FQN:
 *
 *   1. Old Pro's `Divi_Pro_Helpers extends \Uncanny_Automator\Divi_Helpers`
 *      autoloads this file via the `extends` clause.
 *   2. Old Pro trigger code calls `Divi_Helpers::save_tokens( ... )`,
 *      `Divi_Helpers::match_condition_v2( ... )`, etc. as static methods
 *      against this FQN.
 *
 * Every method below forwards to the modern class. When old Pro (<7.3)
 * is no longer supported this entire file can be deleted in a single
 * sweep — no code-move required.
 *
 * @deprecated 7.3 Use \Uncanny_Automator\Integrations\Divi\Divi_Helpers.
 */
class Divi_Helpers {

	/** @deprecated 7.3 Singleton-chain shim — `->options->method()`. */
	public $options;
	/** @deprecated 7.3 */
	public $load_options = true;
	/** @deprecated 7.3 Old Pro registers itself here via setPro(). */
	public $pro;
	/** @deprecated 7.3 Use Modern_Divi_Helpers::$string_joiner. */
	public static $string_joiner = '__';

	/**
	 * Self-reference the `options` chain so old Pro's
	 * `Automator()->helpers->recipe->divi->options->method()` calls resolve
	 * back to this instance.
	 */
	public function __construct() {
		$this->options = $this;
	}

	/** @deprecated 7.3 */
	public function setOptions( Divi_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/** @deprecated 7.3 */
	public function setPro( Divi_Pro_Helpers $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	/**
	 * Build the picker option array — old Pro reaches this via
	 * `Automator()->helpers->recipe->divi->options->all_divi_forms( ... )`.
	 * Migrated triggers use the `forms` remote_data segment instead.
	 *
	 * @deprecated 7.3 Use the `forms` remote_data segment.
	 *
	 * @param string|null $label
	 * @param string      $option_code
	 * @param array       $args
	 *
	 * @return array
	 */
	public function all_divi_forms( $label = null, $option_code = 'DIVIMFORMS', $args = array() ) {

		$label = null === $label ? esc_attr_x( 'Form', 'Divi', 'uncanny-automator' ) : $label;

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any'    => true,
				'uo_any_label'      => esc_attr_x( 'Any form', 'Divi', 'uncanny-automator' ),
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
		);

		if ( ! \Automator()->helpers->recipe->load_helpers ) {
			return apply_filters( 'uap_option_all_divi_forms', $option );
		}

		if ( $args['uo_include_any'] ) {
			$options['-1'] = $args['uo_any_label'];
		}

		// Form discovery lives in the modern class; delegate.
		$data = Modern_Divi_Helpers::extract_forms( $args['uo_update_form_id'] );

		if ( $data ) {
			foreach ( $data as $form_id => $d ) {
				$options[ $form_id ] = $d['title'];
			}
		}

		$option['options'] = $options;

		return apply_filters( 'uap_option_all_divi_forms', $option );
	}

	/** @deprecated 7.3 */
	public static function array_get( $source, $address, $fallback = '' ) {
		return Modern_Divi_Helpers::array_get( $source, $address, $fallback );
	}

	/** @deprecated 7.3 */
	public static function extract_forms( $update_form_id = false ) {
		return Modern_Divi_Helpers::extract_forms( $update_form_id );
	}

	/** @deprecated 7.3 */
	public static function extract_fields( $content_shortcode ) {
		return Modern_Divi_Helpers::extract_fields( $content_shortcode );
	}

	/** @deprecated 7.3 */
	public static function get_form_by_id( $form_id, $updated_options = false ) {
		return Modern_Divi_Helpers::get_form_by_id( $form_id, $updated_options );
	}

	// -------------------------------------------------------------------------
	// Pure delegators — forward to the modern class which owns the logic.
	// -------------------------------------------------------------------------

	/** @deprecated 7.3 */
	public static function match_form_ids( $form_id, $id_in_meta, $loose_match = false ) {
		return Modern_Divi_Helpers::match_form_ids( $form_id, $id_in_meta, $loose_match );
	}

	/** @deprecated 7.3 */
	public static function generate_divi_form_unique_id( $post_id, $attrs, $form_index, $is_theme_builder = false ) {
		return Modern_Divi_Helpers::generate_divi_form_unique_id( $post_id, $attrs, $form_index, $is_theme_builder );
	}

	// -------------------------------------------------------------------------
	// Legacy-only matchers and save_tokens — full bodies live here because no
	// modern code path uses them; the modern class shouldn't carry the weight.
	// -------------------------------------------------------------------------

	/** @deprecated 7.3 Single-meta matcher used by the deleted *-dep triggers. */
	public static function match_condition( $form_id, $recipes = null, $trigger_meta = null ) {
		if ( empty( $recipes ) ) {
			return false;
		}

		$recipe_ids = array();

		foreach ( $recipes as $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				if ( ! array_key_exists( $trigger_meta, $trigger['meta'] ) ) {
					continue;
				}
				if ( ( (string) $trigger['meta'][ $trigger_meta ] === (string) $form_id ) || ( intval( '-1' ) === intval( $trigger['meta'][ $trigger_meta ] ) ) ) {
					$recipe_ids[ $recipe['ID'] ] = $recipe['ID'];
				}
			}
		}

		if ( empty( $recipe_ids ) ) {
			return false;
		}

		return array(
			'recipe_ids' => $recipe_ids,
			'result'     => true,
		);
	}

	/** @deprecated 7.3 Used only by old Pro's runtime path. Migrated triggers match inline in validate(). */
	public static function match_condition_v2( $contact_form_info, $recipes = null, $trigger_meta = null ) {
		if ( empty( $recipes ) ) {
			return false;
		}

		// Strict match: {post_id}__{uid}
		$form_id     = self::resolve_form_id( $contact_form_info );
		$recipe_ids1 = self::match_condition_with_form_id( $form_id, $recipes, $trigger_meta );

		// Loose match: {uid}
		$form_id     = self::resolve_form_id( $contact_form_info, true );
		$recipe_ids2 = self::match_condition_with_form_id( $form_id, $recipes, $trigger_meta, true );

		return array(
			'recipe_ids' => $recipe_ids1 + $recipe_ids2,
			'form_id'    => $form_id,
			'result'     => true,
		);
	}

	/** @deprecated 7.3 Inner of match_condition_v2. */
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

				if ( Modern_Divi_Helpers::match_form_ids( $entry_to_match, $form_id_in_meta, $loose_match ) ) {
					$recipe_ids[ $recipe['ID'] ] = array(
						'recipe_id'  => $recipe['ID'],
						'trigger_id' => $trigger['ID'],
					);
				}
			}
		}

		return $recipe_ids;
	}

	/** @deprecated 7.3 Build runtime form id from contact_form_info. */
	public static function resolve_form_id( $attr, $loose_match = false ) {
		$contact_form_unique_id = isset( $attr['contact_form_unique_id'] ) ? $attr['contact_form_unique_id'] : '';
		$post_id                = isset( $attr['post_id'] ) ? $attr['post_id'] : '';

		if ( $loose_match ) {
			return $contact_form_unique_id;
		}

		return $post_id . self::$string_joiner . $contact_form_unique_id;
	}

	/**
	 * Save the submitted field map under "{trigger_id}:DIVIFORM" so old Pro's
	 * legacy token parser can resolve per-field tokens at runtime. Migrated
	 * triggers do not call this — they emit the same key→value map via
	 * hydrate_tokens().
	 *
	 * @deprecated 7.3
	 *
	 * @param array  $result
	 * @param array  $fields_values
	 * @param string $form_id
	 * @param string $trigger_meta
	 * @param int    $user_id
	 *
	 * @return void
	 */
	public static function save_tokens( $result, $fields_values, $form_id, $trigger_meta, $user_id ) {
		if ( empty( $result ) || false === $result['result'] ) {
			return;
		}

		$all_fields = array();
		foreach ( $fields_values as $k => $v ) {
			$all_fields[ "$form_id|$k" ] = $v['value'];
		}

		$trigger_id     = $result['args']['trigger_id'];
		$trigger_log_id = absint( $result['args']['trigger_log_id'] );
		$run_number     = absint( $result['args']['run_number'] );
		$meta_key       = sprintf( '%d:%s', $trigger_id, $trigger_meta );

		\Automator()->db->token->save(
			$meta_key,
			maybe_serialize( $all_fields ),
			array(
				'user_id'        => $user_id,
				'trigger_id'     => $trigger_id,
				'trigger_log_id' => $trigger_log_id,
				'run_number'     => $run_number,
			)
		);
	}
}
