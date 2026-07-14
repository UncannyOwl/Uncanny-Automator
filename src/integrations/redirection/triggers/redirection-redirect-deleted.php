<?php

namespace Uncanny_Automator\Integrations\Redirection;

/**
 * Class Redirection_Redirect_Deleted
 *
 * @property \Uncanny_Automator\Integrations\Redirection\Redirection_Helpers $item_helpers
 */
class Redirection_Redirect_Deleted extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Token subset this trigger exposes (keys of get_redirect_tokens_config()).
	 *
	 * @var string[]
	 */
	private $token_keys = array(
		'REDIRECT_ID',
		'REDIRECT_SOURCE_URL',
		'REDIRECT_TARGET_URL',
		'REDIRECT_GROUP',
		'REDIRECT_HTTP_CODE',
	);

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'REDIRECTION_REDIRECT_DELETED', 'REDIRECTION' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'REDIRECTION_REDIRECT' )
			->hook( 'redirection_redirect_deleted', 10, 1 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_login_required( false );
		$this->set_sentence( esc_html_x( 'A redirect is deleted', 'Redirection', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A redirect is deleted', 'Redirection', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array();
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
		return is_a( $hook_args[0] ?? null, '\Red_Item' );
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
		$config = array_intersect_key(
			$this->item_helpers->get_redirect_tokens_config(),
			array_flip( $this->token_keys )
		);
		return array_merge( $tokens, array_values( $config ) );
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
		return array_intersect_key(
			$this->item_helpers->hydrate_redirect_item( $hook_args[0] ?? null ),
			array_flip( $this->token_keys )
		);
	}
}
