<?php

namespace Uncanny_Automator\Integrations\Redirection;

/**
 * Class Redirection_Monitor_Created
 *
 * @property \Uncanny_Automator\Integrations\Redirection\Redirection_Helpers $item_helpers
 */
class Redirection_Monitor_Created extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Redirect token subset this trigger exposes (keys of get_redirect_tokens_config()).
	 *
	 * @var string[]
	 */
	private $redirect_token_keys = array(
		'REDIRECT_ID',
		'REDIRECT_SOURCE_URL',
		'REDIRECT_TARGET_URL',
		'REDIRECT_GROUP',
	);

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'REDIRECTION_MONITOR_CREATED', 'REDIRECTION' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'REDIRECTION_MONITOR' )
			->hook( 'redirection_monitor_created', 10, 3 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_login_required( false );
		$this->set_sentence( esc_html_x( 'A redirect is created by the URL monitor', 'Redirection', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A redirect is created by the URL monitor', 'Redirection', 'uncanny-automator' ) );
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
			array_flip( $this->redirect_token_keys )
		);

		return array_merge(
			$tokens,
			array_values( $config ),
			array(
				array(
					'tokenId'   => 'MONITOR_POST_ID',
					'tokenName' => esc_html_x( 'Post ID', 'Redirection', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => 'MONITOR_POST_TITLE',
					'tokenName' => esc_html_x( 'Post title', 'Redirection', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'MONITOR_OLD_URL',
					'tokenName' => esc_html_x( 'Old URL', 'Redirection', 'uncanny-automator' ),
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

		$item    = $hook_args[0] ?? null;
		$before  = $hook_args[1] ?? '';
		$post_id = (int) ( $hook_args[2] ?? 0 );

		$redirect = array_intersect_key(
			$this->item_helpers->hydrate_redirect_item( $item ),
			array_flip( $this->redirect_token_keys )
		);

		return array_merge(
			$redirect,
			array(
				'MONITOR_POST_ID'    => $post_id,
				'MONITOR_POST_TITLE' => $post_id ? get_the_title( $post_id ) : '',
				'MONITOR_OLD_URL'    => (string) $before,
			)
		);
	}
}
