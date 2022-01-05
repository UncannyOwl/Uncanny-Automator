<?php

namespace Uncanny_Automator;

/**
 * Class ANON_FCRM_ADDED_TO_LIST
 *
 * @package Uncanny_Automator
 */
class ANON_FCRM_ADDED_TO_LIST {

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
		$this->trigger_code = 'ANONFCRMUSERLIST';
		$this->trigger_meta = 'FCRMLIST';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/fluentcrm/' ),
			'type'                => 'anonymous',
			'is_pro'              => false,
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Fluent Forms */
			'sentence'            => sprintf( esc_attr_x( 'A contact is added to {{a list:%1$s}}', 'Fluent Forms', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Fluent Forms */
			'select_option_name'  => esc_attr_x( 'A contact is added to {{a list}}', 'Fluent Forms', 'uncanny-automator' ),
			'action'              => 'fluentcrm_contact_added_to_lists',
			'priority'            => 20,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'contact_added_to_lists' ),
			'options'             => array(
				Automator()->helpers->recipe->fluent_crm->options->fluent_crm_lists( null, $this->trigger_meta ),
			),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * @param $attached_list_ids
	 * @param $subscriber
	 */
	public function contact_added_to_lists( $attached_list_ids, $subscriber ) {

		$user_id  = $subscriber->user_id;
		$list_ids = Automator()->helpers->recipe->fluent_crm->get_attached_list_ids( $attached_list_ids );

		if ( empty( $list_ids ) ) {
			// sanity check
			return;
		}

		$matched_recipes = Automator()->helpers->recipe->fluent_crm->match_single_condition( $list_ids, 'int', $this->trigger_meta, $this->trigger_code );

		if ( ! empty( $matched_recipes ) ) {
			foreach ( $matched_recipes as $matched_recipe ) {
				if ( ! Automator()->is_recipe_completed( $matched_recipe->recipe_id, $user_id ) ) {
					$args   = array(
						'code'            => $this->trigger_code,
						'meta'            => $this->trigger_meta,
						'recipe_to_match' => $matched_recipe->recipe_id,
						'ignore_post_id'  => true,
						'user_id'         => $user_id,
					);
					$result = Automator()->maybe_add_trigger_entry( $args, false );
					if ( $result ) {
						foreach ( $result as $r ) {
							if ( true === $r['result'] ) {
								if ( isset( $r['args'] ) && isset( $r['args']['get_trigger_id'] ) ) {
									$trigger_meta = array(
										'user_id'        => $user_id,
										'trigger_id'     => (int) $r['args']['trigger_id'],
										'trigger_log_id' => $r['args']['get_trigger_id'],
										'run_number'     => $r['args']['run_number'],
									);

									$trigger_meta['meta_key']   = $this->trigger_meta;
									$trigger_meta['meta_value'] = maybe_serialize( $matched_recipe->matched_value );
									Automator()->insert_trigger_meta( $trigger_meta );

									$trigger_meta['meta_key']   = 'subscriber_id';
									$trigger_meta['meta_value'] = maybe_serialize( $subscriber->id );
									Automator()->insert_trigger_meta( $trigger_meta );
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
