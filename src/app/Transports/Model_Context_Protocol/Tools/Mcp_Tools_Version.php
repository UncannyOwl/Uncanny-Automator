<?php
/**
 * The version of this plugin's MCP tool contracts.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

/**
 * One number for the set of MCP tools this plugin offers and their contracts:
 * each tool's name, title, description, input schema, output schema and annotations.
 * It is reported in get_site_context as mcp_tools_version.
 *
 * Why a separate version:
 *
 * An MCP client may keep a copy of this site's tool list instead of asking
 * for it on every request, and needs to know when that copy is out of date.
 * The plugin version is a poor signal for that. Many releases contain only
 * integration updates without any changes to the tools themselves, so a
 * client that relied on it would re-read the tool list after every release
 * for nothing. This number changes only when the tools do, so a client
 * re-reads only then. Clients can read it from get_site_context, which they
 * may already call, so checking it adds no request.
 *
 * It also lets a client tell that a tool needs a newer plugin: a site that
 * reports an older tools version can be asked to update, instead of the tool
 * silently being missing.
 *
 * The tradeoff is that this number has to be maintained, but in return tool
 * changes are tracked deliberately. Mcp_Tools_Version_Test fails when any
 * tool contract changes without a new version, so the bump cannot be
 * forgotten. Bump it when the set of tools or a tool's contract changes:
 *
 * - major (2.0.0): a tool's contract changes in a way existing callers break on,
 *   such as a removed tool, input or output field, or a stricter schema;
 * - minor (1.1.0): a tool is added, or a tool gains something optional;
 * - patch (1.0.1): only descriptions change.
 *
 * A site that does not report a number runs a plugin from before 1.0.0.
 */
final class Mcp_Tools_Version {

	/** The current tools version. */
	public const VERSION = '6.5.0';
}
