<?php

namespace Uncanny_Automator;

/**
 * Class UC_ANON_CODEBATCHCREATED
 *
 * @package Uncanny_Automator
 */
class UC_ANON_CODEBATCHCREATED {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UNCANNYCODE';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'ANONCODEBATCHCREATED';
		$this->trigger_meta = 'UNCANNYCODES';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/uncanny-codes/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'meta'                => $this->trigger_meta,
			/* translators: Logged-in trigger - Uncanny Codes */
			'sentence'            => sprintf( esc_attr__( 'A code batch is created', 'uncanny-automator' ) ),
			/* translators: Logged-in trigger - Uncanny Codes */
			'select_option_name'  => esc_attr__( 'A code batch is created', 'uncanny-automator' ),
			'action'              => 'ulc_codes_group_generated',
			'type'                => 'anonymous',
			'priority'            => 20,
			'accepted_args'       => 1,
			'validation_function' => array(
				$this,
				'ulc_codes_group_generated',
			),
			'options'             => array(),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @param $user_id
	 * @param $coupon_id
	 * @param $result
	 */
	public function ulc_codes_group_generated( $batch_id ) {

		if ( empty( $batch_id ) ) {
			return;
		}

		$user_id = get_current_user_id();

		$args = array(
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'post_id'        => - 1,
			'ignore_post_id' => true,
			'user_id'        => $user_id,
		);

		$args = Automator()->maybe_add_trigger_entry( $args, false );

		// Save trigger meta
		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] && $result['args']['trigger_id'] && $result['args']['get_trigger_id'] ) {

					$run_number = Automator()->get->trigger_run_number( $result['args']['trigger_id'], $result['args']['get_trigger_id'], $user_id );
					$save_meta  = array(
						'user_id'        => $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'run_number'     => $run_number, //get run number
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'ignore_user_id' => true,
					);

					// Batch ID
					Automator()->db->token->save( 'UNCANNYCODESBATCH_ID', $batch_id, $save_meta );

					$batch_content = Automator()->helpers->recipe->uncanny_codes->options->uc_get_batch_info( $batch_id );
					// Code Type
					Automator()->db->token->save( 'UNCANNYCODESTYPE', $batch_content['batch_data']->paid_unpaid, $save_meta );

					// Prefix
					Automator()->db->token->save( 'UNCANNYCODESPREFIXBATCH', $batch_content['batch_data']->prefix, $save_meta );

					// Suffix
					Automator()->db->token->save( 'UNCANNYCODESSUFFIXBATCH', $batch_content['batch_data']->suffix, $save_meta );

					// LD Type
					Automator()->db->token->save( 'UNCANNYCODESLD_TYPE', ucfirst( $batch_content['batch_data']->code_for ), $save_meta );

					// Match Per Code
					Automator()->db->token->save( 'UNCANNYCODESMAX_PER_CODE', $batch_content['batch_data']->issue_max_count, $save_meta );

					// Codes generated
					Automator()->db->token->save( 'UNCANNYCODESCODES_GENERATED', $batch_content['batch_data']->issue_count, $save_meta );

					// Expiry date
					Automator()->db->token->save( 'UNCANNYCODESEXPIRY_DATE', $batch_content['batch_data']->expire_date, $save_meta );

					// List of codes
					Automator()->db->token->save( 'UNCANNYCODESLIST_OF_CODES', $batch_content['codes_data']->codes, $save_meta );

					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}

	}

}
