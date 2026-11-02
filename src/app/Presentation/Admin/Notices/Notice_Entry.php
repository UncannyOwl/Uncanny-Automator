<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin\Notices;

/**
 * One notice as a skin receives it: its id, what it says, how it may be put
 * away and, for a stored dismissal, the address that stores it. A preview
 * (the show-all review) changes nothing: its buttons post no form. A folded
 * entry is past its kind's limit ({@see Notice_Limits}): it waits behind
 * one "more notices" row.
 *
 * @since 8.0.0
 */
final class Notice_Entry {

	/**
	 * @var string
	 */
	private $id;

	/**
	 * @var Notice_Message
	 */
	private $message;

	/**
	 * @var Notice_Dismissal
	 */
	private $dismissal;

	/**
	 * @var string
	 */
	private $dismiss_url;

	/**
	 * @var bool
	 */
	private $preview;

	/**
	 * @var bool
	 */
	private $folded = false;

	/**
	 * @param string           $id          The notice's id.
	 * @param Notice_Message   $message     What it says.
	 * @param Notice_Dismissal $dismissal   How it may be put away.
	 * @param string           $dismiss_url Nonced address that stores the dismissal; '' unless stored.
	 * @param bool             $preview     Whether it is a preview, for the show-all review.
	 */
	public function __construct( string $id, Notice_Message $message, Notice_Dismissal $dismissal, string $dismiss_url, bool $preview = false ) {

		$this->id          = $id;
		$this->message     = $message;
		$this->dismissal   = $dismissal;
		$this->dismiss_url = $dismiss_url;
		$this->preview     = $preview;
	}

	/**
	 * @return string
	 */
	public function id(): string {

		return $this->id;
	}

	/**
	 * @return Notice_Message
	 */
	public function message(): Notice_Message {

		return $this->message;
	}

	/**
	 * @return Notice_Dismissal
	 */
	public function dismissal(): Notice_Dismissal {

		return $this->dismissal;
	}

	/**
	 * @return string Nonced address that stores the dismissal; '' unless stored.
	 */
	public function dismiss_url(): string {

		return $this->dismiss_url;
	}

	/**
	 * @return bool Whether it is a preview, for the show-all review.
	 */
	public function is_preview(): bool {

		return $this->preview;
	}

	/**
	 * The same entry, past its kind's limit.
	 *
	 * @return self
	 */
	public function folded(): self {

		$folded         = clone $this;
		$folded->folded = true;

		return $folded;
	}

	/**
	 * @return bool Whether it waits behind the "more notices" row.
	 */
	public function is_folded(): bool {

		return $this->folded;
	}
}
