<?php
/**
 * AbilitiesRegistrar — single source of truth for all free MCP tool definitions.
 *
 * Calls wp_register_ability() for each of the free tools, using
 * wrapExecuteCallback() to provide consistent error handling.
 *
 * @package Mint\MRM\Internal\MCP
 */

namespace Mint\MRM\Internal\MCP;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\Internal\MCP\Tools\ContextTools;
use Mint\MRM\Internal\MCP\Tools\ContactTools;
use Mint\MRM\Internal\MCP\Tools\CampaignTools;
use Mint\MRM\Internal\MCP\Tools\AutomationTools;
use Mint\MRM\Internal\MCP\Tools\SegmentTools;
use Mint\MRM\Internal\MCP\Tools\EmailTools;
use Mint\MRM\Internal\MCP\Tools\TemplateTools;
use Mint\MRM\Internal\MCP\Tools\FormTools;
use Mint\MRM\Internal\MCP\Tools\ReportTools;
use Mint\MRM\Internal\MCP\Tools\CustomFieldTools;

class AbilitiesRegistrar {

	private static $definitions = null;

	/**
	 * Build and return all free tool definitions (keyed by tool name).
	 *
	 * @return array
	 */
	public static function getDefinitions(): array {
		if ( null !== self::$definitions ) {
			return self::$definitions;
		}

		self::$definitions = array_merge(
			array(
				'mail-mint/get-crm-context'          => ContextTools::definition(),
				'mail-mint/check-setup-status'       => ContextTools::setupDefinition(),
				'mail-mint/update-email-settings'    => ContextTools::updateEmailSettingsDefinition(),
				'mail-mint/update-business-settings' => ContextTools::updateBusinessSettingsDefinition(),
			),
			ContactTools::definitions(),
			CampaignTools::definitions(),
			AutomationTools::definitions(),
			SegmentTools::definitions(),
			EmailTools::definitions(),
			TemplateTools::definitions(),
			FormTools::definitions(),
			ReportTools::definitions(),
			CustomFieldTools::definitions()
		);

		return self::$definitions;
	}

	/**
	 * Register all free abilities via wp_register_ability().
	 */
	public static function register(): void {
		foreach ( self::getDefinitions() as $name => $args ) {
			$args['category'] = 'mail-mint';

			// Captured before the annotations array is folded into meta below.
			$is_destructive = ! empty( $args['annotations'] )
				&& is_array( $args['annotations'] )
				&& in_array( 'destructive', $args['annotations'], true );

			// WP_Ability expects annotations inside meta as key=>bool pairs,
			// not as a top-level indexed array of strings.
			if ( isset( $args['annotations'] ) && is_array( $args['annotations'] ) ) {
				$map = array();
				foreach ( $args['annotations'] as $flag ) {
					$map[ $flag ] = true;
				}
				$args['meta']['annotations'] = array_merge(
					$args['meta']['annotations'] ?? array(),
					$map
				);
				unset( $args['annotations'] );
			}

			$args['execute_callback'] = self::wrapExecuteCallback(
				$args['execute_callback'],
				$name,
				$is_destructive
			);
			wp_register_ability( $name, $args );
		}
	}

	/**
	 * Wrap an execute callback with two cross-cutting guards:
	 *
	 *   1. Server-side hard stop — a destructive tool refuses to act unless the
	 *      caller passes confirm:true, returning a confirmation-required preview
	 *      instead. The in-plugin copilot injects confirm:true only AFTER the
	 *      user approves (see AgentLoop::confirm); an external MCP client must
	 *      send it deliberately. So the hard stop holds for EVERY client, not
	 *      just the copilot.
	 *   2. Exception → structured WP_Error.
	 *
	 * @param callable $cb            The tool's execute callback.
	 * @param string   $tool_name     Fully-qualified ability name.
	 * @param bool     $is_destructive Whether the tool is annotated destructive.
	 * @return callable
	 */
	public static function wrapExecuteCallback( callable $cb, string $tool_name, bool $is_destructive = false ): callable {
		return function ( array $params ) use ( $cb, $tool_name, $is_destructive ) {
			if ( $is_destructive && empty( $params['confirm'] ) ) {
				return array(
					'confirmation_required' => true,
					'tool'                  => $tool_name,
					'message'               => 'This action changes or deletes data and needs confirmation. Re-call this tool with "confirm": true to execute it.',
					'pending_action'        => $params,
				);
			}
			// Never forward the control flag to the tool: keeps tool logic and
			// idempotency hashing identical to a call that never used confirm.
			unset( $params['confirm'] );

			try {
				return call_user_func( $cb, $params );
			} catch ( \Throwable $e ) {
				do_action( 'mail_mint/mcp_tool_exception', $e, $tool_name, $params );
				return new \WP_Error(
					'mcp_tool_exception',
					sprintf(
						'Tool "%s" threw an exception: %s',
						$tool_name,
						$e->getMessage()
					),
					array( 'exception' => get_class( $e ) )
				);
			}
		};
	}
}
