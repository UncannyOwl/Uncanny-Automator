<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

use uncanny_learndash_groups\SharedFunctions;

/**
 * Class UOG_REMOVESEATSFROMGROUP
 *
 * @package Uncanny_Automator
 */
class UOG_REMOVESEATSFROMGROUP {

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
		$this->action_code = 'REMOVESEATSFROMGROUP';
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
			'sentence'           => sprintf( esc_attr__( 'Remove {{a number of:%1$s}} seats from {{an Uncanny group:%2$s}}', 'uncanny-automator' ), 'NUMOFSEATS', $this->action_meta ),
			/* translators: Logged-in action - Uncanny Groups */
			'select_option_name' => esc_attr__( 'Remove {{a number of}} seats from {{an Uncanny group}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'remove_seats_from_a_group' ),
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
	public function remove_seats_from_a_group( $user_id, $action_data, $recipe_id, $args ) {
		global $wpdb;

		$uo_group_id = Automator()->parse->text( $action_data['meta']['UNCANNYGROUP'], $recipe_id, $user_id, $args );
		$check_group = learndash_validate_groups( array( $uo_group_id ) );
		if ( empty( $check_group ) || ! is_array( $check_group ) ) {
			$error_message                       = esc_html__( 'The selected group is not found.', 'uncanny-automator' );
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}
		$uo_remove_seats = absint( Automator()->parse->text( $action_data['meta']['NUMOFSEATS'], $recipe_id, $user_id, $args ) );

		$code_group_id = ulgm()->group_management->seat->get_code_group_id( $uo_group_id );
		if ( empty( $code_group_id ) ) {
			$error_message                       = __( 'Group management is not enabled on the selected group.', 'uncanny-automator' );
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}
		$existing_seats = ulgm()->group_management->seat->total_seats( $uo_group_id );
		$empty_seats    = ulgm()->group_management->seat->available_seats( $uo_group_id );
		if ( empty( $empty_seats ) ) {
			$error_message                       = __( 'No empty seats in the selected group.', 'uncanny-automator' );
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}
		// Seats removed
		$tbl = SharedFunctions::$db_group_codes_tbl;

		// If seats to remove are less than empty seats
		if ( $uo_remove_seats < $empty_seats ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->prefix{$tbl} WHERE group_id = %d AND student_id IS NULL LIMIT %d", $code_group_id, $uo_remove_seats ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			update_post_meta( $uo_group_id, '_ulgm_total_seats', $empty_seats );
			Automator()->complete_action( $user_id, $action_data, $recipe_id );

			return;
		}
		// if seats to remove are more than empty seats
		if ( $uo_remove_seats >= $empty_seats ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->prefix{$tbl} WHERE group_id = %d AND student_id IS NULL", $code_group_id ) );  //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			update_post_meta( $uo_group_id, '_ulgm_total_seats', $existing_seats - $uo_remove_seats );
		}

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}

}
