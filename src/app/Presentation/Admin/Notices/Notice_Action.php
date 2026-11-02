<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin\Notices;

use InvalidArgumentException;

/**
 * A button on a notice: a link, a form posted to admin-post.php with its
 * nonce, or the notice's own dismiss. A value; the skin draws it.
 *
 * The label is escaped text (`esc_html_x()`). The description, shown beside a
 * choice, is escaped HTML the notice built.
 *
 * @since 8.0.0
 */
final class Notice_Action {

	public const LINK    = 'link';
	public const POST    = 'post';
	public const DISMISS = 'dismiss';

	/**
	 * @var string
	 */
	private $kind;

	/**
	 * @var string
	 */
	private $label;

	/**
	 * @var string
	 */
	private $url = '';

	/**
	 * @var string
	 */
	private $post_action = '';

	/**
	 * @var string
	 */
	private $nonce_action = '';

	/**
	 * @var array<string, string>
	 */
	private $fields = array();

	/**
	 * @var bool
	 */
	private $primary = false;

	/**
	 * @var string
	 */
	private $description = '';

	/**
	 * @var bool
	 */
	private $dismissing = false;

	/**
	 * @var bool
	 */
	private $new_tab = false;

	/**
	 * @param string $kind  One of the constants.
	 * @param string $label Escaped text.
	 *
	 * @throws InvalidArgumentException For an empty label.
	 */
	private function __construct( string $kind, string $label ) {

		if ( '' === trim( $label ) ) {
			throw new InvalidArgumentException( 'A notice action needs a label.' );
		}

		$this->kind  = $kind;
		$this->label = $label;
	}

	/**
	 * A link.
	 *
	 * @param string $label Escaped text.
	 * @param string $url   Where it goes.
	 *
	 * @return self
	 *
	 * @throws InvalidArgumentException For an empty address.
	 */
	public static function link( string $label, string $url ): self {

		if ( '' === trim( $url ) ) {
			throw new InvalidArgumentException( 'A notice link needs an address.' );
		}

		$action      = new self( self::LINK, $label );
		$action->url = $url;

		return $action;
	}

	/**
	 * A form posted to admin-post.php.
	 *
	 * @param string                $label        Escaped text.
	 * @param string                $post_action  The admin-post `action`.
	 * @param string                $nonce_action The nonce action the handler checks.
	 * @param array<string, string> $fields       Hidden fields.
	 *
	 * @return self
	 *
	 * @throws InvalidArgumentException For an empty admin-post action or nonce action.
	 */
	public static function post( string $label, string $post_action, string $nonce_action, array $fields = array() ): self {

		if ( '' === $post_action || '' === $nonce_action ) {
			throw new InvalidArgumentException( 'A notice form needs its admin-post action and nonce action.' );
		}

		$action               = new self( self::POST, $label );
		$action->post_action  = $post_action;
		$action->nonce_action = $nonce_action;
		$action->fields       = array_map( 'strval', $fields );

		return $action;
	}

	/**
	 * The notice's own dismiss, as a button.
	 *
	 * @param string $label Escaped text.
	 *
	 * @return self
	 */
	public static function dismiss( string $label ): self {

		return new self( self::DISMISS, $label );
	}

	/**
	 * The same action, drawn as the notice's main button.
	 *
	 * @return self
	 */
	public function primary(): self {

		$action          = clone $this;
		$action->primary = true;

		return $action;
	}

	/**
	 * The same link, which also dismisses the notice when followed (the
	 * dismissal is stored in the background; the link still navigates).
	 *
	 * @return self
	 */
	public function dismissing(): self {

		$action             = clone $this;
		$action->dismissing = true;

		return $action;
	}

	/**
	 * @return bool Whether following it also dismisses the notice.
	 */
	public function is_dismissing(): bool {

		return $this->dismissing;
	}

	/**
	 * The same link, opened in a new tab: for a page outside wp-admin the
	 * admin should come back from.
	 *
	 * @return self
	 */
	public function in_new_tab(): self {

		$action          = clone $this;
		$action->new_tab = true;

		return $action;
	}

	/**
	 * @return bool Whether the link opens in a new tab.
	 */
	public function opens_new_tab(): bool {

		return $this->new_tab;
	}

	/**
	 * The same action, with a line beside it that says what choosing it does.
	 *
	 * @param string $description Escaped HTML.
	 *
	 * @return self
	 */
	public function with_description( string $description ): self {

		$action              = clone $this;
		$action->description = $description;

		return $action;
	}

	/**
	 * @return string One of the constants.
	 */
	public function kind(): string {

		return $this->kind;
	}

	/**
	 * @return string Escaped text.
	 */
	public function label(): string {

		return $this->label;
	}

	/**
	 * @return string The link's address; '' for any other kind.
	 */
	public function url(): string {

		return $this->url;
	}

	/**
	 * @return string The admin-post `action`; '' unless a form.
	 */
	public function post_action(): string {

		return $this->post_action;
	}

	/**
	 * @return string The nonce action; '' unless a form.
	 */
	public function nonce_action(): string {

		return $this->nonce_action;
	}

	/**
	 * @return array<string, string> The form's hidden fields.
	 */
	public function fields(): array {

		return $this->fields;
	}

	/**
	 * @return bool
	 */
	public function is_primary(): bool {

		return $this->primary;
	}

	/**
	 * @return string Escaped HTML; '' when there is none.
	 */
	public function description(): string {

		return $this->description;
	}
}
