<?php

namespace Uncanny_Automator\Integrations\Uncanny_Codes;

/**
 * Class UC_ANON_CODEBATCHCREATED
 *
 * @package Uncanny_Automator
 * @property \Uncanny_Automator\Integrations\Uncanny_Codes\Uc_Helpers $item_helpers
 */
class UC_ANON_CODEBATCHCREATED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'ANONCODEBATCHCREATED', 'UNCANNYCODE' )
			->trigger_meta( 'UNCANNYCODES' )
			->trigger_type( 'anonymous' )
			->hook( 'ulc_codes_group_generated', 20, 1 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		/* translators: Anonymous trigger - Uncanny Codes */
		$this->set_sentence( esc_html_x( 'A code batch is created', 'Uncanny Codes', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A code batch is created', 'Uncanny Codes', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array();
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
				'tokenId'   => 'CODE_BATCH_ID',
				'tokenName' => esc_html_x( 'Batch ID', 'Uncanny Codes', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'UNCANNYCODESBATCH_ID',
				'tokenName' => esc_html_x( 'Batch ID', 'Uncanny Codes', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'UNCANNYCODESTYPE',
				'tokenName' => esc_html_x( 'Type', 'Uncanny Codes', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'UNCANNYCODESPREFIXBATCH',
				'tokenName' => esc_html_x( 'Prefix', 'Uncanny Codes', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'UNCANNYCODESSUFFIXBATCH',
				'tokenName' => esc_html_x( 'Suffix', 'Uncanny Codes', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'UNCANNYCODESLD_TYPE',
				'tokenName' => esc_html_x( 'LD Type', 'Uncanny Codes', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'UNCANNYCODESMAX_PER_CODE',
				'tokenName' => esc_html_x( 'Max per code', 'Uncanny Codes', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'UNCANNYCODESCODES_GENERATED',
				'tokenName' => esc_html_x( 'Codes generated', 'Uncanny Codes', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'UNCANNYCODESEXPIRY_DATE',
				'tokenName' => esc_html_x( 'Expiry date', 'Uncanny Codes', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'UNCANNYCODESLIST_OF_CODES',
				'tokenName' => esc_html_x( 'Codes (CSV list of codes)', 'Uncanny Codes', 'uncanny-automator' ),
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

		list( $batch_id ) = $hook_args;

		if ( empty( $batch_id ) ) {
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

		list( $batch_id ) = $hook_args;

		$batch_content = $this->item_helpers->uc_get_batch_info( $batch_id );

		$batch_data = $batch_content['batch_data'];
		$codes_data = $batch_content['codes_data'];

		return array(
			'CODE_BATCH_ID'                => $batch_id,
			'UNCANNYCODESBATCH_ID'         => $batch_id,
			'UNCANNYCODESTYPE'             => isset( $batch_data->paid_unpaid ) ? $batch_data->paid_unpaid : '',
			'UNCANNYCODESPREFIXBATCH'      => isset( $batch_data->prefix ) ? $batch_data->prefix : '',
			'UNCANNYCODESSUFFIXBATCH'      => isset( $batch_data->suffix ) ? $batch_data->suffix : '',
			'UNCANNYCODESLD_TYPE'          => isset( $batch_data->code_for ) ? ucfirst( $batch_data->code_for ) : '',
			'UNCANNYCODESMAX_PER_CODE'     => isset( $batch_data->issue_max_count ) ? $batch_data->issue_max_count : '',
			'UNCANNYCODESCODES_GENERATED'  => isset( $batch_data->issue_count ) ? $batch_data->issue_count : '',
			'UNCANNYCODESEXPIRY_DATE'      => isset( $batch_data->expire_date ) ? $batch_data->expire_date : '',
			'UNCANNYCODESLIST_OF_CODES'    => isset( $codes_data->codes ) ? $codes_data->codes : '',
		);
	}
}
