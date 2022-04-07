<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class UOG_ADDSEATSTOGROUP
 *
 * @package Uncanny_Automator
 */
class UOG_ADDSEATSTOGROUP {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UOG';
	/**
	 * @var
	 */
	public static $number_of_keys;
	/**
	 * @var string
	 */
	private $action_code;
	/**
	 * @var string
	 */
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'ADDSEATSTOGROUP';
		$this->action_meta = 'UNCANNYGROUP';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/uncanny-groups/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Logged-in action - Uncanny Groups */
			'sentence'           => sprintf( esc_attr__( 'Add {{a number of:%1$s}} seats to {{an Uncanny group:%2$s}}', 'uncanny-automator' ), 'NUMOFSEATS', $this->action_meta ),
			/* translators: Logged-in action - Uncanny Groups */
			'select_option_name' => esc_attr__( 'Add {{a number of}} seats to {{an Uncanny group}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'add_seats_to_a_group' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );
	}

	/**
	 * @return array
	 */
	public function load_options() {
		$options = array(
			'options_group' => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->uncanny_groups->options->all_ld_groups( '', 'UNCANNYGROUP', false ),
				),
				'NUMOFSEATS'       => array(
					array(
						'input_type'      => 'int',
						'option_code'     => 'NUMOFSEATS',
						'label'           => esc_attr__( 'Quantity', 'uncanny-automator' ),
						'supports_tokens' => true,
						'required'        => true,
					),
				),
			),
		);

		return Automator()->utilities->keep_order_of_options( $options );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function add_seats_to_a_group( $user_id, $action_data, $recipe_id, $args ) {

		$uo_group_id = Automator()->parse->text( $action_data['meta']['UNCANNYGROUP'], $recipe_id, $user_id, $args );
		$check_group = learndash_validate_groups( array( $uo_group_id ) );
		if ( empty( $check_group ) || ! is_array( $check_group ) ) {
			$error_message                       = esc_html__( 'The selected group is not found.', 'uncanny-automator' );
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		$uo_group_num_seats = absint( Automator()->parse->text( $action_data['meta']['NUMOFSEATS'], $recipe_id, $user_id, $args ) );
		$code_group_id      = ulgm()->group_management->seat->get_code_group_id( $uo_group_id );
		if ( empty( $code_group_id ) ) {
			$error_message                       = __( 'Group management is not enabled on the selected group.', 'uncanny-automator' );
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}
		$existing_seats = ulgm()->group_management->seat->total_seats( $uo_group_id );

		// Seats added
		if ( $uo_group_num_seats > 0 ) {
			$new_seats = $existing_seats + $uo_group_num_seats;
			$new_codes = ulgm()->group_management->generate_random_codes( $uo_group_num_seats );

			$attr = array(
				'qty'           => $uo_group_num_seats,
				'code_group_id' => $code_group_id,
			);
			ulgm()->group_management->add_additional_codes( $attr, $new_codes );
			update_post_meta( $uo_group_id, '_ulgm_total_seats', $new_seats );
		}

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}

}
