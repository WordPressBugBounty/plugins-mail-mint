# Implementation Plan: Exit Intent Display Toggle for Popup Forms

**Branch**: `001-exit-intent-toggle` | **Date**: 2026-03-10 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/001-exit-intent-toggle/spec.md`

## Summary

Add an exit-intent display trigger to Mail Mint popup forms. Users can enable a toggle in form settings to display their popup when visitors hover their cursor near the top of the browser for 500ms or more (sustained hover). The system detects cursor movement via JavaScript on desktop browsers only, tracks trigger state per page session, and displays only the first/primary form if multiple exit-intent popups exist on the same page. Frontend script loads conditionally (only when Form Type = "Popup" AND exit-intent is enabled) and uses WordPress transients for form setting caching.

## Technical Context

**Language/Version**: PHP 7.2+, JavaScript (ES6+), React/JSX  
**Primary Dependencies**: WordPress (core APIs), @wordpress/components (React UI), @wordpress/data (state management), WordPress transients (caching)  
**Storage**: WordPress postmeta (form settings: `enable_exit_intent` boolean), Browser sessionStorage (session state: `mm_exit_intent_fired_[formId]`)  
**Testing**: PHPUnit (PHP backend), Jest/Cypress (JavaScript frontend)  
**Target Platform**: WordPress plugin; frontend: desktop browsers (with graceful mobile degradation)  
**Project Type**: WordPress plugin (hybrid backend + frontend)  
**Performance Goals**: Script load <50ms, maintain 60 fps cursor tracking, single DB query per page load (via transients)  
**Constraints**: Desktop-only (mobile detection via user agent), 500ms sustained hover minimum, only first/primary form displays per session  
**Scale/Scope**: Per-form setting, per-page session tracking, all form types supported (exit-intent only visible for "Popup" type)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Compliance with Mail Mint Constitution v1.0.0

**✅ I. Spec-Driven Development**: 
- Specification complete at [spec.md](spec.md) with 3 prioritized user stories (P1-P3)
- All acceptance criteria documented
- Clarifications integrated (5 Q&A pairs)

**✅ II. Code Quality Standards**:
- Backend PHP code: PHPCS compliance via existing phpcs.xml
- Frontend React: ESLint via existing project config
- All new functions: DocBlocks (PHP) and JSDoc (JS) with parameters, return types, side effects
- Single-responsibility methods enforced

**✅ III. Test-First Development**:
- Tests deferred to implementation phase (not in planning)
- Coverage targets: 80% new code, 100% critical logic (session firing)
- Unit + integration tests required per spec

**✅ IV. Performance & Scalability**:
- Performance targets identified: <50ms script load, 60 fps cursor tracking, <1 query/page via transients
- Database optimization: Settings cached via transients, single query per page load
- Script loading conditional: only when form_position="popup" AND enable_exit_intent=true

**✅ V. UX Consistency & Accessibility**:
- Setting UI in FormBuilder sidebar follows existing toggle patterns
- WCAG 2.1 AA: ToggleControl component (existing, accessible), label associations, keyboard navigation
- UI matches Mail Mint design system (existing component library)

**✅ VI. WordPress Plugin Standards**:
- Settings stored via postmeta (existing Mail Mint pattern)
- No form submission required (no nonces needed for read operations)
- Settings retrieval: current_user_can() check implicit via form editor access control
- Mobile detection via user agent filtering (user-initiated, not tracking)

**Violations**: None identified

## Project Structure

### Documentation (this feature)

```text
specs/001-exit-intent-toggle/
├── spec.md                          # Feature specification (complete)
├── plan.md                          # This file
├── research.md                      # Phase 0 (research findings)
├── data-model.md                    # Phase 1 (entity structure)
├── quickstart.md                    # Phase 1 (implementation guide)
├── contracts/                       # Phase 1 (API contracts if applicable)
└── checklists/
    └── requirements.md              # Quality validation (passing)
```

### Source Code (repository root)

```text
# Backend (PHP)
app/
└── Internal/
    └── FormBuilder/
        ├── FormBuilderHook.php          # Register form settings (existing)
        └── src/
            ├── admin/
            │   └── ExitIntentSetting.php         # NEW: Form settings endpoint
            └── frontend/
                └── ExitIntentDetection.php       # NEW: Script loading controller

