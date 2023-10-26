<?php

namespace Uncanny_Automator;

use uncanny_learndash_codes;
use \uncanny_learndash_codes\Database;

/**
 * Class UC_GENERATE_CODES
 *
 * @package Uncanny_Automator
 */
class UC_GENERATE_CODES {

	use Recipe\Action_Tokens;

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UNCANNYCODE';
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
		$this->action_code = 'UCGENERATECODES';
		$this->action_meta = 'WPUCGENERATECODES';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/set-up-uncanny-codes-for-wordpress/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Logged-in trigger - Uncanny Codes. */
			'sentence'           => sprintf( esc_attr__( 'Generate {{a batch of codes:%1$s}} for Automator', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Logged-in trigger - Uncanny Codes. */
			'select_option_name' => esc_attr__( 'Generate {{a batch of codes}} for Automator', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'generate_codes' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		$this->set_action_tokens(
			array(
				'BATCH_ID'        => array(
					'name' => __( 'Batch ID', 'uncanny-automator' ),
				),
				'CODES_GENERATED' => array(
					'name' => __( 'Generated codes', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->action_code
		);

		Automator()->register->action( $action );
	}

	/**
	 * load_options
	 */
	public function load_options() {
		$options = array(
			'options_group' => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->field->text_field( 'UCBATCHNAME', esc_attr__( 'Batch name', 'uncanny-automator' ), true, 'text', '', true ),
					Automator()->helpers->recipe->field->text_field( 'UCNOOFCODES', esc_attr__( 'Number of codes', 'uncanny-automator' ), true, 'int', '', true ),
					Automator()->helpers->recipe->field->text_field( 'UCUSERPERCODE', esc_attr__( 'Number of uses per code', 'uncanny-automator' ), true, 'int', 1, true ),
					Automator()->helpers->recipe->field->text_field( 'UCEXPIRYDATE', esc_attr__( 'Expiry date', 'uncanny-automator' ), true, 'date', '', false ),
					Automator()->helpers->recipe->field->text_field( 'UCEXPIRYTIME', esc_attr__( 'Expiry time', 'uncanny-automator' ), true, 'time', '', false ),
					Automator()->helpers->recipe->field->text_field( 'UCPREFIX', esc_attr__( 'Prefix', 'uncanny-automator' ), true, 'text', '', false ),
					Automator()->helpers->recipe->field->text_field( 'UCSUFFIX', esc_attr__( 'Suffix', 'uncanny-automator' ), true, 'text', '', false ),
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
	public function generate_codes( $user_id, $action_data, $recipe_id, $args ) {

		$batch_name         = Automator()->parse->text( $action_data['meta']['UCBATCHNAME'], $recipe_id, $user_id, $args );
		$no_of_codes        = Automator()->parse->text( $action_data['meta']['UCNOOFCODES'], $recipe_id, $user_id, $args );
		$no_of_use_per_code = Automator()->parse->text( $action_data['meta']['UCUSERPERCODE'], $recipe_id, $user_id, $args );
		$expiry_date        = Automator()->parse->text( $action_data['meta']['UCEXPIRYDATE'], $recipe_id, $user_id, $args );
		$expiry_time        = Automator()->parse->text( $action_data['meta']['UCEXPIRYTIME'], $recipe_id, $user_id, $args );
		$prefix             = Automator()->parse->text( $action_data['meta']['UCPREFIX'], $recipe_id, $user_id, $args );
		$suffix             = Automator()->parse->text( $action_data['meta']['UCSUFFIX'], $recipe_id, $user_id, $args );
		$character_type     = array( 'uppercase-letters', 'numbers' );
		$codes              = array();

		$args = array(
			'generation_type' => 'auto',
			'coupon_amount'   => $no_of_codes,
			'custom_codes'    => '',
			'dashes'          => array( 4, 4, 4, 4, 4 ),
			'prefix'          => $prefix,
			'suffix'          => $suffix,
			'code_length'     => 20,
			'character_type'  => $character_type,
		);

		// Sanitize values
		$data = array(
			'coupon-amount'         => $no_of_codes,
			'coupon-prefix'         => $prefix,
			'coupon-suffix'         => $suffix,
			'coupon-dash'           => '4-4-4-4-4',
			'coupon-length'         => '20',
			'generation-type'       => 'auto',
			'dependency'            => 'automator',
			'coupon-for'            => 'automator',
			'group-name'            => $batch_name,
			'coupon-courses'        => '',
			'coupon-group'          => '',
			'expiry-date'           => $expiry_date,
			'expiry-time'           => $expiry_time,
			'coupon-paid-unpaid'    => 'default',
			'coupon-max-usage'      => $no_of_use_per_code,
			'coupon-character-type' => $character_type,
		);

		$data     = apply_filters( 'automator_uo_codes_generate_group_data', $data, $this );
		$group_id = Database::add_code_group_batch( $data );
		$inserted = Database::add_codes_to_batch( $group_id, $codes, $args );

		if ( 0 === $inserted && $inserted !== $no_of_codes ) {
			$error_message = esc_attr__( 'Something went wrong! Codes not generated, Try again.', 'uncanny-automator' );
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		$this->hydrate_tokens(
			array(
				'BATCH_ID'        => $group_id,
				'CODES_GENERATED' => $this->get_generated_codes( $group_id ),
			)
		);

		Automator()->complete_action( $user_id, $action_data, $recipe_id );

	}

	/**
	 * @param $group_id
	 *
	 * @return string
	 */
	private function get_generated_codes( $group_id ) {

		global $wpdb;

		$tbl_codes = $wpdb->prefix . \uncanny_learndash_codes\Config::$tbl_codes;

		$codes = $wpdb->get_col( $wpdb->prepare( "SELECT `code` FROM $tbl_codes WHERE code_group = %d", $group_id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return implode( ', ', $codes );

	}

}
