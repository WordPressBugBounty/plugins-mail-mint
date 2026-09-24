# Automation engine

## Overview

This is the PHP runtime for automations. A trigger fires, then the steps (actions, delays, conditions) run in order through Action Scheduler. The visual builder is separate and lives in `src/automation/`. Pro adds its own connectors, triggers, and actions on top of this through filters.

## Key files

| File | Owns |
|---|---|
| `AutomationManager.php` | Listens on `MINT_TRIGGER_AUTOMATION` and `MINT_PROCESS_AUTOMATION`, finds matching automations, runs each step |
| `Connectors/AutomationConnector.php` | Builds the connector list (`WP`, `MintForm`, `WpfnlCore`), filterable via `mrm_automation_connectors` |
| `Connectors/*/Triggers/` | PHP listeners that watch a WordPress event and fire `do_action( MINT_TRIGGER_AUTOMATION, $data )` with `connector_name` and `trigger_name` |
| `Actions/` | Step actions: `AddList`, `AddTag`, `SendMail`, `Delay` |
| `Core/DataStore/` | Stores for automations, steps, jobs, and logs |
| `Core/API/` | REST controllers and routes the builder calls |
| `TriggerCatalog.php` | Generated file. The static list of every trigger the builder offers |
| `TriggerAvailability.php` | Answers "can this site use this trigger right now?" at runtime |

## Commands

```bash
# Rebuild TriggerCatalog.php from the builder registry
npm run generate:trigger-catalog

# Fail if the committed catalog is out of date
npm run check:trigger-catalog
```

## Conventions

- The builder registry in `src/automation/integrations/core/triggers/` is the source of truth for what a trigger is. When you add, remove, or change the gating of a trigger there, run `npm run generate:trigger-catalog`. Never edit `TriggerCatalog.php` by hand.
- Every trigger carries a `package` of `'free'` or `'pro'`. The generator treats any other value as Pro, so a typo quietly hides a Free trigger.
- Ask `TriggerAvailability::is_available()` whether a trigger can be used. The catalog only describes shape, not availability.
- Namespaces here are an outlier. Most files use `MintMail\App\Internal\Automation\...` with singular sub namespaces (`Action`, `Connector`) even though the folders are plural (`Actions/`, `Connectors/`). Files in `Core/API/` use `Mint\MRM\Admin\API\Controllers` and `Mint\MRM\Admin\API\Routes`. Copy the namespace from a neighbor file.
- Hook and constant names (`MINT_TRIGGER_AUTOMATION` and friends) are defined in `mail-mint.php`.

## Gotchas

- Pro ships classes in the same `MintMail\App\Internal\Automation` namespace, and PHP class names are case insensitive. A Free class whose name matches a Pro one in any casing collides, and one side silently wins. That is why the free WPFunnels connector is named `WpfnlCore`. Pick names Pro cannot clash with, and register Free connectors under their own key.
- When connectors share a key, `AutomationConnector` merges their trigger lists. Keep it a merge; overwriting makes Free or Pro triggers vanish from the builder.
- The MCP tools and the AI copilot read `TriggerCatalog`. A stale catalog makes the assistant tell users a trigger they can see in the builder does not exist.
- `tests/unit-test/php/actions/internal/automation/test-trigger-catalog.php` (class `TriggerCatalogTest`) fails the suite when the catalog drifts from the builder.

## Agent skills

- [pro-feature-boundary](../../../.ai/skills/pro-feature-boundary/): project skill, decides what belongs in Free versus Pro before you add a trigger or action.

_Drafted by /audit from the repo, worth a quick human pass. Edit freely: once a line stops matching this draft, later runs treat it as curated and will flag rather than overwrite it._
