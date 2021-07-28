<?php

namespace Uncanny_Automator;

use Groundhogg\DB\Tags;
use Groundhogg\Plugin;

/**
 * Class HG_ADDTAG
 * @package Uncanny_Automator
 */
class GH_ADDTAG {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'GH';

	private $action_code;
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


		$tags = new Tags;

		$tag_options = array();
		foreach ( $tags->get_tags() as $tag ) {
			$tag_options[ $tag->tag_id ] = $tag->tag_name;
		}

		$option = [
			'option_code' => $this->action_meta,
			'label'       => esc_attr__( 'Tags', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => $tag_options,
		];

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
			'options'            => [
				$option,
			],
		);

		Automator()->register->action( $action );
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function add_tag_to_user( $user_id, $action_data, $recipe_id, $args ) {



		$tag_id = absint( $action_data['meta'][ $this->action_meta ] );

		if ( 0 !== $tag_id ) {
			$contact = Plugin::$instance->utils->get_contact( absint( $user_id ), true );

			if ( ! $contact ) {
				return;
			}

			$tags_to_add = [ $tag_id ];
			$contact->apply_tag( $tags_to_add );

		}

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}
}
