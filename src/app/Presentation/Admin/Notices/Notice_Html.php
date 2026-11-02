<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin\Notices;

/**
 * The HTML both skins share: the markup a notice's text may keep, and the
 * hidden fields of a form action.
 *
 * @since 8.0.0
 */
final class Notice_Html {

	/**
	 * What a paragraph, list row or choice description may contain.
	 */
	private const ALLOWED = array(
		'a'      => array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
		),
		'code'   => array(),
		'strong' => array(),
		'em'     => array(),
		'br'     => array(),
	);

	/**
	 * Keep only the markup a notice's text may use.
	 *
	 * @param string $html Escaped HTML from a notice.
	 *
	 * @return string
	 */
	public static function text( string $html ): string {

		return wp_kses( $html, self::ALLOWED );
	}

	/**
	 * A notice's escaped text as plain text, for an attribute (which escapes
	 * it again).
	 *
	 * @param string $html Escaped text from a notice.
	 *
	 * @return string
	 */
	public static function plain( string $html ): string {

		return wp_specialchars_decode( wp_strip_all_tags( $html ), ENT_QUOTES );
	}

	/**
	 * A form action's hidden fields: the admin-post action, its own fields,
	 * the nonce and the referer field the handler returns through.
	 *
	 * @param Notice_Action $action A Notice_Action::POST action.
	 *
	 * @return string
	 */
	public static function hidden_fields( Notice_Action $action ): string {

		$html = '<input type="hidden" name="action" value="' . esc_attr( $action->post_action() ) . '" />';

		foreach ( $action->fields() as $name => $value ) {
			$html .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
		}

		$html .= wp_nonce_field( $action->nonce_action(), '_wpnonce', true, false );

		return $html;
	}

	/**
	 * A message's report, folded behind its label: a read-only box the admin
	 * can select and copy. '' when the message has none.
	 *
	 * @param Notice_Message $message The message.
	 *
	 * @return string
	 */
	public static function report( Notice_Message $message ): string {

		$report = $message->report();
		if ( null === $report ) {
			return '';
		}

		$rows = min( count( explode( "\n", $report['text'] ) ), 12 );

		return '<details class="automator-notice-report"><summary>' . self::text( $report['label'] ) . '</summary>'
			. '<textarea readonly rows="' . (int) $rows . '" onclick="this.select();">' . esc_textarea( $report['text'] ) . '</textarea></details>';
	}

	/**
	 * Whether any of the actions explains itself, so they are laid out as
	 * choices, each beside what it does.
	 *
	 * @param Notice_Action[] $actions The actions.
	 *
	 * @return bool
	 */
	public static function are_choices( array $actions ): bool {

		foreach ( $actions as $action ) {
			if ( '' !== $action->description() ) {
				return true;
			}
		}

		return false;
	}
}
