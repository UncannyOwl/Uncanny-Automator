<?php

namespace Uncanny_Automator\Integrations\Pretty_Links;

/**
 * Class PRLI_CREATE_LINK
 *
 * @package Uncanny_Automator
 *
 * @method \Uncanny_Automator\Integrations\Pretty_Links\Pretty_Links_Helpers get_item_helpers()
 */
class PRLI_CREATE_LINK extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Set up the action's basic properties like integration, sentence, and tokens.
	 *
	 * @return void
	 */
	protected function setup_action() {
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
	 * Define the action's option fields.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'PRLI_TITLE',
					'label'       => esc_html_x( 'Title', 'Pretty Links', 'uncanny-automator' ),
				)
			),
			Automator()->helpers->recipe->field->select_field_args(
				array(
					'input_type'      => 'select',
					'option_code'     => 'PRLI_REDIRECTION',
					'label'           => esc_html_x( 'Redirection type', 'Pretty Links', 'uncanny-automator' ),
					'required'        => true,
					'options'         => array(),
					'remote_data'     => $this->get_item_helpers()->remote_data_load_config( 'redirection_types' ),
					'options_show_id' => false,
					'token_name'      => esc_html_x( 'Redirection type', 'Pretty Links', 'uncanny-automator' ),
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'PRLI_TARGET_URL',
					'input_type'  => 'url',
					'label'       => esc_html_x( 'Target URL', 'Pretty Links', 'uncanny-automator' ),
					'description' => esc_html_x( 'This is the URL that your Pretty Link will redirect to.', 'Pretty Links', 'uncanny-automator' ),
				)
			),
			array(
				'option_code' => 'PRLI_TRACK_ME',
				'required'    => false,
				'input_type'  => 'checkbox',
				'is_toggle'   => true,
				'label'       => esc_html_x( 'Tracking', 'Pretty Links', 'uncanny-automator' ),
				'description' => esc_html_x( 'Enable Pretty Link built-in hit (click) tracking.', 'Pretty Links', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Define the tokens exposed by the action.
	 *
	 * @return array[]
	 */
	public function define_tokens() {
		return array(
			'LINK_ID'     => array(
				'name' => esc_html_x( 'Link ID', 'Pretty Links', 'uncanny-automator' ),
				'type' => 'int',
			),
			'LINK_TITLE'  => array(
				'name' => esc_html_x( 'Link title', 'Pretty Links', 'uncanny-automator' ),
				'type' => 'text',
			),
			'PRETTY_LINK' => array(
				'name' => esc_html_x( 'Pretty link', 'Pretty Links', 'uncanny-automator' ),
				'type' => 'url',
			),
		);
	}

	/**
	 * Process the action: create the pretty link and hydrate its tokens.
	 *
	 * @param int     $user_id     The user ID. Use this argument to pass the user ID instead of get_current_user_id().
	 * @param mixed[] $action_data The action data.
	 * @param int     $recipe_id   The recipe ID.
	 * @param mixed[] $args        The args.
	 * @param mixed[] $parsed      The parsed field values.
	 *
	 * @return bool True if the action is successful. Returns false, otherwise.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$title         = isset( $parsed['PRLI_TITLE'] ) ? sanitize_text_field( $parsed['PRLI_TITLE'] ) : '';
		$redirect_type = isset( $parsed['PRLI_REDIRECTION'] ) ? sanitize_text_field( $parsed['PRLI_REDIRECTION'] ) : '';
		$target_url    = isset( $parsed['PRLI_TARGET_URL'] ) ? esc_url_raw( $parsed['PRLI_TARGET_URL'] ) : '';
		$track_me      = isset( $parsed['PRLI_TRACK_ME'] ) ? sanitize_text_field( $parsed['PRLI_TRACK_ME'] ) : '';

		$link = $this->create_link(
			array(
				'name'          => $title,
				'url'           => $target_url,
				'redirect_type' => $redirect_type,
				'track_me'      => 'true' === $track_me,
			)
		);

		if ( is_wp_error( $link ) ) {
			$this->add_log_error( $link->get_error_message() );

			return false;
		}

		$this->hydrate_tokens(
			array(
				'LINK_ID'     => $link['id'],
				'LINK_TITLE'  => $title,
				'PRETTY_LINK' => $link['pretty_url'],
			)
		);

		return true;
	}

	/**
	 * Create a pretty link using whichever API the installed Pretty Links version provides.
	 *
	 * Pretty Links 4.0 removed the v3 PrliLink model in favour of the namespaced
	 * Links repository, so link creation is version-dispatched here instead of
	 * coupling the action to a single API surface.
	 *
	 * @param array $params Link data. Supported keys: name, url, redirect_type, track_me (bool).
	 *
	 * @return array|\WP_Error Array with 'id' and 'pretty_url' keys on success, WP_Error on failure.
	 */
	private function create_link( $params ) {

		// Pretty Links 4.0+.
		if ( class_exists( '\PrettyLinks\Repositories\Links' ) ) {
			return $this->create_link_v4( $params );
		}

		// Pretty Links 3.x and older.
		if ( class_exists( '\PrliLink' ) ) {
			return $this->create_link_legacy( $params );
		}

		// Public API function — real in 3.x, kept as a deprecated shim in 4.x. Last
		// resort in case a future version drops or relocates the classes above.
		if ( function_exists( 'prli_create_pretty_link' ) ) {
			$track_me = empty( $params['track_me'] ) ? '' : 1;
			$link_id  = prli_create_pretty_link( $params['url'], '', $params['name'], '', 0, $track_me, '', '', $params['redirect_type'] );

			return $this->format_created_link( $link_id );
		}

		return new \WP_Error( 'prli_api_not_found', esc_html_x( 'No supported Pretty Links API was found. Make sure the Pretty Links plugin is installed and activated.', 'Pretty Links', 'uncanny-automator' ) );
	}

	/**
	 * Create a link through the Pretty Links 4.0 Links repository.
	 *
	 * @param array $params Link data. Supported keys: name, url, redirect_type, track_me (bool).
	 *
	 * @return array|\WP_Error Array with 'id' and 'pretty_url' keys on success, WP_Error on failure.
	 */
	private function create_link_v4( $params ) {

		$data = array(
			'url'  => $params['url'],
			'name' => $params['name'],
		);

		if ( ! empty( $params['redirect_type'] ) ) {
			$data['redirect_type'] = (string) $params['redirect_type'];
		}

		// Only send the flag when tracking was ticked so an untouched toggle
		// falls back to the site-wide default, matching the v3 behaviour.
		if ( ! empty( $params['track_me'] ) ) {
			$data['track_me'] = 1;
		}

		$link = ( new \PrettyLinks\Repositories\Links() )->create( $data );

		if ( isset( $link['error'] ) || empty( $link['id'] ) ) {
			$reason = isset( $link['error'] ) ? $link['error'] : 'insert_failed';

			/* translators: %s: Pretty Links error code (e.g. url_invalid, insert_failed) */
			return new \WP_Error( 'prli_create_failed', sprintf( esc_html_x( 'Pretty Links could not create the link (%s).', 'Pretty Links', 'uncanny-automator' ), $reason ) );
		}

		return array(
			'id'         => (int) $link['id'],
			'pretty_url' => isset( $link['pretty_url'] ) ? (string) $link['pretty_url'] : '',
		);
	}

	/**
	 * Create a link through the legacy PrliLink model (Pretty Links 3.x and older).
	 *
	 * @param array $params Link data. Supported keys: name, url, redirect_type, track_me (bool).
	 *
	 * @return array|\WP_Error Array with 'id' and 'pretty_url' keys on success, WP_Error on failure.
	 */
	private function create_link_legacy( $params ) {

		$prli = new \PrliLink();

		$values = array(
			'name'          => $params['name'],
			'slug'          => $prli->generateValidSlug(),
			'url'           => $params['url'],
			'redirect_type' => $params['redirect_type'],
		);

		if ( ! empty( $params['track_me'] ) ) {
			$values['track_me'] = 1;
		}

		return $this->format_created_link( $prli->create( $values ) );
	}

	/**
	 * Normalize a created link ID into the create_link() return shape.
	 *
	 * @param int|false $link_id The newly created link ID, or a falsy value on failure.
	 *
	 * @return array|\WP_Error Array with 'id' and 'pretty_url' keys on success, WP_Error on failure.
	 */
	private function format_created_link( $link_id ) {

		if ( empty( $link_id ) ) {
			return new \WP_Error( 'prli_create_failed', esc_html_x( 'Pretty Link was not able to create a URL. Please check PHP error log for possible reason.', 'Pretty Links', 'uncanny-automator' ) );
		}

		return array(
			'id'         => (int) $link_id,
			'pretty_url' => (string) prli_get_pretty_link_url( $link_id ),
		);
	}
}
