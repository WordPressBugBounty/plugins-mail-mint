# Data Model: Exit Intent Display Feature

**Phase**: 1 (Design)  
**Date**: 2026-03-10  
**Feature**: Exit Intent Display Toggle for Popup Forms  

## Entity Definitions

### Core Entities

#### 1. Form Exit Intent Setting

**Purpose**: Store whether a popup form should trigger on exit-intent  
**Ownership**: One per Form (form with form_position="popup")

| Property | Type | Constraint | Default | Notes |
|----------|------|-----------|---------|-------|
| form_id | integer | Foreign key → posts(ID) | - | WordPress post ID of form |
| enable_exit_intent | boolean | Not null | false | Toggle: ON=true, OFF=false |
| created_at | timestamp | - | NOW() | When setting was first added |
| updated_at | timestamp | - | NOW() on update | When setting was last changed |

**Storage Location**: WordPress postmeta
- Table: `wp_postmeta`
- `post_id`: form ID
- `meta_key`: `_exit_intent_enabled` or stored in settings JSON
- `meta_value`: '0' or '1' (or embedded in settings JSON)

**Recommendation**: Store in existing settings JSON structure under `settings.extras.enable_exit_intent`

```json
{
  "settings": {
    "form_layout": { ... },
    "confirmation_type": { ... },
    "extras": {
      "enable_exit_intent": true,
      "cookies_timer": 7,
      "show_always": true
    }
  }
}
```

**Relationships**:
- **1:1** → Form (post_type="mrm_form")
  - A form has at most one exit-intent setting
  - Setting exists only when form_position="popup"

---

#### 2. Exit Intent Session State

**Purpose**: Track which forms have already triggered exit-intent in current browser session  
**Ownership**: Per page load, per form (independent tracking)

| Property | Type | Storage | Lifetime | Notes |
|----------|------|---------|----------|-------|
| session_id | string | sessionStorage | Page session | Key: `mm_exit_intent_fired_[formId]` |
| form_id | integer | Embedded in key | Page session | Extracted from key |
| fired | boolean | sessionStorage value | Page session | true=already fired, false=not yet |
| fired_timestamp | number (ms) | N/A (not stored) | N/A | When exit-intent fired (for analytics, future) |

**Storage Location**: Browser sessionStorage (client-side)
- Key Format: `mm_exit_intent_fired_[formId]` (e.g., `mm_exit_intent_fired_42`)
- Value: `true` or `false` (JSON boolean)
- Scope: Per tab/window (sessionStorage)
- Lifetime: Cleared on page unload, new session on page load

**Relationships**:
- **Many:1** → Form (per-page session tracking)
  - Multiple session states can exist for same form across different page loads
  - State is ephemeral (not persisted to database)

---

#### 3. Exit Intent Detection Configuration

**Purpose**: Provide frontend script with form configuration and trigger settings  
**Ownership**: Global per page (one config object containing all forms)

| Property | Type | Scope | Source | Notes |
|----------|------|-------|--------|-------|
| enabled | boolean | Page | Server-side calc | true if ANY form has exit_intent=true |
| forms | Array[FormConfig] | Page | Server-side query | All popup forms with exit_intent status |
| hoverThreshold | number (px) | Page | Hardcoded | 10px from top (spec constant) |
| sustainedHoverDuration | number (ms) | Page | Hardcoded | 500ms (spec constant) |
| primaryFormId | number | Page | Server-side calc | Lowest form ID with exit_intent=true |
| isDesktop | boolean | Page | Client-side detect | true if NOT mobile user agent |

**Where**: Injected into page via `wp_localize_script`
- Window object: `window.MRM_ExitIntentConfig`
- Loaded only when: FormType="popup" AND at least one form has exit_intent=true

**Example Object**:
```javascript
window.MRM_ExitIntentConfig = {
  enabled: true,
  forms: [
    { id: 42, enable_exit_intent: true },
    { id: 43, enable_exit_intent: false }
  ],
  hoverThreshold: 10,
  sustainedHoverDuration: 500,
  primaryFormId: 42,
  isDesktop: true // Detect before sending to frontend
};
```

**Relationships**:
- **1:Many** → Form
  - Each config contains multiple forms
  - References only forms with form_position="popup"

---

### Supporting Entities

#### 4. Exit Intent Trigger Event

**Purpose**: Signal that exit-intent detection has fired for a form  
**Type**: Custom JavaScript event (transient, not persisted)

| Property | Type | Notes |
|----------|------|-------|
| type | string | `'mrm-exit-intent-triggered'` |
| detail.formId | number | Form ID that should display |
| detail.timestamp | number | When triggered (ms since epoch) |

**Lifecycle**:
1. Exit-intent detection script detects sustained hover
2. Checks if form already fired in session (sessionStorage)
3. If not yet fired: dispatches custom event
4. Form display system listens and displays popup
5. Records firing in sessionStorage

---

## Data Model Diagram

