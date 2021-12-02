<?php

namespace Uncanny_Automator;

/**
 * Class MYCRED_AWARDPOINTS_A
 *
 * @package Uncanny_Automator
 */
class MYCRED_AWARDPOINTS_A {

	/**
	 * integration code
	 *
	 * @var string
	 */
	public static $integration = 'MYCRED';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'MYCREDAWARDPOINTS';
		$this->action_meta = 'MYCREDPOINTS';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/mycred/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - myCred */
			'sentence'           => sprintf( esc_attr__( 'Award {{a number of:%1$s}} points to the user', 'uncanny-automator' ), 'MYCREDPOINTVALUE' ),
			/* translators: Action - myCred */
			'select_option_name' => esc_attr__( 'Award {{a number of}} points to the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'award_mycred_points' ),
			'options'            => array(),
			'options_group'      => array(
				'MYCREDPOINTVALUE' => array(
					Automator()->helpers->recipe->mycred->options->list_mycred_points_types(
						esc_attr__( 'Point type', 'uncanny-automator' ),
						$this->action_meta,
						array(
							'token'   => false,
							'is_ajax' => false,
						)
					),
					array(
						'input_type'      => 'float',
						'option_code'     => 'MYCREDPOINTVALUE',
						'label'           => esc_attr__( 'Points', 'uncanny-automator' ),
						'supports_tokens' => true,
						'required'        => true,
					),
					array(
						'input_type'      => 'text',
						'option_code'     => 'MYCREDDESCRIPTION',
						'label'           => __( 'Description', 'uncanny-automator' ),
						'description'     => __( 'If this is left blank, the description "Revoked by Uncanny Automator" will be used', 'uncanny-automator' ),
						'supports_tokens' => true,
						'required'        => false,
					),
				),
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
	 */
	public function award_mycred_points( $user_id, $action_data, $recipe_id, $args ) {

		$points_type = $action_data['meta'][ $this->action_meta ];

		$description = __( 'Awarded by Uncanny Automator', 'uncanny-automator' );

		if ( ! empty( $action_data['meta']['MYCREDDESCRIPTION'] ) ) {
			$description = Automator()->parse->text( $action_data['meta']['MYCREDDESCRIPTION'], $recipe_id, $user_id, $args );
		}

		$points    = Automator()->parse->text( $action_data['meta']['MYCREDPOINTVALUE'], $recipe_id, $user_id, $args );
		$reference = Automator()->parse->text( $action_data['meta']['MYCREDPOINTS_readable'], $recipe_id, $user_id, $args );
		mycred_add( $reference, absint( $user_id ), $points, $description, '', '', $points_type );

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}
}
