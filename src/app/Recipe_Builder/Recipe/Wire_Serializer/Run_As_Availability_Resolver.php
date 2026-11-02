<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Availability_Status;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\Services\User_Selector_Availability;

/**
 * Resolves the wire `run_as_availability` object: the run-as setting's
 * availability on this site, shaped like a flow node's `availability`.
 *
 * Usable when a user resolver is registered; otherwise unavailable, with the
 * message a step that needs Pro carries (the requirement's own description,
 * the runtime framing, and the upgrade link). The publish walk reads the same
 * object, so the wire and the set-live rules cannot disagree.
 *
 * @since 8.0.0
 */
final class Run_As_Availability_Resolver {

	/**
	 * The run-as availability predicate and its unmet requirement.
	 *
	 * @var User_Selector_Availability
	 */
	private User_Selector_Availability $run_as;

	/**
	 * Composes the unavailable message the way node availability does.
	 *
	 * @var Recipe_Entity_Availability_Message_Provider
	 */
	private Recipe_Entity_Availability_Message_Provider $messages;

	/**
	 * Lazy-default DI.
	 *
	 * @param User_Selector_Availability|null                  $run_as   Availability predicate.
	 * @param Recipe_Entity_Availability_Message_Provider|null $messages Message composer.
	 */
	public function __construct( ?User_Selector_Availability $run_as = null, ?Recipe_Entity_Availability_Message_Provider $messages = null ) {
		$this->run_as   = $run_as ?? new User_Selector_Availability();
		$this->messages = $messages ?? new Recipe_Entity_Availability_Message_Provider();
	}

	/**
	 * The run-as availability: `{ status: usable }`, or `{ status: unavailable, message }`.
	 *
	 * @return array{status:string, message?:string}
	 */
	public function resolve(): array {
		if ( $this->run_as->is_usable() ) {
			return array( Wire_Key::STATUS => Availability_Status::USABLE );
		}

		return array(
			Wire_Key::STATUS  => Availability_Status::UNAVAILABLE,
			Wire_Key::MESSAGE => $this->messages->message( $this->run_as->requirement(), 'run_as' ),
		);
	}
}
