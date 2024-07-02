<?php
namespace Uncanny_Automator\Integrations\Notion\Actions;

use Exception;

/**
 * @package Uncanny_Automator\Integrations\Notion\Actions\Create_Page
 */
class Create_Page extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @var Notion_Helpers
	 */
	protected $helpers = null;

	/**
	 * Setups the action basic properties like Integration, Sentence, etc.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'NOTION' );
		$this->set_action_code( 'NOTION_CREATE_PAGE' );
		$this->set_action_meta( 'NOTION_CREATE_PAGE_META' );
		$this->set_requires_user( false );

		/* translators: Action sentence */
		$this->set_sentence( sprintf( esc_attr_x( 'Create {{a page:%1$s}}', 'Notion', 'uncanny-automator' ), 'NON_EXISTENT:' . $this->get_action_meta() ) );

		/* translators: Action sentence */
		$this->set_readable_sentence( esc_attr_x( 'Create {{a page}}', 'Notion', 'uncanny-automator' ) );

	}

	/**
	 * Defines the options.
	 *
	 * @return array<array{
	 *  'text': string,
	 *  'value': mixed
	 * }>
	 */
	public function options() {

		// The "Redirect title" field.
		$parent = array(
			'input_type'  => 'select',
			'option_code' => 'PARENT',
			'label'       => _x( 'Parent', 'Notion', 'uncanny-automator' ),
			'required'    => true,
			'options'     => array(),
			'ajax'        => array(
				'endpoint' => 'automator_notion_list_pages',
				'event'    => 'on_load',
			),
		);

		// The "Redirect type" field.
		$title = array(
			'input_type'  => 'text',
			'option_code' => $this->get_action_meta(),
			'label'       => _x( 'Title', 'Notion', 'uncanny-automator' ),
			'required'    => true,
		);

		// The "Target URL" field.
		$content = array(
			'input_type'  => 'textarea',
			'option_code' => 'CONTENT',
			'label'       => _x( 'Content', 'Notion', 'uncanny-automator' ),
			'required'    => true,
		);

		return array(
			$parent,
			$title,
			$content,
		);

	}

	/**
	 * Processes the action.
	 *
	 * @link https://developer.automatorplugin.com/adding-a-custom-action-to-uncanny-automator/ Processing the action.
	 *
	 * @param int     $user_id The user ID. Use this argument to passed the User ID instead of get_current_user_id().
	 * @param mixed[] $action_data The action data.
	 * @param int     $recipe_id The recipe ID.
	 * @param mixed[] $args The args.
	 * @param mixed[] $parsed The parsed variables.
	 *
	 * @return bool True if the action is successful. Returns false, otherwise.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		try {
			$body = array(
				'action'         => 'create_page',
				'parent_page_id' => $this->get_parsed_meta_value( 'PARENT', '' ),
				'title'          => $this->get_parsed_meta_value( $this->get_action_meta(), '' ),
				'content'        => $this->get_parsed_meta_value( 'CONTENT', '' ),
			);
			$this->helpers->api_request( $body, $action_data );
		} catch ( Exception $e ) {
			$this->add_log_error( $e->getMessage() );
			return false;
		}

		return true;
	}

}
