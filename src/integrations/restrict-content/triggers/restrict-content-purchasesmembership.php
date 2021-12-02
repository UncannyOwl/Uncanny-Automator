<?php

namespace Uncanny_Automator;

/**
 * Class RESTRICT_CONTENT_PURCHASESMEMBERSHIP
 *
 * @package Uncanny_Automator
 */
class RESTRICT_CONTENT_PURCHASESMEMBERSHIP {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'RC';

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
		$this->trigger_code = 'RCPURCHASESMEMBERSHIP';
		$this->trigger_meta = 'RCMEMBERSHIPLEVEL';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/restrict-content/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Wishlist Member */
			'sentence'            => sprintf( esc_attr__( 'A user purchases {{a membership level:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Wishlist Member */
			'select_option_name'  => esc_attr__( 'A user purchases {{a membership level}}', 'uncanny-automator' ),
			'action'              => 'rcp_membership_post_activate',
			'priority'            => 5,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'user_purchases_membership_level' ),
			'options'             => array(
				Automator()->helpers->recipe->restrict_content->options->get_membership_levels(
					null,
					$this->trigger_meta,
					array( 'any' => true )
				),
			),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * @param int $membership_id ID of the membership.
	 * @param \RCP_Membership $membership Membership object.
	 */
	public function user_purchases_membership_level( $membership_id, \RCP_Membership $RCP_Membership ) {

		$user_id = $RCP_Membership->get_user_id();

		if ( ! $user_id ) {
			return;
		}

		$level_id = $RCP_Membership->get_object_id();

		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_level     = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();

		//Add where Membership Level is set for trigger
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( $required_level[ $recipe_id ][ $trigger_id ] === '-1' || $required_level[ $recipe_id ][ $trigger_id ] === $level_id ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {

				$pass_args = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
				);

				$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {

							// Add token for options
							Automator()->insert_trigger_meta(
								array(
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => $this->trigger_meta . '_MEMBERSHIPID ',
									'meta_value'     => $membership_id,
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								)
							);

							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}

		return;

	}
}
