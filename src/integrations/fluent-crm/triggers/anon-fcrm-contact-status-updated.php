<?php

namespace Uncanny_Automator;

/**
 * Class ANON_FCRM_CONTACT_STATUS_UPDATED
 *
 * @package Uncanny_Automator
 */
class ANON_FCRM_CONTACT_STATUS_UPDATED {

	/**
	 * Integration code.
	 *
	 * @var string
	 */
	public static $integration = 'FCRM';

	/**
	 * The trigger code.
	 *
	 * @var string
	 */
	protected $trigger_code;

	/**
	 * The trigger meta.
	 *
	 * @var string
	 */
	protected $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->trigger_code = 'ANONFCRMUSERSTATUSUPDATED';
		$this->trigger_meta = 'FCRMUSERUPDATEDSTATUS';
		$this->define_trigger();
	}

	public function get_trigger_code() {
		return $this->trigger_code;
	}

	public function get_trigger_meta() {
		return $this->trigger_meta;
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object.
	 *
	 * @return void
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/fluentcrm/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'type'                => 'anonymous',
			'is_pro'              => false,
			/* translators: Logged-in trigger - Fluent Forms */
			'sentence'            => sprintf( esc_html__( 'A contact is set to a {{specific status:%1$s}}', 'uncanny-automator' ), $this->trigger_code ),
			/* translators: Logged-in trigger - Fluent Forms */
			'select_option_name'  => esc_html__( 'A contact is set to a {{specific status}}', 'uncanny-automator' ),
			'action'              => 'automator_fluentcrm_status_update',
			'priority'            => 200,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'contact_status_updated' ),
			'options'             => array(
				array(
					'input_type'               => 'select',
					'option_code'              => $this->trigger_code,
					'options'                  => Automator()->helpers->recipe->fluent_crm->get_subscriber_statuses(),
					'required'                 => true,
					'label'                    => esc_html__( 'List of all available status values for Fluent CRM contacts.', 'uncanny-automator' ),
					'description'              => esc_html__( 'Select from dropdown list of the options above.', 'uncanny-automator' ),
					'supports_token'           => true,
					'supports_multiple_values' => false,
					'supports_custom_value'    => false,
					'relevant_tokens'          => $this->get_tokens(),
				),
			),
		);

		Automator()->register->trigger( $trigger );

	}

	/*
	 * Callback function to define trigger.
	 */
	public function contact_status_updated( $subscriber, $old_value ) {
		$matched_recipe_ids = $this->get_matched_recipes_ids( $subscriber );
		$this->process_trigger( $matched_recipe_ids, $subscriber );
	}

	/**
	 * Returns the tokens.
	 *
	 * @return array The tokens.
	 */
	public function get_tokens() {
		$token_id = 'FLUENTCRM_STATUS_FIELD_';
		if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			$tokens = array();
		}
		// Regular contact profile fields.
		$mappable_fields                         = (array) \FluentCrm\App\Models\Subscriber::mappables();
		$tokens['FLUENTCRM_STATUS_FIELD_status'] = esc_attr__( 'Subscription status', 'uncanny-automator' );
		foreach ( $mappable_fields as $field_id => $field_label ) {
			$tokens[ $token_id . $field_id ] = $field_label;
		}
		// Custom contact profile fields.
		$custom_fields = new \FluentCrm\App\Models\CustomContactField();
		$custom_fields = $custom_fields->getGlobalFields()['fields'];
		if ( ! empty( $custom_fields ) ) {
			foreach ( $custom_fields as $custom_field ) {
				$tokens[ $token_id . $custom_field['slug'] ] = $custom_field['label'];
			}
		}

		return $tokens;
	}

	/**
	 * Processes our trigger.
	 *
	 * @param array $matched_recipe_ids The matching recipe ids.
	 * @param object $uncanny_automator The Automator's object.
	 * @param \Uncanny_Automator\FCRM_USER_STATUS_UPDATED $trigger The trigger.
	 * @param object $subscriber The subscriber object.
	 *
	 * @return void
	 */
	public function process_trigger( $matched_recipe_ids = array(), $subscriber = null ) {

		$user_id = $subscriber->user_id;

		// Fluent CRM contact email.
		$contact_email = '';

		if ( isset( $subscriber->email ) ) {
			$contact_email = $subscriber->email;
		}

		if ( ! empty( $matched_recipe_ids ) ) {

			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$args = array(
					'code'             => $this->get_trigger_code(),
					'meta'             => $this->get_trigger_meta(),
					'user_id'          => absint( $user_id ),
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
				);

				$args = Automator()->maybe_add_trigger_entry( $args, false );

				if ( $args ) {

					foreach ( $args as $result ) {

						if ( true === $result['result'] && $result['args']['trigger_id'] && $result['args']['trigger_log_id'] ) {

							// The contact email meta config.
							$contact_email_meta = array(
								'user_id'        => absint( $user_id ),
								'trigger_id'     => $result['args']['trigger_id'],
								'run_number'     => $result['args']['run_number'],
								'trigger_log_id' => $result['args']['trigger_log_id'],
								'meta_key'       => $this->get_trigger_meta(),
								'meta_value'     => $contact_email,
							);

							// Add contact email as trigger meta.
							Automator()->insert_trigger_meta( $contact_email_meta );

							// Complete the trigger.
							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}
	}

	/**
	 * Get the matching recipe ids.
	 *
	 * @param mixed $uncanny_automator The uncanny_automator global object.
	 * @param mixed $trigger The trigger. Must be an instance of Uncanny_Automator\ANON_FCRM_CONTACT_STATUS_UPDATED.
	 * @param mixed $subscriber The subscriber object.
	 *
	 * @return array The matching recipe ids.
	 */
	public function get_matched_recipes_ids( $subscriber = null ) {
		$recipes            = Automator()->get->recipes_from_trigger_code( $this->get_trigger_code() );
		$status             = Automator()->get->meta_from_recipes( $recipes, $this->get_trigger_code() );
		$matched_recipe_ids = array();
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				// Check to see if trigger matches `Any` trigger or a specific Event.
				$selected_option = $status[ $recipe_id ][ $trigger_id ];
				if ( intval( '-1' ) === intval( $selected_option ) || $selected_option === $subscriber->status ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

		return $matched_recipe_ids;
	}

}
