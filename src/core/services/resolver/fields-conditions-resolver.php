<?php
namespace Uncanny_Automator\Resolver;

class Fields_Conditions_Resolver {

	/**
	 * @var int $recipe_id
	 */
	protected $recipe_id;

	/**
	 * @var mixed[] $recipe_actions_conditions
	 */
	protected $recipe_actions_conditions = array();

	/**
	 * @var mixed[] $recipe_actions_conditions_raw
	 */
	protected $recipe_actions_conditions_raw = array();

	/**
	 * Get $recipe_id
	 *
	 * @return  int
	 */
	public function get_recipe_id() {
		return $this->recipe_id;
	}

	/**
	 * Set $recipe_id
	 *
	 * @param  int  $recipe_id  $recipe_id
	 *
	 * @return  self
	 */
	public function set_recipe_id( $recipe_id ) {
		$this->recipe_id = $recipe_id;

		return $this;
	}

	/**
	 * Get $recipe_actions_conditions
	 *
	 * @return mixed[]
	 */
	public function get_recipe_actions_conditions() {
		return $this->recipe_actions_conditions;
	}

	/**
	 * Set $recipe_actions_conditions
	 *
	 * @param  mixed[]  $recipe_actions_conditions  $recipe_actions_conditions
	 *
	 * @return  self
	 */
	public function set_recipe_actions_conditions( $recipe_actions_conditions ) {
		$this->recipe_actions_conditions = $recipe_actions_conditions;

		return $this;
	}

	/**
	 * Get $recipe_actions_conditions_raw
	 *
	 * @return mixed[]
	 */
	public function get_recipe_actions_conditions_raw() {
		return $this->recipe_actions_conditions_raw;
	}

	/**
	 * Set $recipe_actions_conditions_raw
	 *
	 * @param  mixed[]  $recipe_actions_conditions_raw
	 *
	 * @return self
	 */
	public function set_recipe_actions_conditions_raw( $recipe_actions_conditions_raw ) {
		$this->recipe_actions_conditions_raw = $recipe_actions_conditions_raw;

		return $this;
	}

	/**
	 * @param string $integration_code
	 * @param string $condition_code
	 *
	 * @return mixed[] The array of fields.
	 */
	protected function get_recipe_condition_fields( $integration_code, $condition_code ) {

		return apply_filters(
			'automator_pro_actions_conditions_fields',
			array(),
			$integration_code,
			$condition_code
		);

	}

