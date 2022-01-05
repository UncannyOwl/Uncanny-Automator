<?php

namespace Uncanny_Automator;

/**
 * Class FCRM_ADD_USER_TO_TAG
 *
 * @package Uncanny_Automator
 */
class FCRM_TAG_ADDED_TO_USER {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'FCRM';

	/**
	 * @var string
	 */
	private $trigger_code;
	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'FCRMUSERTAG';
		$this->trigger_meta = 'FCRMTAG';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/fluentcrm/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Fluent Forms */
			'sentence'            => sprintf( esc_attr_x( '{{A tag:%1$s}} is added to a user', 'Fluent Forms', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Fluent Forms */
			'select_option_name'  => esc_attr_x( '{{A tag}} is added to a user', 'Fluent Forms', 'uncanny-automator' ),
			'action'              => 'fluentcrm_contact_added_to_tags',
			'priority'            => 20,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'contact_added_to_tags' ),
			'options'             => array(
				Automator()->helpers->recipe->fluent_crm->options->fluent_crm_tags(),
			),
		);

		Automator()->register->trigger( $trigger );

		return;
	}


	/**
	 * @param $attached_tag_ids
	 * @param $subscriber
	 *
	 * @return void
	 */
	public function contact_added_to_tags( $attached_tag_ids, $subscriber ) {

		$user_id = $subscriber->user_id;

		if ( 0 === $user_id ) {
			// There is no wp user associated with the subscriber
			return;
		}

		$tag_ids = Automator()
			->helpers
			->recipe
			->fluent_crm
			->get_attached_tag_ids( $attached_tag_ids );

		if ( empty( $tag_ids ) ) {
			// sanity check
			return;
		}

		$matched_recipes = Automator()
			->helpers
			->recipe
			->fluent_crm
			->match_single_condition( $tag_ids, 'int', $this->trigger_meta, $this->trigger_code );

		if ( ! empty( $matched_recipes ) ) {
			foreach ( $matched_recipes as $matched_recipe ) {
				if ( ! Automator()->is_recipe_completed( $matched_recipe->recipe_id, $user_id ) ) {

					$args = array(
						'code'            => $this->trigger_code,
						'meta'            => $this->trigger_meta,
						'recipe_to_match' => $matched_recipe->recipe_id,
						'ignore_post_id'  => true,
						'user_id'         => $user_id,
						'is_signed_in'    => true,
					);

					$result = Automator()->maybe_add_trigger_entry( $args, false );

					if ( $result ) {
						foreach ( $result as $r ) {
							if ( true === $r['result'] ) {
								if ( isset( $r['args'] ) && isset( $r['args']['get_trigger_id'] ) ) {

									$insert = array(
										'user_id'        => $user_id,
										'trigger_id'     => (int) $r['args']['trigger_id'],
										'trigger_log_id' => $r['args']['get_trigger_id'],
										'meta_key'       => $this->trigger_meta,
										'meta_value'     => maybe_serialize( $matched_recipe->matched_value ),
										'run_number'     => $r['args']['run_number'],
									);

									Automator()->insert_trigger_meta( $insert );

									$insert = array(
										'user_id'        => $user_id,
										'trigger_id'     => (int) $r['args']['trigger_id'],
										'trigger_log_id' => $r['args']['get_trigger_id'],
										'meta_key'       => 'subscriber_id',
										'meta_value'     => $subscriber->id,
										'run_number'     => $r['args']['run_number'],
									);

									Automator()->insert_trigger_meta( $insert );
								}

								Automator()->maybe_trigger_complete( $r['args'] );
							}
						}
					}
				}
			}
		}
	}
}