```
┌─────────────────────────────────────────┐
│   WordPress Post (post_type=mrm_form)   │
│                                         │
│  ID, post_title, post_content, etc.    │
└────────────────┬────────────────────────┘
                 │
                 │ 1:1
                 │
                 ▼
┌─────────────────────────────────────────┐
│     postmeta (settings JSON)            │
│                                         │
│  {                                      │
│    "settings": {                        │
│      "form_layout": { ... },            │
│      "extras": {                        │
│        "enable_exit_intent": boolean    │◄──── Form Exit Intent Setting
│      }                                  │
│    }                                    │
│  }                                      │
└─────────────────────────────────────────┘
                 │
                 │
                 ▼
         ┌──────────────┐
         │   Frontend   │
         └──────────────┘
                 │
         ┌───────┴────────┐
         │                │
         ▼                ▼
    ┌──────────┐  ┌────────────────────────┐
    │sessionSt-│  │  window.MRM_ExitIntent-│
    │orage     │  │  Config (injected)     │
    │          │  │                        │
    │mm_exit_  │  │ {                      │
    │intent_   │  │   enabled: true,       │
    │fired_42  │  │   forms: [...],        │
    │= true    │  │   primaryFormId: 42    │
    └──────────┘  │ }                      │
                  └────────────────────────┘
                           │
                           ▼
                   ┌─────────────────┐
                   │ Exit Intent     │
                   │ Detection Script│
                   │ (listener active)
                   └─────────────────┘
```

---

## Storage Decisions

### Why WordPress postmeta for Form Setting?

✅ **Existing Pattern**: Mail Mint already stores form_layout, confirmation_type, etc. in postmeta  
✅ **Per-Form Scope**: Each form has independent setting (postmeta perfect for this)  
✅ **No New Tables**: Reuses existing infrastructure  
✅ **Automatic Cleanup**: When form deleted, postmeta cleaned up with post  
✅ **Transient Caching**: Can cache via `mm_form_settings_{form_id}` transient  

### Why Browser sessionStorage for Session State?

✅ **Per-Session Scope**: Data cleared on page unload, new session on reload  
✅ **No Database Load**: Client-side state, zero DB queries  
✅ **Per-Tab Isolation**: sessionStorage doesn't share across browser tabs  
✅ **Privacy**: No persistent cookies stored  
✅ **Performance**: Instant access from JavaScript  

### Why Window Object for Configuration?

✅ **Bootstrap Data**: Provided once per page load via `wp_localize_script`  
✅ **No DOM Queries**: Script doesn't need to query DOM for form elements  
✅ **Zero Latency**: Data available before exit-intent script initializes  
✅ **Type Safety**: Configuration validated server-side before sending  

---

## Caching Strategy

### Form Settings Caching (WordPress Transients)

**Purpose**: Reduce database queries for frequently accessed form settings

**Cache Key**: `mm_form_settings_{form_id}`
```php
$cache_key = 'mm_form_settings_' . $form_id;
$settings = get_transient($cache_key);
if (false === $settings) {
    $form_post = get_post($form_id);
    $settings = json_decode($form_post->post_content, true);
    set_transient($cache_key, $settings, HOUR_IN_SECONDS); // 3600 sec
}
```

**Invalidation**: On form update
```php
add_action('save_post_mrm_form', function($post_id) {
    delete_transient('mm_form_settings_' . $post_id);
});
```

**TTL**: 1 hour (forms unlikely to change frequently)

**Impact**: Reduces DB calls from N (for N forms) to 1 per page load if cache hits

---

## Validation Rules

### Form Setting Validation

| Rule | Constraint | Enforcement | Notes |
|------|-----------|-------------|-------|
| enable_exit_intent is boolean | Must be 0 or 1 | PHP validation before save | Prevents invalid values |
| Only valid for popup forms | form_position must == "popup" | Server-side check | Hide UI if not popup |
| One setting per form | Max 1 row per form_id | Implicit via postmeta | postmeta allows only one value per key |

### Session State Validation

| Rule | Constraint | Enforcement | Notes |
|------|-----------|-------------|-------|
| formId must be positive integer | form_id > 0 | JavaScript check in detector | Prevents NaN or negative IDs |
| fired must be boolean | true or false | JavaScript native | sessionStorage enforces type |
| Session state expires | Cleared on page unload | Browser native | Automatic sessionStorage behavior |

### Configuration Validation

| Rule | Constraint | Enforcement | Notes |
|------|-----------|-------------|-------|
| primaryFormId is valid | Must exist in forms array | Server-side calculation | Pass only valid form ID to frontend |
| hoverThreshold is 10px | Hardcoded constant | No validation needed | Spec requirement (not configurable) |
| sustainedHoverDuration is 500ms | Hardcoded constant | No validation needed | Spec requirement (not configurable) |
| enabled implies forms array has exits | If enabled=true, at least one form has exit_intent=true | Server-side logic | Ensures consistency |

---

## Migration & Backward Compatibility

### New Feature (No Migration Needed)

- No existing exit-intent data to migrate
- Setting added to existing form settings object
- Defaults to `false` (disabled) for all forms
- Fully backward compatible (opt-in feature)

### Future Considerations

- **v2.0**: If exit-intent becomes configurable (custom hover threshold), would need:
  - New postmeta fields: `_exit_intent_hover_threshold`, `_exit_intent_hover_duration`
  - Or nested structure in settings JSON
  - No breaking changes expected

---

## Testing Implications

### Unit Tests

- ✓ Form setting storage (enable/disable, persistence)
- ✓ Session state management (fire/don't fire, session reset)
- ✓ Configuration object generation (correct forms, primaryFormId calc)
- ✓ Mobile detection (user agent filtering)

### Integration Tests

- ✓ End-to-end: Set form exit-intent ON → Publish → Load page → Check config
- ✓ Multiple forms: Two popup forms with exit-intent → Verify primary form ID
- ✓ Cache invalidation: Update form → Verify transient cleared
- ✓ Mobile handling: Load page with mobile user agent → Verify script not loaded

---

**Document Version**: 1.0  
**Last Updated**: 2026-03-10  
**Status**: Ready for Implementation
