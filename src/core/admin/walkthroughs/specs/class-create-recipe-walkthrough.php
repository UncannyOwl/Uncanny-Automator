<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Create_Recipe_Walkthrough
 *
 * Authors the create-recipe guided tour. Targets and event names track the live recipe-builder web components (node-selector based flow), and every event name uses the uap.f.recipe-builder.* namespace. The spec assembly and the wire-format constructors come from {@see Automator_Walkthrough_Spec}.
 *
 * @package Uncanny_Automator
 */
class Automator_Create_Recipe_Walkthrough extends Automator_Walkthrough_Spec {

	const ID      = 'create-recipe';
	const SURFACE = 'recipe-edit';

	/**
	 * The ordered steps.
	 *
	 * @return array
	 */
	protected static function get_steps() {

		$type_confirm   = self::shadow( array( 'uap-f-recipe-builder-type-selector', 'uas-button[slot="primary-action"]' ) );
		$title_input    = self::shadow( array( 'uap-f-recipe-builder-title', 'input.title' ) );
		$add_trigger    = self::selector( 'uap-f-recipe-builder-node-selector-add-button[scope="trigger"], [data-test="canvas-add-trigger"]' );
		$add_action     = self::selector( 'uap-f-recipe-builder-node-selector-add-button[scope="action"], [data-test="canvas-add-action"]' );
		$edit_dialog    = self::selector( 'uap-f-recipe-builder-edit-node' );
		$wp_integration = self::shadow( array( 'uap-f-recipe-builder-node-selector-viewer', '.viewer-integration[data-integration-code="WP"]' ) );
		$wp_trigger     = self::shadow( array( 'uap-f-recipe-builder-node-selector-viewer', '.viewer-item[data-integration-code="WP"][data-item-code="WP_USER_POST_PUBLISHED"]' ) );
		$emails         = self::shadow( array( 'uap-f-recipe-builder-node-selector-viewer', '.viewer-integration[data-integration-code="EMAILS"]' ) );
		$send_email     = self::shadow( array( 'uap-f-recipe-builder-node-selector-viewer', '.viewer-item[data-integration-code="EMAILS"][data-item-code="SENDEMAIL"]' ) );

		return array(

			array(
				'id'        => 'welcome',
				'surface'   => self::SURFACE,
				'title'     => esc_html__( 'Welcome to Uncanny Automator', 'uncanny-automator' ),
				'body_html' => self::paragraph( esc_html_x( 'Let us build your first recipe together. It only takes a minute.', 'Create recipe walkthrough', 'uncanny-automator' ) ),
				'wait_for'  => array( self::selector( 'uap-f-recipe-builder' ) ),
				'advance'   => array( 'kind' => 'button' ),
				'buttons'   => self::button( esc_html__( 'Start', 'uncanny-automator' ), 'next' ),
			),

			array(
				'id'        => 'recipe-type',
				'surface'   => self::SURFACE,
				'title'     => esc_html__( 'Choose a recipe type', 'uncanny-automator' ),
				'body_html' => self::paragraph( esc_html_x( '"Logged-in users" is selected, so the recipe runs for users on your site. Confirm to start building your recipe.', 'Create recipe walkthrough', 'uncanny-automator' ) ),
				'target'    => $type_confirm,
				'attach'    => 'left',
				'wait_for'  => array( $type_confirm ),
				'requires'  => $type_confirm,
				'advance'   => array(
					'kind' => 'event',
					'name' => 'uap.f.recipe-builder.core.set-recipe-type',
				),
			),

			array(
				'id'        => 'set-title',
				'surface'   => self::SURFACE,
				'title'     => esc_html__( 'Name your recipe', 'uncanny-automator' ),
				'body_html' => self::paragraph( esc_html_x( 'Every recipe needs a title. We have filled one in for this example.', 'Create recipe walkthrough', 'uncanny-automator' ) ),
				'target'    => $title_input,
				'attach'    => 'bottom',
				'wait_for'  => array( $title_input ),
				'actions'   => array(
					array(
						'kind'   => 'typewriter',
						'target' => $title_input,
						'text'   => esc_html__( 'Send an email when a post is published', 'uncanny-automator' ),
					),
				),
				'advance'   => array( 'kind' => 'button' ),
				'buttons'   => self::button( esc_html__( 'Continue', 'uncanny-automator' ), 'next' ),
			),

			array(
				'id'        => 'trigger-add',
				'surface'   => self::SURFACE,
				'title'     => esc_html__( 'Add a trigger', 'uncanny-automator' ),
				'body_html' => self::paragraph( esc_html_x( 'A trigger is what starts your recipe. Click "Add trigger" to choose one.', 'Create recipe walkthrough', 'uncanny-automator' ) ),
				'target'    => $add_trigger,
				'attach'    => 'right',
				'wait_for'  => array( $add_trigger ),
				'advance'   => array(
					'kind'  => 'event',
					'name'  => 'uap.f.recipe-builder.node-selector.ready',
					'match' => array( 'detail.scope' => 'trigger' ),
				),
			),

			array(
				'id'                 => 'trigger-integration',
				'surface'            => self::SURFACE,
				'title'              => esc_html__( 'Pick the WordPress integration', 'uncanny-automator' ),
				'body_html'          => self::paragraph( esc_html_x( 'Select the WordPress integration from the results.', 'Create recipe walkthrough', 'uncanny-automator' ) ),
				'target'             => $wp_integration,
				'attach'             => 'left',
				'wait_for'           => array( $wp_integration ),
				'requires'           => $wp_integration,
				'restrict_to_target' => true,
				'advance'            => array(
					'kind'  => 'event',
					'name'  => 'uap.f.recipe-builder.node-selector.step-request-change',
					'match' => array(
						'detail.scenario_id'      => 'integration-ready',
						'detail.integration_code' => 'WP',
					),
				),
			),

			array(
				'id'                 => 'trigger-item',
				'surface'            => self::SURFACE,
				'title'              => esc_html__( 'Pick a trigger', 'uncanny-automator' ),
				'body_html'          => self::paragraph( esc_html_x( 'Choose "A user publishes a post" to start the recipe when a post is published.', 'Create recipe walkthrough', 'uncanny-automator' ) ),
				'target'             => $wp_trigger,
				'attach'             => 'left',
				'wait_for'           => array( $wp_trigger ),
				'requires'           => $wp_trigger,
				'restrict_to_target' => true,
				'advance'            => array(
					'kind'  => 'event',
					'name'  => 'uap.f.recipe-builder.node-selector.step-request-change',
					'match' => array(
						'detail.scenario_id' => 'item-ready',
						'detail.item_code'   => 'WP_USER_POST_PUBLISHED',
					),
				),
			),

			array(
				'id'        => 'trigger-save',
				'surface'   => self::SURFACE,
				'title'     => esc_html__( 'Edit your trigger', 'uncanny-automator' ),
				'body_html' => self::paragraph( esc_html_x( 'Fill in the trigger fields, then save it.', 'Create recipe walkthrough', 'uncanny-automator' ) ),
				'target'    => $edit_dialog,
				'attach'    => 'left',
				'wait_for'  => array( $edit_dialog ),
				'advance'   => array(
					'kind' => 'event',
					'name' => 'uap.f.recipe-builder.flow.item.edit.saved',
				),
			),

			array(
				'id'        => 'action-add',
				'surface'   => self::SURFACE,
				'title'     => esc_html__( 'Add an action', 'uncanny-automator' ),
				'body_html' => self::paragraph( esc_html_x( 'Actions run after the trigger fires. Add one to choose what happens next.', 'Create recipe walkthrough', 'uncanny-automator' ) ),
				'target'    => $add_action,
				'attach'    => 'right',
				'wait_for'  => array( $add_action ),
				'advance'   => array(
					'kind'  => 'event',
					'name'  => 'uap.f.recipe-builder.node-selector.ready',
					'match' => array( 'detail.scope' => 'action' ),
				),
			),

			array(
				'id'                 => 'action-integration',
				'surface'            => self::SURFACE,
				'title'              => esc_html__( 'Pick the Emails integration', 'uncanny-automator' ),
				'body_html'          => self::paragraph( esc_html_x( 'Select Emails to send an email when the recipe runs.', 'Create recipe walkthrough', 'uncanny-automator' ) ),
				'target'             => $emails,
				'attach'             => 'left',
				'wait_for'           => array( $emails ),
				'requires'           => $emails,
				'restrict_to_target' => true,
				'advance'            => array(
					'kind'  => 'event',
					'name'  => 'uap.f.recipe-builder.node-selector.step-request-change',
					'match' => array(
						'detail.scenario_id'      => 'integration-ready',
						'detail.integration_code' => 'EMAILS',
					),
				),
			),

			array(
				'id'                 => 'action-item',
				'surface'            => self::SURFACE,
				'title'              => esc_html__( 'Pick an action', 'uncanny-automator' ),
				'body_html'          => self::paragraph( esc_html_x( 'Choose "Send an email".', 'Create recipe walkthrough', 'uncanny-automator' ) ),
				'target'             => $send_email,
				'attach'             => 'left',
				'wait_for'           => array( $send_email ),
				'requires'           => $send_email,
				'restrict_to_target' => true,
				'advance'            => array(
					'kind'  => 'event',
					'name'  => 'uap.f.recipe-builder.node-selector.step-request-change',
					'match' => array(
						'detail.scenario_id' => 'item-ready',
						'detail.item_code'   => 'SENDEMAIL',
					),
				),
			),

			array(
				'id'        => 'action-save',
				'surface'   => self::SURFACE,
				'title'     => esc_html__( 'Configure and save', 'uncanny-automator' ),
				'body_html' => self::paragraph( esc_html_x( 'Fill in the action fields, then save it.', 'Create recipe walkthrough', 'uncanny-automator' ) ),
				'target'    => $edit_dialog,
				'attach'    => 'left',
				'wait_for'  => array( $edit_dialog ),
				'advance'   => array(
					'kind' => 'event',
					'name' => 'uap.f.recipe-builder.flow.item.edit.saved',
				),
			),

			array(
				'id'        => 'recipe-live',
				'surface'   => self::SURFACE,
				'title'     => esc_html__( 'Go live', 'uncanny-automator' ),
				'body_html' => self::paragraph( esc_html_x( 'Your recipe is ready. Use the Recipe on / Recipe off toggle here to turn it on whenever you like.', 'Create recipe walkthrough', 'uncanny-automator' ) ),
				'target'    => self::selector( 'uap-f-recipe-builder-publish-bar' ),
				'attach'    => 'top',
				'advance'   => array( 'kind' => 'button' ),
				'buttons'   => self::button( esc_html__( 'Done', 'uncanny-automator' ), 'next' ),
			),

			array(
				'id'        => 'recipe-created',
				'surface'   => self::SURFACE,
				'title'     => esc_html__( 'You built a recipe', 'uncanny-automator' ),
				'body_html' => self::paragraph( esc_html_x( 'That is the whole flow. Build one of your own next.', 'Create recipe walkthrough', 'uncanny-automator' ) ),
				'advance'   => array( 'kind' => 'button' ),
				'buttons'   => self::button( esc_html__( 'Finish tour', 'uncanny-automator' ), 'complete' ),
			),

		);
	}
}
