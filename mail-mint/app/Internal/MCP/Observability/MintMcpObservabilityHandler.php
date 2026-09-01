<?php
/**
 * MintMcpObservabilityHandler — audit sink for the Mail Mint MCP server.
 *
 * Wraps the adapter's error-log handler and drops the events that the adapter
 * emits while building the server, keeping only the events that describe
 * something a site owner would actually want in an audit trail.
 *
 * @package Mint\MRM\Internal\MCP\Observability
 */

namespace Mint\MRM\Internal\MCP\Observability;

defined( 'ABSPATH' ) || exit;

use WP\MCP\Infrastructure\Observability\ErrorLogMcpObservabilityHandler;

/**
 * Logs MCP tool traffic without the per-request lifecycle noise.
 */
class MintMcpObservabilityHandler extends ErrorLogMcpObservabilityHandler {

	/**
	 * Events the adapter emits as a side effect of constructing the server,
	 * whether or not anyone is using MCP.
	 *
	 * The adapter builds every registered server on `rest_api_init`, so passing
	 * these through to error_log() writes one line per REST request on every
	 * site — hundreds of thousands of lines on a busy store, none of them
	 * actionable. Tool traffic (`mcp.request`) is still logged.
	 *
	 * @var string[]
	 */
	private const LIFECYCLE_EVENTS = array(
		'mcp.server.created',
		'mcp.component.registration',
	);

	/**
	 * Record an event unless it is per-request lifecycle noise.
	 *
	 * @param string     $event       The event name to record.
	 * @param array      $tags        Optional tags to attach to the event.
	 * @param float|null $duration_ms Optional duration in milliseconds.
	 * @return void
	 */
	public function record_event( string $event, array $tags = array(), ?float $duration_ms = null ): void {
		if ( in_array( self::format_metric_name( $event ), self::LIFECYCLE_EVENTS, true ) ) {
			return;
		}

		parent::record_event( $event, $tags, $duration_ms );
	}
}
