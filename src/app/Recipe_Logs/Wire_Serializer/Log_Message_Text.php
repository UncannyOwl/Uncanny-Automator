<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

/**
 * A stored run message as the plain text the wire carries.
 *
 * Steps record their result messages HTML-escaped (`esc_html_*` on the
 * translated sentence, a convention the legacy HTML log relied on), so the
 * stored rows hold entities like `&#039;`. The recipe log renders
 * `result_message` as text, so every stored message is decoded exactly once
 * on its way onto the wire.
 */
final class Log_Message_Text {

	/**
	 * Decodes one level of HTML entities in a stored message.
	 *
	 * @param string $stored The message as stored on the log row.
	 * @return string The message as plain text.
	 */
	public static function from_stored( string $stored ): string {
		return html_entity_decode( $stored, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}