	/**
	 * @param array{recipe_id:int, recipe_log_id: int, run_number:int} $params
	 *
	 * @return string[]
	 */
	private function get_condition_summary( $params ) {

		global $wpdb;

		$condition_summary = array();

		// @todo Move to a query class.
		$conditions_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->prefix}uap_recipe_log_meta
				WHERE user_id = %d
				AND recipe_id = %d
				AND recipe_log_id = %d
				AND meta_key = 'conditions_result'
				",
				apply_filters( 'automator_field_resolver_condition_result_user_id', null ),
				$params['recipe_id'],
				$params['recipe_log_id']
			),
			ARRAY_A
		);

		foreach ( $conditions_results as $condition_result ) {
			$json_result = (array) json_decode( $condition_result['meta_value'], true );
			foreach ( $json_result as $cond_id => $condition_item_result ) {
				$condition_summary[ $cond_id ] = $condition_item_result;
			}
		}

		return $condition_summary;

	}

	/**
	 * @param array{recipe_id:int, recipe_log_id: int, run_number:int} $params
	 * @param mixed[] $interpolated
	 *
	 * @return mixed[]
	 */
	public function resolve( $params, $interpolated ) {

		$items = array();

		$conditions_statuses = array();

		// The saved actions conditions. The one that is supplied by the user.
		$recipe_actions_conditions = $this->get_recipe_actions_conditions();

		// Iterate through the given conditions.
		foreach ( $recipe_actions_conditions as $recipe_actions_condition ) {

			$recipe_actions_condition = wp_parse_args(
				(array) $recipe_actions_condition,
				array(
					'fields'      => array(),
					'integration' => '',
					'condition'   => '',
				)
			);

			$field_values = $recipe_actions_condition['fields'];
			$fields_item  = array();

			$conditions_fields = $this->get_recipe_condition_fields(
				$recipe_actions_condition['integration'],
				$recipe_actions_condition['condition']
			);

			foreach ( $conditions_fields as $conditions_field ) {

				$conditions_field = wp_parse_args(
					(array) $conditions_field,
					array(
						'options_code' => '',
						'label'        => '',
					)
				);

				$field_value = $field_values[ $conditions_field['option_code'] ];
				$type        = $conditions_field['input_type'];
				$readable    = false;

				if ( 'automator_custom_value' === $field_value && isset( $field_values[ $conditions_field['option_code'] . '_readable' ] ) ) {
					$field_value = $field_values[ $conditions_field['option_code'] . '_readable' ];
				}

				if ( 'select' === $type && isset( $field_values[ $conditions_field['option_code'] . '_readable' ] ) ) {
					$readable = $field_values[ $conditions_field['option_code'] . '_readable' ];
				}

				$fields_item[] = array(
					'field_code' => $conditions_field['option_code'],
					'type'       => $type,
					'label'      => $conditions_field['label'],
					'value'      => array(
						'readable' => $readable,
						'raw'      => $field_value,
						'parsed'   => Automator()->parsed_token_records()->interpolate( $field_value, $interpolated ),
					),
				);

			}

			// Conditions failed messages resolving end.
			$conditions_failed = Automator()->get_conditions_failed(
				$params['recipe_id'],
				$params['recipe_log_id']
			);

			// Condition IDs are string.
			$id = (string) $recipe_actions_condition['id'];

			$conditions_statuses[ $id ] = array(
				'status_id'     => 'not-evaluated',
				'error_message' => isset( $conditions_failed[ $id ] ) ? $conditions_failed[ $id ] : '',
			);

			$condition_summary = $this->get_condition_summary( $params );

			$evaluated = $this->is_evaluated( $id, $condition_summary );

			if ( true === $evaluated ) {

				$conditions_statuses[ $id ]['status_id'] = 'met';

				// Prevents conditions that have a status 'succeeded' outputting the previous error message as default.
				if ( 'succeeded' === $condition_summary[ $id ] ) {
					$conditions_statuses[ $id ]['error_message'] = '';
				}

				if ( ! isset( $condition_summary[ $id ] ) || 'succeeded' !== $condition_summary[ $id ] ) {
					$conditions_statuses[ $id ]['status_id'] = 'not-met';
				}
			}

			$name_dynamic = isset( $recipe_actions_condition['backup']['nameDynamic'] )
						? $recipe_actions_condition['backup']['nameDynamic'] :
						''; // Defaults to empty string.

			$title_html_backup = isset( $recipe_actions_condition['backup']['titleHTML'] ) ? (string) $recipe_actions_condition['backup']['titleHTML'] : '';
			$has_title_html    = '' !== trim( wp_strip_all_tags( html_entity_decode( $title_html_backup, ENT_QUOTES, 'UTF-8' ) ) );

			/**
			 * @todo Move as separate func.
			 */
			if ( ! $has_title_html ) {
				$title_html = $this->parse_condition_sentence( $name_dynamic, $recipe_actions_condition, $conditions_fields, $interpolated );
			} else {
				$title_html = $title_html_backup;
			}

			$items[] = array(
				'title_html'       => $title_html,
				'id'               => $id,
				'integration_code' => $recipe_actions_condition['integration'],
				'code'             => $recipe_actions_condition['condition'],
				'fields'           => $fields_item,
				'status_id'        => $conditions_statuses[ $id ]['status_id'],
				'result_message'   => $conditions_statuses[ $id ]['error_message'],
			);

		}

		return $items;

	}

	/**
	 * @param string $id The condition ID is string (e.g. lgqsnddqvji5b4st55r)
	 * @param string[] $conditions_summary The collection of condition IDs as key with 'succeeded' and 'failed'.
	 *
	 * @return bool
	 */
	protected function is_evaluated( $id, $conditions_summary ) {
		return in_array( $id, array_keys( $conditions_summary ), true );
	}

	/**
	 * This is a fallback function because we havent saved the conditions title HTML.
	 *
	 * This method is complex. We can remove this after a while.
	 *
	 * @param string $name_dynamic
	 * @param mixed[] $recipe_actions_condition
	 * @param mixed[] $conditions_fields
	 * @param mixed[] $interpolated
	 *
	 * @return string
	 */
	protected function parse_condition_sentence( $name_dynamic, $recipe_actions_condition, $conditions_fields, $interpolated = array() ) {
		$name_dynamic = is_string( $name_dynamic ) ? $name_dynamic : '';
		if ( '' === trim( $name_dynamic ) ) {
			$name_dynamic = $this->resolve_condition_dynamic_name_from_registry( (array) $recipe_actions_condition );
		}

		if ( '' === trim( $name_dynamic ) ) {
			$name_dynamic = $this->build_fallback_dynamic_name( (array) $recipe_actions_condition['fields'] );
		}

		if ( '' === trim( $name_dynamic ) ) {
			$name_dynamic = esc_html_x( 'Condition', 'Condition fallback sentence', 'uncanny-automator' );
		}

		$normalized_condition_field = array();
		// Put condition ID as an index of the conditions fields.
		foreach ( $conditions_fields as $condition_field ) {
			if ( ! is_array( $condition_field ) ) {
				continue;
			}
			$normalized_condition_field[ $condition_field['option_code'] ] = $condition_field;
		}
		preg_match_all( '/{{\s*(.*?)\s*}}/', $name_dynamic, $arr );
		if ( empty( $arr[0] ) ) {
			return str_replace( array( '{{', '}}' ), '', $name_dynamic );
		}
		$matches               = $arr[1];
		$interpolated_internal = array();
		foreach ( $matches as $i => $match ) {
			$replaceable                      = '';
			$parts                             = explode( ':', $match, 2 );
			$sentence_a                        = isset( $parts[0] ) ? trim( $parts[0] ) : '';
			$option_code                       = isset( $parts[1] ) ? trim( $parts[1] ) : '';
			$condition_field                   = $normalized_condition_field[ $option_code ] ?? array();
			$show_label                        = isset( $condition_field['show_label_in_sentence'] ) ? $condition_field['show_label_in_sentence'] : true;
			$input_type                        = isset( $condition_field['input_type'] ) ? $condition_field['input_type'] : 'text';

			if ( 'select' !== $input_type ) {
				$replaceable = isset( $recipe_actions_condition['fields'][ $option_code . '_readable' ] )
					? $recipe_actions_condition['fields'][ $option_code . '_readable' ]
					: ( $recipe_actions_condition['fields'][ $option_code ] ?? $sentence_a );

				if ( is_array( $replaceable ) || is_object( $replaceable ) ) {
					$replaceable = $sentence_a;
				}

				$replaceable = (string) $replaceable;
				$parsed      = Automator()->parsed_token_records()->interpolate( $replaceable, $interpolated );

				if ( is_string( $parsed ) && '' !== trim( $parsed ) ) {
					$replaceable = $parsed;
				}

				// Prevent unresolved token IDs from leaking to logs.
				if ( preg_match( '/^\{\{(.+)\}\}$/', trim( $replaceable ) ) ) {
					$replaceable = $sentence_a;
				}
			} else {
				$label = '';
				if ( isset( $recipe_actions_condition['fields'][ $option_code . '_label' ] ) ) {
					$label = '<span class="sentence-pill-label">' . $recipe_actions_condition['fields'][ $option_code . '_label' ] . ':</span>';
				}
				// Select field show readable.
				if ( isset( $recipe_actions_condition['fields'][ $option_code . '_readable' ] ) ) {
					$replaceable = $label . $recipe_actions_condition['fields'][ $option_code . '_readable' ];
				}
			}
			if ( false === $show_label ) {
				$replaceable = str_replace( $sentence_a . ':', '', $replaceable );
			} else {
				if ( isset( $recipe_actions_condition['fields'][ $option_code . '_label' ] ) ) {
					$label       = '<span class="sentence-pill-label">' . $recipe_actions_condition['fields'][ $option_code . '_label' ] . ': </span>';
					$replaceable = str_replace( $sentence_a . ':', $label, $replaceable );
				}
			}
			$interpolated_internal[ $arr[0][ $i ] ] = '<span class="sentence-pill sentence-pill--filled">' . $replaceable . '</span>';
		}

		$title_html = '<span class="sentence sentence--old-condition">' . strtr( $name_dynamic, $interpolated_internal ) . '</span>';

		// Find all tokens and format them.
		preg_match_all( '/{{\s*(.*?)\s*}}/', $title_html, $tokens );
		$_tokens_interpolates = array();
		if ( ! empty( $tokens ) ) {
			foreach ( $tokens[0] as $token ) {
				$_tokens_interpolates[ $token ] = '<span class="uap-token">
						<span class="uap-token__icon">
							<uo-icon id="bolt"></uo-icon>
						</span>
						<span class="uap-token__name">' . $token . '</span>
					</span>';
			}
		}

		return htmlentities(
			str_replace(
				array( '{{', '}}' ),
				'',
			strtr( $title_html, $_tokens_interpolates )
			),
			ENT_QUOTES
		);
	}

	/**
	 * Resolve condition dynamic sentence template from registry.
	 *
	 * This keeps logs aligned with builder sentences when older recipes have empty
	 * backup metadata (`nameDynamic`/`titleHTML`).
	 *
	 * @param array<string,mixed> $recipe_actions_condition Condition payload.
	 *
	 * @return string
	 */
	protected function resolve_condition_dynamic_name_from_registry( array $recipe_actions_condition ) {
		$integration_code = isset( $recipe_actions_condition['integration'] ) ? (string) $recipe_actions_condition['integration'] : '';
		$condition_code   = isset( $recipe_actions_condition['condition'] ) ? (string) $recipe_actions_condition['condition'] : '';

		if ( '' === $integration_code || '' === $condition_code ) {
			return '';
		}

		$conditions = (array) apply_filters( 'automator_pro_actions_conditions_list', array() );
		$condition  = $conditions[ $integration_code ][ $condition_code ] ?? null;

		if ( ! is_array( $condition ) ) {
			return '';
		}

		$candidates = array(
			$condition['dynamic_name'] ?? '',
			$condition['name_dynamic'] ?? '',
			$condition['name'] ?? '',
		);

		foreach ( $candidates as $candidate ) {
			if ( ! is_string( $candidate ) ) {
				continue;
			}

			$candidate = trim( $candidate );
			if ( '' !== $candidate ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Build fallback dynamic name from saved field labels/readable values.
	 *
	 * @param array<string,mixed> $fields Condition fields.
	 *
	 * @return string
	 */
	protected function build_fallback_dynamic_name( array $fields ) {
		$segments = array();
		$codes    = array();

		foreach ( array_keys( $fields ) as $key ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			if ( str_ends_with( $key, '_label' ) ) {
				$codes[] = substr( $key, 0, -6 );
				continue;
			}

			if ( str_ends_with( $key, '_readable' ) ) {
				$codes[] = substr( $key, 0, -9 );
				continue;
			}

			$codes[] = $key;
		}

		foreach ( array_unique( $codes ) as $code ) {
			$value = $fields[ $code . '_readable' ] ?? ( $fields[ $code ] ?? '' );
			if ( is_array( $value ) || is_object( $value ) ) {
				continue;
			}

			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}

			$label = $fields[ $code . '_label' ] ?? $code;
			if ( is_array( $label ) || is_object( $label ) ) {
				$label = $code;
			}

			$segments[] = sprintf( '{{%s:%s}}', trim( (string) $label ), $code );
		}

		return implode( ' ', $segments );
	}

}
