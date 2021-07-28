<?php

namespace Uncanny_Automator;

/**
 * Class PM_POPUPSHOW
 * @package Uncanny_Automator
 */
class PM_POPUPSHOW {

	/**
	 * Integration code
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



		global $wpdb;

		$automator_popups = $wpdb->get_col( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'popup_settings' AND meta_value LIKE '%automator%'" );

		$args = [
			'post_type'      => 'popup',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
			//'include'        => $automator_popups,
		];

		$options = Automator()->helpers->recipe->options->wp_query( $args, false, esc_attr__( 'Any popup', 'uncanny-automator' ) );

		$option = [
			'option_code'              => 'POPUPID',
			'label'                    => esc_attr__( 'Popup', 'uncanny-automator' ),
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'custom_value_description' => esc_attr__( 'Popup ID', 'uncanny-automator' ),
		];


		$action = array(
			'author'             => 'Uncanny Automator',
			'support_link'       => Automator()->get_author_support_link($this->action_code,'knowledge-base/working-with-popup-maker-actions'),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'requires_user'      => false,
			/* translators: Logged-in trigger - Popup Maker */
			'sentence'           => sprintf( esc_attr__( 'Show {{a popup:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Logged-in trigger - Popup Maker */
			'select_option_name' => esc_attr__( 'Show {{a popup}}', 'uncanny-automator' ),
			'priority'           => 11,
			'accepted_args'      => 3,
			'execution_function' => [ $this, 'display_pop_up' ],
			'options'            => [
				$option,
			],
		);

		Automator()->register->action( $action );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function display_pop_up( $user_id, $action_data, $recipe_id, $args ) {



		$popup_id = absint( $action_data['meta']['POPUPID'] );

		$popup = get_post( $popup_id );


		if ( ! $popup ) {
			$error_message = 'The pop up doesn\'t exist. ID: ' . $popup_id;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		if ( 'publish' !== $popup->post_status ) {
			$error_message = 'The pop was not published or does not exist anymore. ID: ' . $popup_id;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		$found_it = array();

		// update Popup triggers
		$settings = get_post_meta( $popup->ID, 'popup_settings', true );

		if ( isset( $settings['triggers'] ) ) {

			foreach ( $settings['triggers'] as $trigger ) {
				if ( 'automator' === $trigger['type'] ) {
					foreach ( $trigger['settings'] as $key => $setting ) {
						if ( 'recipe' === $key && in_array( $recipe_id, $setting ) ) {
							$found_it = true;
						}
					}
				}
			}
		}

		if ( ! $found_it ) {
			$error_message = 'The pop did no have the associated Recipe set as a pop up trigger. ID: ' . $popup_id;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}


		update_user_meta( $user_id, 'display_pop_up_' . $popup_id, $popup_id );
		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}

	/**
	 *
	 */
	public function automator_option_updated( $return, $item, $meta_key, $meta_value ) {

		$found_it = array();

		if ( isset( $item->post_type ) && 'uo-action' === $item->post_type ) {
			if ( 'POPUPID' === $meta_key ) {

				$popup = get_post( absint( $meta_value ) );
				// update Popup triggers
				$settings = get_post_meta( $popup->ID, 'popup_settings', true );

				if ( isset( $settings['triggers'] ) ) {

					foreach ( $settings['triggers'] as $trigger ) {
						if ( 'automator' === $trigger['type'] ) {
							foreach ( $trigger['settings'] as $key => $setting ) {
								if ( 'recipe' === $key && in_array( $item->post_parent, $setting ) ) {
									$found_it = true;
								}
							}
						}
					}
				}

				if ( ! $found_it ) {
					$settings['triggers'][] = [
						'type'     => 'automator',
						'settings' => [
							'cookie_name' => '',
							'recipe'      => [ $item->post_parent ],
						],
					];

					update_post_meta( $popup->ID, 'popup_settings', $settings );
				}

				return $return;
			}
		}

		return $return;
	}
}
