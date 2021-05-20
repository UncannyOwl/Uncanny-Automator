<?php
namespace Uncanny_Automator;

/**
 * Fcrm_Status_Helper
 *
 * This class contains helper methods for our status trigger.
 */
class Fcrm_Status_Helpers {

	public static $has_run = false;

	/**
	 * Our class construction. Attach the event to change status.
	 *
	 * @return void
	 */
	public function __construct() {



		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		$fluent_crm_targetted_actions = array(
			'fluentcrm_subscriber_status_to_subscribed',
			'fluentcrm_subscriber_status_to_pending',
			'fluentcrm_subscriber_status_to_unsubscribed',
			'fluentcrm_subscriber_status_to_bounced',
			'fluentcrm_subscriber_status_to_complained',
		);

		foreach ( $fluent_crm_targetted_actions as $status_action ) {
			add_action( $status_action, array( $this, 'do_fluent_crm_actions' ), 2, 99 );
		}

	}

	/**
	 * @param Fcrm_Status_Helpers $options
	 */
	public function setOptions( Fcrm_Status_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Fluent_Crm_Pro_Helpers $pro
	 */
	public function setPro( Fluent_Crm_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * Our callback function to attach the trigger 'automator_fluentcrm_status_update'.
	 *
	 * @param  mixed $subscriber The accepted subscriber object from status_action.
	 * @param  string $old_status The old status.
	 * @return void
	 */
	public function do_fluent_crm_actions( $subscriber, $old_status ) {
		// Make sure to only trigger once. For some reason, Fluent CRM is triggering this twice.
		if ( ! self::$has_run ) {
			do_action( 'automator_fluentcrm_status_update', $subscriber, $old_status );
			self::$has_run = true;
		}
	}


	/**
	 * Get the matching recipe ids.
	 *
	 * @param mixed $uncanny_automator The uncanny_automator global object.
	 * @param mixed $trigger The trigger. Must be an instance of Uncanny_Automator\FCRM_USER_STATUS_UPDATED.
	 * @param mixed $subscriber The subscriber object.
	 *
	 * @return array The matching recipe ids.
	 */
	public function get_matched_recipes_ids( $uncanny_automator, FCRM_USER_STATUS_UPDATED $trigger, $subscriber = null ) {

		$recipes = Automator()->get->recipes_from_trigger_code( $trigger->get_trigger_code() );

		$status = Automator()->get->meta_from_recipes( $recipes, $trigger->get_trigger_code() );

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
	public function process_trigger( $matched_recipe_ids = array(), $uncanny_automator, FCRM_USER_STATUS_UPDATED $trigger, $subscriber = null ) {

		if ( ! empty( $matched_recipe_ids ) ) {

			foreach ( $matched_recipe_ids as $matched_recipe_id ) {

				$args = array(
					'code'             => $trigger->get_trigger_code(),
					'meta'             => $trigger->get_trigger_meta(),
					'user_id'          => $subscriber->user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
				);

				$args = Automator()->maybe_add_trigger_entry( $args, false );

				if ( $args ) {

					foreach ( $args as $result ) {

						if ( true === $result['result'] && $result['args']['trigger_id'] && $result['args']['get_trigger_id'] ) {

							Automator()->maybe_trigger_complete( $result['args'] );

						}
					}
				}
			}
		}
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
		$mappable_fields = (array) \FluentCrm\App\Models\Subscriber::mappables();

		$tokens['FLUENTCRM_STATUS_FIELD_status'] = esc_attr__( 'Subscription Status', 'uncanny-automator' );

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
}