# Frontend (React/JavaScript)
app/Internal/FormBuilder/src/
├── components/
│   └── sidebar/
│       └── index.jsx                # MODIFY: Add exit-intent toggle UI
└── assets/
    └── exit-intent/
        └── exit-intent-detector.js  # NEW: Frontend detection script

# Tests (not in plan, deferred to implementation)
tests/
├── unit-test/
│   ├── php/
│   │   └── ExitIntentSettingTest.php        # NEW
│   └── javascript/
│       └── exitIntentDetector.test.js       # NEW
└── cypress/
    └── e2e/
        └── exit-intent.spec.js              # NEW
```

## Phase 0: Research & Discovery

### Research Questions

1. **Form Settings Structure**: How does Mail Mint currently persist form settings? What's the schema?
   - **Finding**: Form settings stored as JSON in form postmeta, managed via FormBuilderHook.php
   - **Location**: app/Internal/FormBuilder/templates/Storage.php (examples), sidebar/index.jsx (UI)
   - **Pattern**: Settings object with nested structure (form_layout, confirmation_type, extras, etc.)

2. **Form Position Detection**: How does the system identify "Popup" form types?
   - **Finding**: `form_position` field in settings with values: "popup", "flyins", "fixed-on-top", "fixed-on-bottom"
   - **Location**: sidebar/index.jsx (onChangeFormPosition handler)
   - **Pattern**: Conditional UI rendering based on formPosition value

3. **Frontend Script Loading**: How does Mail Mint conditionally load frontend scripts?
   - **Finding**: FrontendAssets class loads scripts via enqueue_script based on conditions
   - **Location**: app/Internal/Admin/FrontendAssets.php
   - **Pattern**: Hooks into wp_enqueue_scripts, checks form types/options before enqueueing

4. **Form Setting Metadata**: What's the key pattern for storing form-specific metadata?
   - **Finding**: Uses WordPress postmeta with post_id (form ID) and meta_key
   - **Location**: Database layer (not yet fully explored; defer to implementation)
   - **Pattern**: Follow existing form metadata patterns

5. **Transient Caching**: How should form settings be cached via transients?
   - **Finding**: WordPress transients cache data with expiration; invalidate on form update
   - **Pattern**: Suggested implementation: cache key = `mm_form_settings_[form_id]`, expire on form_save hook
   - **Status**: Implementation detail for Phase 1+

### Discoveries Summary

- Form settings are JSON-structured and stored in postmeta
- Form Builder sidebar (React) manages UI for settings
- Conditional rendering based on formPosition value is established pattern
- Frontend script loading controlled by FrontendAssets class
- Transients should be invalidated on form save via hooks

## Phase 1: Design & Contracts

### 1. Data Model

**Form Setting Entity**: `enable_exit_intent`

```yaml
Entity: FormExitIntentSetting
Location: Form postmeta (post_type='mrm_form')
MetaKey: 'enable_exit_intent'
Type: Boolean (0 or 1)
Default: 0 (disabled)
Relationship: One per Form, only applicable when form_position="popup"
Persistence: Via form settings update (existing mechanism)

Visibility Rules:
- Show in UI only when form_position="popup"
- Hide when form_position in ["flyins", "fixed-on-top", "fixed-on-bottom"]
```

**Session State Entity**: `mm_exit_intent_fired_*`

```yaml
Entity: ExitIntentSessionState
Location: Browser sessionStorage
Key Format: `mm_exit_intent_fired_[formId]` (e.g., mm_exit_intent_fired_42)
Type: Boolean (true/false)
Default: false (not yet fired)
Lifetime: Page session (cleared on page unload, new session on page load)
Scope: Per form ID (multiple forms tracked independently)

