<?php

namespace Uncanny_Automator\Integrations\Pretty_Links;

/**
 * Class PRLI_CREATE_LINK
 *
 * @package Uncanny_Automator
 */
class PRLI_CREATE_LINK extends \Uncanny_Automator\Recipe\Action {

	protected $helpers;

	/**
	 * Setups the action basic properties like Integration, Sentence, etc.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'PRETTY_LINKS' );
		$this->set_action_code( 'PRLI_CREATE_LINK' );
		$this->set_action_meta( 'PRLI_CREATE_LINK_META' );
		$this->set_requires_user( false );
		// Sentence that appears in the trigger list dropdown.
		/* translators: Action sentence - Pretty Links */
		$this->set_sentence( sprintf( esc_attr_x( 'Create a pretty link for {{a specific target URL:%1$s}}', 'Pretty Links', 'uncanny-automator' ), $this->get_action_meta() ) );
		// Sentence that appears in the trigger list dropdown.
		$this->set_readable_sentence( esc_attr_x( 'Create a pretty link for {{a specific target URL}}', 'Pretty Links', 'uncanny-automator' ) );
	}

	/**
	 * load_options
	 *
	 * @return array
	 */
	public function options() {
		return array(
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'PRLI_TITLE',
					'label'       => _x( 'Title', 'Pretty Links', 'uncanny-automator' ),
				)
			),
			Automator()->helpers->recipe->field->select_field_args(
				array(
					'input_type'      => 'select',
					'option_code'     => 'PRLI_REDIRECTION',
					'label'           => _x( 'Redirection type', 'Pretty Links', 'uncanny-automator' ),
					'required'        => true,
					'options'         => $this->helpers->get_all_redirection_types(),
					'options_show_id' => false,
					'token_name'      => _x( 'Redirection type', 'Pretty Links', 'uncanny-automator' ),
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'PRLI_TARGET_URL',
					'input_type'  => 'url',
					'label'       => _x( 'Target URL', 'Pretty Links', 'uncanny-automator' ),
					'description' => _x( 'This is the URL that your Pretty Link will redirect to.', 'Pretty Links', 'uncanny-automator' ),
				)
			),
			array(
				'option_code' => 'PRLI_TRACK_ME',
				'required'    => false,
				'input_type'  => 'checkbox',
				'is_toggle'   => true,
				'label'       => _x( 'Tracking', 'Pretty Links', 'uncanny-automator' ),
				'description' => _x( 'Enable Pretty Link built-in hit (click) tracking.', 'Pretty Links', 'uncanny-automator' ),
			),
		);

	}

	/**
	 * @return array[]
	 */
	public function define_tokens() {
		return array(
			'LINK_ID'     => array(
				'name' => __( 'Link ID', 'uncanny-automator' ),
				'type' => 'int',
			),
			'LINK_TITLE'  => array(
				'name' => __( 'Link title', 'uncanny-automator' ),
				'type' => 'text',
			),
			'PRETTY_LINK' => array(
				'name' => __( 'Pretty link', 'uncanny-automator' ),
				'type' => 'url',
			),
		);
	}

	/**
	 * Processes the action.
	 *
	 * @param int $user_id The user ID. Use this argument to passed the User ID instead of
	 *                             get_current_user_id().
	 * @param mixed[] $action_data The action data.
	 * @param int $recipe_id The recipe ID.
	 * @param mixed[] $args The args.
	 * @param mixed[] $parsed The parsed variables.
	 *
	 * @return bool True if the action is successful. Returns false, otherwise.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		if ( ! class_exists( '\PrliLink' ) ) {
			$this->add_log_error( 'Class \PrliLink is not found. Make sure Pretty Links plugin is installed and activated.' );

			return false;
		}

		$title         = isset( $parsed['PRLI_TITLE'] ) ? sanitize_text_field( $parsed['PRLI_TITLE'] ) : '';
		$redirect_type = isset( $parsed['PRLI_REDIRECTION'] ) ? sanitize_text_field( $parsed['PRLI_REDIRECTION'] ) : '';
		$target_url    = isset( $parsed['PRLI_TARGET_URL'] ) ? esc_url_raw( $parsed['PRLI_TARGET_URL'] ) : '';
		$track_me      = isset( $parsed['PRLI_TRACK_ME'] ) ? sanitize_text_field( $parsed['PRLI_TRACK_ME'] ) : '';
		$prli          = new \PrliLink();
		$params        = array();

		// Assign values to the $pretty_link_values assoc array that will be passed to the PrliLink::create() method later.
		$params['name']          = $title;
		$params['slug']          = $prli->generateValidSlug();
		$params['url']           = $target_url;
		$params['redirect_type'] = $redirect_type;
		if ( 'true' === $track_me ) {
			$params['track_me'] = 1;
		}
		// Pass the constructured array of $params.
		$pretty_link_id = $prli->create( $params );

		if ( ! empty( $pretty_link_id ) ) {
			$this->hydrate_tokens(
				array(
					'LINK_ID'     => $pretty_link_id,
					'LINK_TITLE'  => $title,
					'PRETTY_LINK' => prli_get_pretty_link_url( $pretty_link_id ),
				)
			);

			return true;
		}

		$this->add_log_error( 'Pretty Link was not able to create a URL. Please check PHP error log for possible reason.' );

		return false;

	}
}
