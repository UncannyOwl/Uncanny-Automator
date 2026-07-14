<?php

namespace Uncanny_Automator\Integrations\Redirection;

/**
 * Class Redirection_Redirect_Matched
 *
 * @property \Uncanny_Automator\Integrations\Redirection\Redirection_Helpers $item_helpers
 */
class Redirection_Redirect_Matched extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'REDIRECTION_REDIRECT_MATCHED', 'REDIRECTION' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'REDIRECTION_GROUP' )
			->hook( 'redirection_visit', 10, 3 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_login_required( false );
		$this->set_sentence(
			sprintf(
				/* translators: 1: Group placeholder */
				esc_html_x( 'A redirect in {{a group:%1$s}} is matched', 'Redirection', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A redirect in {{a group}} is matched', 'Redirection', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Group', 'Redirection', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'groups' ),
			),
		);
	}

	/**
	 * Validate Trigger.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$item = $hook_args[0] ?? null;

		if ( ! is_a( $item, '\Red_Item' ) ) {
			return false;
		}

		$selected_group = (int) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? -1 );

		if ( -1 !== $selected_group && (int) $item->get_group_id() !== $selected_group ) {
			return false;
		}

		return true;
	}

	/**
	 * Define Tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			array_values( $this->item_helpers->get_redirect_tokens_config() ),
			array(
				array(
					'tokenId'   => 'REQUESTED_URL',
					'tokenName' => esc_html_x( 'Requested URL', 'Redirection', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'RESOLVED_TARGET_URL',
					'tokenName' => esc_html_x( 'Resolved target URL', 'Redirection', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
	}

	/**
	 * Hydrate Tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$item         = $hook_args[0] ?? null;
		$original_url = $hook_args[1] ?? '';
		$target_url   = $hook_args[2] ?? '';

		return array_merge(
			$this->item_helpers->hydrate_redirect_item( $item ),
			array(
				'REQUESTED_URL'       => (string) $original_url,
				'RESOLVED_TARGET_URL' => (string) $target_url,
			)
		);
	}
}
