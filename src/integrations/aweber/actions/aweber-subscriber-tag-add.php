<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound

namespace Uncanny_Automator\Integrations\Aweber;

use Exception;

/**
 * Class AWEBER_SUBSCRIBER_TAG_ADD
 *
 * @package Uncanny_Automator
 */
class AWEBER_SUBSCRIBER_TAG_ADD extends \Uncanny_Automator\Recipe\Action {

	public $prefix = 'AWEBER_SUBSCRIBER_TAG_ADD';

	/**
	 * Spins up new action inside "AWEBER" integration.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'AWEBER' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/aweber/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s Contact Email, %2$s List*/
				esc_attr_x( 'Add {{a tag:%1$s}} to {{a subscriber:%2$s}}', 'AWeber', 'uncanny-automator' ),
				'NON_EXISTING:' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add {{a tag}} to {{a subscriber}}', 'AWeber', 'uncanny-automator' ) );
		$this->set_background_processing( true );

	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code' => 'ACCOUNT',
				'label'       => _x( 'Account', 'AWeber', 'uncanny-automator' ),
				'input_type'  => 'select',
				'options'     => array(),
				'required'    => true,
				'ajax'        => array(
					'endpoint' => 'automator_aweber_accounts_fetch',
					'event'    => 'on_load',
				),
			),
			array(
				'option_code' => 'LIST',
				'label'       => _x( 'List', 'AWeber', 'uncanny-automator' ),
				'input_type'  => 'select',
				'options'     => array(),
				'required'    => true,
				'ajax'        => array(
					'endpoint'      => 'automator_aweber_list_fetch',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( 'ACCOUNT' ),
				),
			),
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => _x( 'Subscriber email', 'AWeber', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'TAGS',
				'label'       => _x( 'Tags', 'AWeber', 'uncanny-automator' ),
				'description' => _x( 'Please enter a comma-separated list of values.', 'AWeber', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
		);

	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$account_id = $parsed['ACCOUNT'] ?? '';
		$list_id    = $parsed['LIST'] ?? '';
		$email      = $parsed[ $this->get_action_meta() ] ?? '';
		$tags       = (array) explode( ',', $parsed['TAGS'] ) ?? '';

		try {

			if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
				throw new Exception( sprintf( 'The email address [%s] is invalid', $email ) );
			}

			$body = array(
				'action'     => 'add_tags_subscriber',
				'account_id' => $account_id,
				'list_id'    => $list_id,
				'email'      => $email,
				'tags'       => wp_json_encode( array_map( 'trim', $tags ) ),
			);

			$this->helpers->api_request( $body, $action_data );

			return true;

		} catch ( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}

	}

}
