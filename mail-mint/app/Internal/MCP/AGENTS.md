# MCP tools and AI copilot

## Overview

This folder registers Mail Mint's tools with the WordPress Abilities API and serves them over MCP at `POST /wp-json/mrm/mcp`. The in plugin AI copilot (`app/Internal/AI/`) calls the very same abilities through `ToolGateway`, so one tool surface serves both external MCP clients and the copilot. Pro adds its own tools when `mail_mint/mcp_loaded` fires.

## Key files

| File | Owns |
|---|---|
| `MCPInit.php` | Registers the `mail-mint` ability category, the abilities, and the MCP server route; fires `mail_mint/mcp_loaded` |
| `AbilitiesRegistrar.php` | Merges every `Tools/*::definitions()` and calls `wp_register_ability()`; wraps each callback with the destructive guard |
| `Tools/*.php` | One file per domain (contacts, campaigns, automations, segments, forms, and so on) |
| `Helpers/MCPHelper.php` | Pagination, error factory, idempotency helpers |
| `Helpers/EmailComposer.php` | Turns structured email input into builder JSON and HTML |
| `../AI/ToolGateway.php` | Bridges the copilot's agent loop to the abilities; holds the `ALWAYS_CONFIRM` list |
| `../AI/AgentLoop.php`, `../AI/Providers/` | The copilot loop and the Anthropic, OpenAI, Gemini, and WordPress AI adapters |

## Conventions

- A tool is one entry in a `definitions()` array, keyed `mail-mint/<kebab-name>`, with `label`, `description`, `input_schema`, `execute_callback`, `permission_callback`, and `annotations`. Add a new domain file to the `array_merge` in `AbilitiesRegistrar::getDefinitions()`.
- Permission checks go through `PermissionManager::current_user_can( 'mint_...' )`. `WP_Ability::execute()` runs them as the logged in user, so never bypass them.
- Mark any tool that deletes or changes live state with the `destructive` annotation. The registrar then refuses to run it unless the caller sends `confirm: true`, for every client, not only the copilot. If the copilot must always ask the user first, also add the tool to `ToolGateway::ALWAYS_CONFIRM`.
- Keep tool results small. `ToolGateway` truncates any result over 12000 characters before it reaches the model.
- The full tool reference lives in `.ai/mcp-tools.md`. Update it when you add or rename a tool.

## Gotchas

- MCP boots from `app/Internal/Actions/Hooks.php` on `init`, gated only on `function_exists( 'wp_register_ability' )` and the `_mrm_mcp_enabled` option (default `yes`). There is no PHP version gate, so every file here must parse on PHP 7.4, the plugin's declared minimum.
- Automation tools read `Automation/TriggerCatalog.php`. If a trigger is missing from the copilot's answers, regenerate the catalog (see `app/Internal/Automation/AGENTS.md`).
- `tools/mcp-server/` at the repo root is a different thing: a local dev MCP server (phpcs, phpunit, env status) for coding agents, wired in `.mcp.json`. It is not shipped with the plugin.

_Drafted by /audit from the repo, worth a quick human pass. Edit freely: once a line stops matching this draft, later runs treat it as curated and will flag rather than overwrite it._
