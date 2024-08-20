<?php

final class Traits_Trigger_Token_Provider {

	public static function trigger_token_data( $token_identifier ) {

		$value = '';

		$pieces = array(
			0 => '4397',
			1 => $token_identifier,
			2 => $token_identifier,
		);

		$recipe_id = 4380;

		$trigger_data = array(
			0 => array(
				'ID'          => 4397,
				'post_status' => 'publish',
				'meta'        =>
				array(
					'code'                                 => $token_identifier,
					'integration'                          => 'METABOX',
					'uap_trigger_version'                  => '4.2.1.1',
					'add_action'                           => 'a:2:{i:0;s:15:"added_post_meta";i:1;s:17:"updated_post_meta";}',
					'integration_name'                     => 'Metabox',
					'sentence'                             => 'A user updates {{a field:METABOX_USER_POST_FIELD_UPDATED_META}} on {{a post:POST_ID:METABOX_USER_POST_FIELD_UPDATED_META}}',
					'sentence_human_readable'              => 'A user updates {{Any field}} on {{Any post}}',
					'sentence_human_readable_html'         => '<div><span class="item-title__normal">A user updates </span><span class="item-title__token item-title__token--filled" data-token-id="METABOX_USER_POST_FIELD_UPDATED_META" data-options-id="METABOX_USER_POST_FIELD_UPDATED_META">Any field</span><span class="item-title__normal"> on </span><span class="item-title__token item-title__token--filled" data-token-id="POST_ID" data-options-id="METABOX_USER_POST_FIELD_UPDATED_META">Any post</span></div>',
					'POST_TYPE_readable'                   => 'Post',
					'POST_TYPE'                            => 'post',
					'POST_ID_readable'                     => 'Any post',
					'POST_ID'                              => '-1',
					'METABOX_USER_POST_FIELD_UPDATED_META_readable' => 'Any field',
					'METABOX_USER_POST_FIELD_UPDATED_META' => '-1',
				),
				'tokens'      => array(),
			),
		);

		$user_id = 1;

		$replace_args = array(
			'pieces'         =>
			array(
				0 => '4397',
				1 => $token_identifier,
				2 => 'POST_TYPE',
			),
			'recipe_id'      => 1,
			'recipe_log_id'  => 1,
			'trigger_id'     => 1,
			'trigger_log_id' => 1,
			'run_number'     => 1,
			'user_id'        => 1,
		);

		return array(
			array(
				$value,
				$pieces,
				$recipe_id,
				$trigger_data,
				$user_id,
				$replace_args,
			),
		);
	}
	public static function trigger_data( $trigger_code, $mocked_trigger ) {

		return array(
			array(
				array(
					'entry_args'    => array(
						'code'             => $trigger_code,
						'meta'             => 'DOESNT_MATTER',
						'user_id'          => 88667,
						'trigger_to_match' => 9999,
						'recipe_to_match'  => 9999,
						'ignore_post_id'   => true,
					),
					'trigger_args'  => array(
						'some_data_here',
						'passed_from_trigger_hook',
						'UncannyAutomatorFTW!',
					),
					'trigger_entry' =>
					array(
						'code'             => 'METABOX_USER_POST_FIELD_UPDATED',
						'meta'             => 'METABOX_USER_POST_FIELD_UPDATED_META',
						'post_id'          => 0,
						'user_id'          => 1,
						'recipe_to_match'  => 4380,
						'trigger_to_match' => 4397,
						'ignore_post_id'   => true,
						'is_signed_in'     => true,
						'get_trigger_id'   => 2694,
						'trigger_log_id'   => 2694,
						'recipe_id'        => 4380,
						'trigger_id'       => 4397,
						'recipe_log_id'    => 2589,
						'run_number'       => '10',
					),
				),
				$mocked_trigger,
			),
		);

	}

}
