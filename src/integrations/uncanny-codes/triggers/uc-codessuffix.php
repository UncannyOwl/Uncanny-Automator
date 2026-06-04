<?php

namespace Uncanny_Automator\Integrations\Uncanny_Codes;

/**
 * Class UC_CODESSUFFIX
 *
 * @package Uncanny_Automator
 * @property \Uncanny_Automator\Integrations\Uncanny_Codes\Uc_Helpers $item_helpers
 */
class UC_CODESSUFFIX extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'UCSUFFIX', 'UNCANNYCODE' )
			->trigger_meta( 'UNCANNYCODESSUFFIX' )
			->hook( 'ulc_user_redeemed_code', 20, 3 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_pro( false );
		// translators: %1$s is the suffix.
		$this->set_sentence( sprintf( esc_html_x( 'A user redeems a code with a {{specific:%1$s}} suffix', 'Uncanny Codes', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'A user redeems a code with a {{specific}} suffix', 'Uncanny Codes', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_trigger_meta(),
				'label'                 => esc_html_x( 'Suffix', 'Uncanny Codes', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'suffixes' ),
				'options'               => array(),
			),
		);
	}

	/**
	 * Define available tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			array(
				'tokenId'   => 'CODE_REDEEMED',
				'tokenName' => esc_html_x( 'Code', 'Uncanny Codes', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CODE_BATCH_ID',
				'tokenName' => esc_html_x( 'Batch ID', 'Uncanny Codes', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'REMAINING_CODES',
				'tokenName' => esc_html_x( 'Remaining codes', 'Uncanny Codes', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TOTAL_CODES',
				'tokenName' => esc_html_x( 'Total codes', 'Uncanny Codes', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $user_id, $coupon_id, $result ) = $hook_args;

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( empty( $user_id ) ) {
			return false;
		}

		$selected_suffix = $trigger['meta'][ $this->get_trigger_meta() . '_readable' ] ?? '';

		$suffix = $this->get_suffix_by_coupon( $coupon_id );

		if ( (string) $suffix !== (string) $selected_suffix ) {
			return false;
		}

		return true;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $user_id, $coupon_id, $result ) = $hook_args;

		$code     = $this->item_helpers->uc_get_code_redeemed( $coupon_id );
		$batch_id = $this->get_batch_id_by_coupon( $coupon_id );
		$suffix   = $this->get_suffix_by_coupon( $coupon_id );

		return array(
			$this->get_trigger_meta() => $suffix,
			'CODE_REDEEMED'           => $code,
			'CODE_BATCH_ID'           => $batch_id,
			'REMAINING_CODES'         => $this->get_remaining_codes( $batch_id ),
			'TOTAL_CODES'             => $this->get_total_codes( $batch_id ),
		);
	}

	/**
	 * Get suffix by coupon ID.
	 *
	 * @param int $coupon_id The coupon ID.
	 *
	 * @return string
	 */
	private function get_suffix_by_coupon( $coupon_id ) {

		global $wpdb;

		return (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT g.suffix FROM `{$wpdb->prefix}uncanny_codes_groups` g LEFT JOIN `{$wpdb->prefix}uncanny_codes_codes` c ON g.ID = c.code_group WHERE c.ID = %d",
				$coupon_id
			)
		);
	}

	/**
	 * Get batch ID by coupon ID.
	 *
	 * @param int $coupon_id The coupon ID.
	 *
	 * @return int
	 */
	private function get_batch_id_by_coupon( $coupon_id ) {

		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT g.id
FROM `{$wpdb->prefix}uncanny_codes_groups` g
	LEFT JOIN `{$wpdb->prefix}uncanny_codes_codes` c
		ON g.ID = c.code_group
WHERE c.ID = %d",
				$coupon_id
			)
		);
	}

	/**
	 * Get remaining codes count for a batch.
	 *
	 * @param int $batch_id The batch ID.
	 *
	 * @return int
	 */
	private function get_remaining_codes( $batch_id ) {

		if ( empty( $batch_id ) ) {
			return 0;
		}

		$redeemed_count = absint( \uncanny_learndash_codes\Database::get_group_redeemed_count( $batch_id ) );
		$issue          = absint( \uncanny_learndash_codes\SharedFunctionality::ulc_get_issue_count( $batch_id ) );

		return $issue - $redeemed_count;
	}

	/**
	 * Get total codes count for a batch.
	 *
	 * @param int $batch_id The batch ID.
	 *
	 * @return int
	 */
	private function get_total_codes( $batch_id ) {

		if ( empty( $batch_id ) ) {
			return 0;
		}

		return absint( \uncanny_learndash_codes\SharedFunctionality::ulc_get_issue_count( $batch_id ) );
	}
}
