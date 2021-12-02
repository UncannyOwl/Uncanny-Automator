<?php

namespace Uncanny_Automator;

/**
 * Class WPWH_TRIGGERTRIGGERED
 *
 * @package Uncanny_Automator
 */
class WPWH_TRIGGERTRIGGERED {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WPWEBHOOKS';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'WPWHTRIGGERTRIGGERED';
		$this->trigger_meta = 'WPWHTRIGGER';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/automator-core/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'meta'                => $this->trigger_meta,
			'sentence'            => sprintf( __( '{{A webhook trigger:%1$s}} is triggered', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Formidable */
			'select_option_name'  => __( '{{A webhook trigger}} is triggered', 'uncanny-automator' ),
			'action'              => 'wpwhpro/admin/webhooks/webhook_trigger_sent',
			'priority'            => 10,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'save_data' ),
			'options'             => array(
				Automator()->helpers->recipe->wp_webhooks->options->list_webhook_triggers( null, $this->trigger_meta ),
			),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 */
	public function save_data( $response, $url, $http_args, $webhook ) {

		if ( ! isset( $webhook['webhook_name'] ) || empty( $webhook['webhook_name'] ) ) {
			return;
		}
		$trigger = $webhook['webhook_name'];

		$body_data_format = 'json';
		if ( isset( $webhook['settings']['wpwhpro_trigger_response_type'] ) ) {
			$body_data_format = $webhook['settings']['wpwhpro_trigger_response_type'];
		}

		$recipes = Automator()->get->recipes_from_trigger_code( $this->trigger_code );

		$conditions = Automator()->helpers->recipe->wp_webhooks->options->match_action_condition( $trigger, $recipes, $this->trigger_meta, $this->trigger_code );

		if ( ! $conditions ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! empty( $conditions ) ) {
			foreach ( $conditions['recipe_ids'] as $recipe_id ) {
				if ( ! Automator()->is_recipe_completed( $recipe_id, $user_id ) ) {
					$args = array(
						'code'            => $this->trigger_code,
						'meta'            => $this->trigger_meta,
						'recipe_to_match' => $recipe_id,
						'ignore_post_id'  => true,
						'user_id'         => $user_id,
					);

					$result = Automator()->maybe_add_trigger_entry( $args, false );

					if ( $result ) {
						foreach ( $result as $r ) {
							if ( true === $r['result'] ) {
								$_args = array();
								if ( isset( $r['args'] ) && isset( $r['args']['get_trigger_id'] ) ) {
									//Saving params in trigger log meta for token parsing!
									$_args = array(
										'trigger_id'     => (int) $r['args']['trigger_id'],
										'meta_key'       => $this->trigger_meta . '_request_body',
										'user_id'        => $user_id,
										'trigger_log_id' => $r['args']['get_trigger_id'],
										'run_number'     => $r['args']['run_number'],
									);

									$params = $http_args['body'];
									if ( 'json' === $body_data_format ) {
										// convert json to array
										$params = json_decode( $params, true );
									} elseif ( 'xml' === $body_data_format ) {
										// convert xml to array
										$xml_data = new \SimpleXMLElement( $params );
										$params   = Automator()->helpers->recipe->wp_webhooks->options->XML2Array( $xml_data );
									}

									Automator()->helpers->recipe->wp_webhooks->options->extract_and_save_data( $params, $_args );
								}
								Automator()->maybe_trigger_complete( $r['args'] );
							}
						}
					}
				}
			}
		}
	}
}
