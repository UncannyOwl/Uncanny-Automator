<?php

namespace Uncanny_Automator\Integrations\Gravity_Forms;

class Gravity_Forms_Tokens {

	/**
	 * @var Gravity_Forms_Tokens_Parser
	 */
	public $parser;

	/**
	 * @var Gravity_Forms_Possible_Tokens
	 */
	public $possible_tokens;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		$this->parser          = new Gravity_Forms_Tokens_Parser();
		$this->possible_tokens = new Gravity_Forms_Possible_Tokens();
	}

	/**
	 * save_legacy_trigger_tokens
	 *
	 * @param  mixed $trigger_meta
	 * @param  mixed $entry
	 * @param  mixed $form
	 * @return void
	 */
	public function save_legacy_trigger_tokens( $trigger_meta, $entry, $form ) {

		$trigger_meta['meta_key']   = 'GFENTRYID';
		$trigger_meta['meta_value'] = $entry['id'];
		Automator()->process->user->insert_trigger_meta( $trigger_meta );

		$trigger_meta['meta_key']   = 'GFUSERIP';
		$trigger_meta['meta_value'] = maybe_serialize( $entry['ip'] );
		Automator()->process->user->insert_trigger_meta( $trigger_meta );

		$trigger_meta['meta_key']   = 'GFENTRYDATE';
		$trigger_meta['meta_value'] = maybe_serialize( \GFCommon::format_date( $entry['date_created'], false, 'Y/m/d' ) );
		Automator()->process->user->insert_trigger_meta( $trigger_meta );

		$trigger_meta['meta_key']   = 'GFENTRYSOURCEURL';
		$trigger_meta['meta_value'] = maybe_serialize( $entry['source_url'] );
		Automator()->process->user->insert_trigger_meta( $trigger_meta );
	}
}
