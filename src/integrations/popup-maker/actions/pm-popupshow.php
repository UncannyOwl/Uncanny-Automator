<?php

namespace Uncanny_Automator;

/**
 * Class PM_POPUPSHOW
 *
 * @package Uncanny_Automator
 */
class PM_POPUPSHOW {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'PM';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'POPUPSHOW';
		$this->action_meta = 'POPUPID';
		$this->define_action();

		add_filter( 'automator_option_updated', array( $this, 'automator_option_updated' ), 10, 4 );
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$args = array(
			'post_type'      => 'popup',
			'posts_per_page' => 99,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, false, esc_attr__( 'Any popup', 'uncanny-automator' ) );

		$option = array(
			'option_code'              => 'POPUPID',
			'label'                    => esc_attr__( 'Popup', 'uncanny-automator' ),
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'custom_value_description' => esc_attr__( 'Popup ID', 'uncanny-automator' ),
		);

		$action = array(
			'author'             => 'Uncanny Automator',
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/working-with-popup-maker-actions' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'requires_user'      => false,
			/* translators: Logged-in trigger - Popup Maker */
			'sentence'           => sprintf( esc_attr__( 'Show {{a popup:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Logged-in trigger - Popup Maker */
			'select_option_name' => esc_attr__( 'Show {{a popup}}', 'uncanny-automator' ),
			'priority'           => 11,
			'accepted_args'      => 3,
			'execution_function' => array( $this, 'display_pop_up' ),
			'options'            => array(
				$option,
			),
		);

		Automator()->register->action( $action );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function display_pop_up( $user_id, $action_data, $recipe_id, $args ) {

		$popup_id = absint( $action_data['meta']['POPUPID'] );
		$popup    = get_post( $popup_id );

		if ( ! $popup instanceof \WP_Post ) {
			$error_message                       = sprintf( '%s: %d', __( 'The popup no longer exists. Popup ID', 'uncanny-automator' ), $popup_id );
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		// update Popup triggers
		$settings = get_post_meta( $popup->ID, 'popup_settings', true );

		if ( empty( $settings ) || ! isset( $settings['triggers'] ) ) {
			$error_message                       = sprintf( '%s: %d', __( 'No settings found with this popup. Popup ID', 'uncanny-automator' ), $popup_id );
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		$found         = false;
		$found_recipes = array();
		foreach ( $settings['triggers'] as $_trigger ) {
			if ( 'auto_open' !== $_trigger['type'] ) {
				continue;
			}
			if ( ! isset( $_trigger['settings']['recipe'] ) ) {
				continue;
			}
			if ( empty( $_trigger['settings']['recipe'] ) ) {
				continue;
			}
			$found         = true;
			$found_recipes = array_merge( $found_recipes, $_trigger['settings']['recipe'] );
		}

		if ( ! $found || empty( $found_recipes ) ) {
			$error_message                       = sprintf( '%s: %d', __( 'Recipes are not set for this popup. Popup ID', 'uncanny-automator' ), $popup_id );
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		if ( 'publish' !== $popup->post_status || 0 === absint( get_post_meta( $popup_id, 'enabled', true ) ) ) {
			$error_message                       = sprintf( '%s: %d', __( 'The popup is no longer active. Popup ID', 'uncanny-automator' ), $popup_id );
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		$popup_recipes = array_map( 'absint', $found_recipes );

		if ( ! in_array( absint( $recipe_id ), $popup_recipes, true ) ) {
			$error_message                       = sprintf( '%s: %d', __( 'The recipe is not linked with this popup. Popup ID', 'uncanny-automator' ), $popup_id );
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		update_user_meta( $user_id, 'display_pop_up_' . $popup_id, $popup_id );
		Automator()->complete->action( $user_id, $action_data, $recipe_id );
	}

	/**
	 *
	 */
	public function automator_option_updated( $return, $item, $meta_key, $meta_value ) {

		if ( ! isset( $item->post_type ) ) {
			return $return;
		}

		if ( 'uo-action' !== $item->post_type ) {
			return $return;
		}

		if ( is_array( $meta_value ) && ! array_key_exists( 'POPUPID', $meta_value ) ) {
			return $return;
		}

		if ( is_array( $meta_value ) ) {
			$pop_id = $meta_value['POPUPID'];
		} else {
			$pop_id = $meta_value;
		}

		$popup = get_post( $pop_id );
		if ( ! $popup instanceof \WP_Post ) {
			return $return;
		}

		// update Popup triggers
		$settings = get_post_meta( $pop_id, 'popup_settings', true );
		$found_it = false;
		if ( isset( $settings['triggers'] ) ) {
			foreach ( $settings['triggers'] as $trigger ) {
				if ( 'auto_open' !== $trigger['type'] ) {
					continue;
				}
				if ( ! isset( $trigger['settings']['recipe'] ) ) {
					continue;
				}
				if ( empty( $trigger['settings']['recipe'] ) ) {
					continue;
				}
				$popup_recipes = array_map( 'absint', $trigger['settings']['recipe'] );
				if ( ! in_array( absint( $item->post_parent ), $popup_recipes, true ) ) {
					continue;
				}
				$found_it = true;
			}
		}
		if ( $found_it ) {
			return $return;
		}

		$settings['triggers'][] = array(
			'type'     => 'auto_open',
			'settings' => array(
				'delay'  => '500',
				'recipe' => array( $item->post_parent ),
			),
		);

		update_post_meta( $pop_id, 'popup_settings', $settings );

		return $return;
	}
}
