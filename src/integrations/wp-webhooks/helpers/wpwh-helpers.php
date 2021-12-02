<?php


namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Wpwh_Pro_Helpers;

/**
 * Class Wpwh_Helpers
 *
 * @package Uncanny_Automator
 */
class Wpwh_Helpers {

	/**
	 * @var Wpwh_Helpers
	 */
	public $options;
	/**
	 * @var Wpwh_Helpers
	 */
	public $pro;
	/**
	 * @var bool
	 */
	public $load_options;


	/**
	 * Wpwh_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

	}

	/**
	 * @param Wpwh_Helpers $options
	 */
	public function setOptions( Wpwh_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Wpwh_Pro_Helpers $pro
	 */
	public function setPro( Wpwh_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}


	/**
	 * @param null   $label
	 * @param string $option_code
	 * @param array  $args
	 *
	 * @return mixed|void
	 */
	public function list_webhook_triggers( $label = null, $option_code = 'WPWHTRIGGER', $args = array() ) {

		if ( ! $label ) {
			$label = __( 'Webhook triggers', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';

		$options       = array();
		$options['-1'] = __( 'Any trigger', 'uncanny-automator' );

		$triggers        = WPWHPRO()->webhook->get_triggers();
		$active_webhooks = WPWHPRO()->settings->get_active_webhooks( 'all' );

		foreach ( $triggers as $trigger ) {
			if ( isset( $active_webhooks['triggers'][ $trigger['trigger'] ] ) ) {
				$options[ $trigger['trigger'] ] = $trigger['name'];
			}
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
			'relevant_tokens' => array(),
		);

		return apply_filters( 'uap_option_list_webhook_triggers', $option );
	}

	/**
	 * Match condition for form field and value.
	 *
	 * @param             $action
	 * @param null|array  $recipes             .
	 * @param null|string $trigger_meta        .
	 * @param null|string $trigger_code        .
	 * @param null|string $trigger_second_code .
	 *
	 * @return array|bool
	 */
	public function match_action_condition( $action, $recipes = null, $trigger_meta = null, $trigger_code = null, $trigger_second_code = null ) {
		if ( null === $recipes ) {
			return false;
		}

		$recipe_ids = array();

		//Limiting to specific recipe IDs
		foreach ( $recipes as $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				if ( key_exists( $trigger_meta, $trigger['meta'] ) && $trigger['meta'][ $trigger_meta ] === $action ) {
					$recipe_ids[ $recipe['ID'] ] = $recipe['ID'];
					break;
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
	 * @param $entry_id
	 * @param $form_id
	 * @param $args
	 *
	 * @return array
	 */
	public function extract_and_save_data( $params, $args ) {
		$data = $params;

		$trigger_id     = (int) $args['trigger_id'];
		$user_id        = (int) $args['user_id'];
		$trigger_log_id = (int) $args['trigger_log_id'];
		$run_number     = (int) $args['run_number'];
		$meta_key       = (string) $args['meta_key'];

		if ( $data ) {

			$insert = array(
				'user_id'        => $user_id,
				'trigger_id'     => $trigger_id,
				'trigger_log_id' => $trigger_log_id,
				'meta_key'       => $meta_key,
				'meta_value'     => maybe_serialize( $data ),
				'run_number'     => $run_number,
			);

			Automator()->insert_trigger_meta( $insert );
		}

		return $data;
	}

	/**
	 * @param $parent
	 *
	 * @return array
	 */
	public function XML2Array( \SimpleXMLElement $parent ) {
		$array = array();

		foreach ( $parent as $name => $element ) {
			( $node = &$array[ $name ] )
			&& ( 1 === count( $node ) ? $node = array( $node ) : 1 )
			&& $node = &$node[];

			$node = $element->count() ? $this->XML2Array( $element ) : trim( $element );
		}

		return $array;
	}
}
