<?php

namespace Uncanny_Automator;

use Groundhogg\DB\Tags;
use Groundhogg\Plugin;

/**
 * Class HG_ADDTAG
 *
 * @package Uncanny_Automator
 */
class GH_ADDTAG {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'GH';

	/**
	 * @var string
	 */
	private $action_code;
	/**
	 * @var string
	 */
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'GHADDTAG';
		$this->action_meta = 'GHTAG';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/groundhogg/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - Groundhogg */
			'sentence'           => sprintf( esc_attr__( 'Add {{a tag:%1$s}} to the user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - Groundhogg */
			'select_option_name' => esc_attr__( 'Add {{a tag}} to the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'add_tag_to_user' ),
			'options_callback'   => array( $this, 'options_callback' ),
		);

		Automator()->register->action( $action );
	}

	/**
	 * @return array
	 */
	public function options_callback() {
		$tags = new Tags();

		$tag_options = array();
		foreach ( $tags->get_tags() as $tag ) {
			$tag_options[ $tag->tag_id ] = $tag->tag_name;
		}

		$options = array(
			'option_code' => $this->action_meta,
			'label'       => esc_attr__( 'Tags', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => $tag_options,
		);

		return Automator()->utilities->keep_order_of_options( array( 'options' => array( $options ) ) );
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function add_tag_to_user( $user_id, $action_data, $recipe_id, $args ) {
		$contact = Plugin::$instance->utils->get_contact( absint( $user_id ), true );

		if ( ! $contact ) {
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, __( 'Contact was not found.', 'uncanny-automator' ) );

			return;
		}

		$tag_id = $action_data['meta'][ $this->action_meta ];
		$tags   = new Tags();

		if ( false === $tags->exists( $tag_id ) ) {
			$tag_id = $tags->add( array( 'tag_name' => $tag_id ) );
		}

		$tags_to_add = array( absint( $tag_id ) );
		$contact->apply_tag( $tags_to_add );

		Automator()->complete->action( $user_id, $action_data, $recipe_id );
	}
}
