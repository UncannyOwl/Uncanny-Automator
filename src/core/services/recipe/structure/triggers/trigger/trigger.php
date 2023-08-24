<?php
namespace Uncanny_Automator\Services\Recipe\Structure\Triggers\Trigger;

use stdClass;
use Uncanny_Automator\Services\Recipe\Common;
use Uncanny_Automator\Services\Recipe\Structure\Fields;

/**
 * This class represents the trigger object under the triggers object in the recipe object.
 *
 * @package Uncanny_Automator\Services\Recipe\Structure\Triggers\Trigger
 *
 * @since 5.0
 */
final class Trigger implements \JsonSerializable {

	use Common\Trait_JSON_Serializer;

	protected $type             = 'trigger';
	protected $is_item_on       = false;
	protected $id               = null;
	protected $integration_code = null;
	protected $code             = null;
	protected $miscellaneous    = null;
	protected $backup           = null;
	protected $fields           = null;
	protected $tokens           = null;

	private static $recipe = null;

	public function __construct( $recipe ) {
		self::$recipe = $recipe;
	}
	/**
	 * The property can_log_in_new_user and uses_credit
	 * is an official Trigger property.
	 *
	 * We dont want to create a new class for it.
	 * Lets just compose it for the sake of UI.
	 *
	 * @param mixed[] $trigger;
	 *
	 * @return object
	 */
	private function miscellaneous( $trigger ) {

		$misc = new stdClass();

		$trigger_arr_from_object = Automator()->get_trigger( $trigger['meta']['code'] );

		$misc->uses_credit = isset( $trigger_arr_from_object['uses_api'] );

		$misc->can_log_in_new_user = false;

		if ( isset( $trigger['meta']['can_log_in_new_user'] ) ) {
			$misc->can_log_in_new_user = ( 'false' === $trigger['meta']['can_log_in_new_user'] ) ? false : true;
		}

		$misc->uses_credit = isset( $trigger_arr_from_object['uses_api'] );

		return $misc;

	}

	private function backup( $trigger ) {

		$backup = new stdClass();

		$integration = Automator()->get_integration( $trigger['meta']['integration'] );

		$sentence_html = isset( $trigger['meta']['sentence_human_readable_html'] )
			? htmlentities( $trigger['meta']['sentence_human_readable_html'], ENT_QUOTES )
			: htmlentities( $trigger['meta']['sentence_human_readable'], ENT_QUOTES );

		$backup->integration_name = is_array( $integration ) && isset( $integration['name'] ) ? $integration['name'] : '';
		$backup->sentence         = htmlentities( $trigger['meta']['sentence_human_readable'], ENT_QUOTES );
		$backup->sentence_html    = $sentence_html;

		return $backup;

	}

	/**
	 * @param mixed[] $trigger.
	 *
	 * @return self;
	 */
	public function hydrate_from( $trigger = array() ) {

		$fields = new Fields( $trigger, self::$recipe );
		$tokens = new Tokens( $trigger );

		$this->id               = $trigger['ID'];
		$this->is_item_on       = 'publish' === $trigger['post_status'];
		$this->integration_code = $trigger['meta']['integration'];
		$this->code             = $trigger['meta']['code'];
		$this->miscellaneous    = $this->miscellaneous( $trigger );
		$this->backup           = $this->backup( $trigger );
		$this->fields           = $fields->get_fields();
		$this->tokens           = $tokens->get_tokens( $fields->get_original_fields() );

		return $this;
	}
}
