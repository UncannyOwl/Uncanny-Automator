<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

/**
 * A stored run message, user name or post title as the plain text the wire
 * carries.
 *
 * Steps record their result messages HTML-escaped (`esc_html_*` on the
 * translated sentence, a convention the legacy HTML log relied on), so the
 * stored rows hold entities like `&#039;`; WordPress stores a user's display
 * name with its `&`, `<` and `>` as entities, and `get_the_title()` returns a
 * title with entities such as `&#038;`. The recipe log renders all of them as
 * text, so each is decoded exactly once on its way onto the wire.
 */
final class Log_Message_Text {

	/**
	 * Decodes one level of HTML entities in a stored message, user name or
	 * post title.
	 *
	 * @param string $stored The message as stored on the log row, a user's display name as WordPress stores it, or a post title as `get_the_title()` returns it.
	 * @return string The plain text.
	 */
	public static function from_stored( string $stored ): string {
		return html_entity_decode( $stored, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}