Tracking Logic:
- Set to true when exit-intent is triggered for this form ID
- Check before triggering: if already true, do not trigger
- Clear on page unload (automatic via sessionStorage)
```

**Frontend Script Configuration**: `exit_intent_config`

```yaml
Entity: ExitIntentScriptConfig
Location: Injected inline via wp_localize_script
Format: JavaScript object (window.MRM_ExitIntentConfig)
Properties:
  - enabled: boolean (exit-intent enabled for any form on page)
  - forms: Array[{ id: number, enable_exit_intent: boolean }]
  - hoverThreshold: number (10px - distance from top)
  - sustainedHoverDuration: number (500ms - hover time before trigger)
  - primaryFormId: number (first/lowest ID form with exit-intent enabled)

Conditional Loading:
- Only inject config if FormType="Popup" AND at least one form has exit_intent=true
- Do NOT load on mobile devices (detect via user agent: iOS, Android)
```

### 2. API Contracts

#### Backend Endpoint (Form Settings Update)

**Endpoint**: `POST /wp-json/mrmapi/v1/form/settings` (existing)

**Modifications**: 
- Accept `enable_exit_intent` (boolean) in request body
- Validate: only allow when `form_position="popup"`
- Store in postmeta via `update_post_meta($form_id, 'enable_exit_intent', $value)`
- Invalidate transient: `delete_transient("mm_form_settings_$form_id")`

**Sample Request**:
```json
{
  "form_id": 42,
  "settings": {
    "form_layout": { "form_position": "popup", ... },
    "enable_exit_intent": true
  }
}
```

**Sample Response**:
```json
{
  "success": true,
  "form_id": 42,
  "enable_exit_intent": true,
  "message": "Form settings updated"
}
```

#### Frontend Script - Window Object

**Window Variable**: `window.MRM_ExitIntentConfig`

```javascript
window.MRM_ExitIntentConfig = {
  enabled: true,  // exit-intent active on page
  forms: [
    { id: 42, enable_exit_intent: true },
    { id: 43, enable_exit_intent: false }
  ],
  hoverThreshold: 10,                    // pixels from top
  sustainedHoverDuration: 500,           // milliseconds
  primaryFormId: 42                      // first form to display
};
```

**Frontend Script - Display Trigger**

When exit-intent fires:
```javascript
// Dispatch custom event that form display system can listen to
window.dispatchEvent(new CustomEvent('mrm-exit-intent-triggered', {
  detail: { formId: 42 }
}));
```

### 3. Component Structure

#### Backend PHP

**File**: `app/Internal/FormBuilder/src/admin/ExitIntentSetting.php`

```php
namespace Mint\MRM\Internal\FormBuilder\Admin;

class ExitIntentSetting {
    // Register setting field in form settings schema
    public function register_setting() { }
    
    // Validate setting value (boolean, only for popup forms)
    public function validate_setting($value, $form_id) { }
    
    // Get setting for a form (with transient caching)
    public static function get_setting($form_id) { }
    
    // Save setting and invalidate cache
    public function save_setting($form_id, $value) { }
}
```

**File**: `app/Internal/FormBuilder/src/frontend/ExitIntentDetection.php`

```php
namespace Mint\MRM\Internal\FormBuilder\Frontend;

class ExitIntentDetection {
    // Enqueue exit-intent detection script (conditional)
    public function enqueue_script() { }
    
    // Check if exit-intent should be active (form type + setting check)
    private function should_load() { }
    
    // Get forms with exit-intent enabled
    private function get_exit_intent_forms() { }
    
    // Localize script with config
    private function localize_config() { }
    
    // Detect mobile via user agent
    private static function is_mobile() { }
}
```

#### Frontend JavaScript

**File**: `app/Internal/FormBuilder/src/assets/exit-intent/exit-intent-detector.js`

```javascript
/**
 * Exit Intent Detection Script
 * 
 * Monitors cursor movement toward top of viewport
 * Triggers popup when sustained hover (500ms+) detected
 * Tracks session state via sessionStorage
 */

class ExitIntentDetector {
  constructor(config) {
    this.config = config;
    this.hovering = false;
    this.hoverStartTime = null;
    this.hoverTimeout = null;
    this.fired = new Map(); // Track which forms have fired
  }
  
  init() {
    // Load session state for forms
    // Attach mousemove listener
    // Attach mouseenter/mouseleave listeners
  }
  
  isHoveringNearTop(event) {
    // Return true if cursor within 10px of top
  }
  
