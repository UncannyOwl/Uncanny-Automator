<?php
/**
 * Run a site command that changes the site, in WP-CLI-style syntax.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Site_Commands;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Site_Commands\Application\Index_Site_Commands;
use Uncanny_Automator\App\Site_Commands\Application\Run_Site_Command;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Line\Command_Parser;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Formats;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Formatter;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Manage\Builtin_Site_Manage_Catalog;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Request_Time_Budget;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * The commands that change the site. They run unless the site owner turned
 * this tool off (see Site_Management_Settings), and only with a token that
 * may make changes. Reading stays with site_command.
 */
class Site_Manage_Tool extends Abstract_MCP_Tool {

	public const NAME = 'site_manage';

	private Run_Site_Command $runner;

	/**
	 * Whether the runner seals its capture buffer; see Site_Command_Tool.
	 *
	 * @var bool
	 */
	private bool $sealed;

	/**
	 * Use the built-in commands, with WordPress's JSON encoder and a sealed
	 * capture buffer, unless a runner is supplied. These commands change the
	 * site, so they run without read isolation.
	 *
	 * @param Run_Site_Command|null $runner Command runner.
	 */
	public function __construct( ?Run_Site_Command $runner = null ) {
		$this->sealed = null === $runner;
		$this->runner = $runner ?? new Run_Site_Command(
			new Index_Site_Commands( new Builtin_Site_Manage_Catalog() ),
			null,
			null,
			new Formatter( 'wp_json_encode' ),
			null,
			true
		);
	}

	/**
	 * The MCP tool name.
	 *
	 * @return string
	 */
	public function get_name() {
		return self::NAME;
	}

	/**
	 * What the tool does, for the AI client.
	 *
	 * @return string
	 */
	public function get_description() {
		return 'Change the site with familiar WP-CLI-style commands. It can: install plugins and themes from WordPress.org; update plugins, themes and translations; activate and deactivate plugins, switch themes, and turn their automatic updates on or off; approve or unapprove comments, mark them as spam or take them out of spam; allow or discourage search engines; install a language and switch the site\'s language; start due cron events; flush the object cache, rewrite rules and expired transients; and show the maintenance page, to visitors and in wp-admin, for 1 to 10 minutes, at most once an hour. This connection gets the maintenance page too, so no command answers until it ends. For example "plugin update akismet" or "comment spam 12,15". '
			. 'Commands are built into Automator in PHP; there is no shell. They never delete or uninstall plugins, themes, comments or content, and packages come only from WordPress.org or from updates WordPress already offers. WordPress itself is not updated here. '
			. 'Each request plans its work to answer within ' . Request_Time_Budget::RESPONSE_SECONDS . ' seconds, or the host\'s shorter PHP time limit: work that cannot finish in the time left is not started, and the answer says so and what to run next. A slow server, such as one slow to look up other sites\' addresses, can still make an answer later. Cron events run in WordPress\'s own cron runner, not in this request. '
			. 'Run "help" to list the commands and "help <command>" for its options. Check the state first and afterwards with site_command, such as "plugin list". '
			. 'Updates, activations and theme switches can break a site, and there is no rollback: WordPress keeps the previous version only during an update. Before changing a live site, tell the user this and suggest trying the change on a staging site first, or ask whether they have a backup. After a change, only the home page and the REST API index are checked for a PHP fatal error; other pages are not. '
			. 'Errors say whether anything changed. Changes to several items list each one\'s result; --format=' . implode( '|', Formats::ALL ) . ' applies to them.';
	}

	/**
	 * Changes the site, can remove a plugin from use, and downloads from WordPress.org.
	 *
	 * @return array
	 */
	protected function get_annotations(): array {
		return array(
			'readOnlyHint'    => false,
			'destructiveHint' => true,
			'idempotentHint'  => false,
			'openWorldHint'   => true,
		);
	}

	/**
	 * One command string.
	 *
	 * @return array
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'command' => array(
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => Command_Parser::MAX_LENGTH,
					'description' => 'One command, such as "plugin update akismet". A leading "wp" is optional.',
				),
			),
			'required'   => array( 'command' ),
		);
	}

	/**
	 * No output schema; command output is plain terminal text.
	 *
	 * @return array|null
	 */
	protected function output_schema_definition(): ?array {
		return null;
	}

	/**
	 * Run the command and return its terminal text.
	 *
	 * @param User_Context $user_context Verified caller; the base class has already required an administrator.
	 * @param array        $params       Validated tool input.
	 * @return array MCP tool result: plain text content, then stats as a second
	 *               text block when there are any, with isError on failure.
	 */
	protected function execute_tool( User_Context $user_context, array $params ) {
		if ( $this->sealed ) {
			Site_Command_Tool::flush_safely_at_shutdown();
		}
		$result   = $this->runner->execute( (string) $params['command'], true );
		$response = array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $result->output(),
				),
			),
		);
		if ( '' !== $result->stats() ) {
			$response['content'][] = array(
				'type' => 'text',
				'text' => $result->stats(),
			);
		}
		if ( ! $result->is_success() ) {
			$response['isError'] = true;
		}

		return $response;
	}
}