  handleMouseMove(event) {
    // Update hover state based on cursor Y position
    // Start/cancel hover timer based on threshold
  }
  
  triggerExitIntent() {
    // Get primary form ID
    // Check if already fired in session
    // If not fired: dispatch event and mark as fired
  }
  
  hasExitIntentFired(formId) {
    // Check sessionStorage and internal Map
  }
  
  recordExitIntentFired(formId) {
    // Save to sessionStorage and internal Map
  }
}
```

#### React UI Component

**File**: `app/Internal/FormBuilder/src/components/sidebar/index.jsx` (MODIFY)

```jsx
// In Sidebar component render method, add after form_position selector:

{formPosition === 'popup' && (
  <div className="single-settings tooltip-on-switcher">
    <hr className="mrm-hr"/>
    <ToggleControl
      className="mrm-switcher-block"
      label={window?.MRM_Vars?.mint_trans?.ExitIntentDisplay || 'Exit-intent display'}
      checked={enableExitIntent}
      onChange={(state) => handleEnableExitIntent(state)}
      help={
        <span className="mintmrm-tooltip">
          <QuestionIcon/>
          <p>
            {window?.MRM_Vars?.mint_trans?.ExitIntentDisplayTooltip || 
             'Show the form immediately if the visitor attempts to leave the site.'}
          </p>
        </span>
      }
    />
  </div>
)}

// Add state hook and handler:
const [enableExitIntent, setEnableExitIntent] = useState(false);

const handleEnableExitIntent = (state) => {
  setEnableExitIntent(state);
  updateSettingData({
    ...settingData,
    settings: {
      ...settingData.settings,
      extras: {
        ...settingData.settings.extras,
        enable_exit_intent: state
      }
    }
  });
};
```

### 4. Quickstart Implementation Guide

#### Backend Setup (Phase 1)

1. Create `app/Internal/FormBuilder/src/admin/ExitIntentSetting.php`
   - Extend existing form settings pattern
   - Add to form settings schema registration

2. Create `app/Internal/FormBuilder/src/frontend/ExitIntentDetection.php`
   - Instantiate in app/App.php (init frontend)
   - Enqueue script conditionally

3. Register transients for caching
   - Cache key: `mm_form_settings_{form_id}`
   - Invalidate on form update hook

#### Frontend Setup (Phase 1)

1. Create `app/Internal/FormBuilder/src/assets/exit-intent/exit-intent-detector.js`
   - Implement cursor tracking (sustained hover)
   - Implement session state tracking
   - Dispatch event on trigger

2. Modify `app/Internal/FormBuilder/src/components/sidebar/index.jsx`
   - Add ToggleControl for exit-intent setting
   - Conditional visibility (only for popup form type)
   - Integrate with existing form settings save flow

3. Register i18n strings for UI text

#### Configuration & Testing

1. Add to `window.MRM_Vars` (translations)
   - ExitIntentDisplay (label)
   - ExitIntentDisplayTooltip (help text)

2. Verify script loads correctly
   - Only when form_position="popup" AND enable_exit_intent=true
   - Not on mobile devices
   - Within 50ms performance budget

## Complexity Tracking

| Aspect | Complexity | Mitigation |
|--------|-----------|-----------|
| Multiple Forms on Same Page | Medium | Display only primary form by ID; independent session tracking per form |
| Performance Impact | Low | Debounced cursor tracking; transient caching; conditional script loading |
| Mobile Device Detection | Low | User agent filtering (established pattern) |
| Browser Compatibility | Low | Graceful degradation if APIs unavailable; fallback to other display triggers |

## Next Steps

**Phase 0 Complete**: Research findings documented and integrated  
**Phase 1 Deliverables**:
- [ ] data-model.md (entity definitions + relationships)
- [ ] research.md (discovery findings)
- [ ] quickstart.md (implementation cookbook)
- [ ] contracts/ (API + integration contracts)

**Phase 2** (next command: `/speckit.tasks`):
- Generate task breakdown from this plan
- Map tasks to user stories (P1, P2, P3)
- Establish test-first task execution order

---

**Version**: Plan v1.0 | **Status**: Ready for Phase 1 design artifacts | **Next**: `/speckit.tasks` for implementation task generation
