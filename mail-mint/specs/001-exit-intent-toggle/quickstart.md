# Quickstart Implementation Guide

**Phase**: 1 (Design)  
**Date**: 2026-03-10  
**Feature**: Exit Intent Display Toggle for Popup Forms  
**Audience**: Developers implementing this feature

## 5-Minute Overview

Exit-intent allows popup forms to display when visitors move their cursor near the top of the browser (exit-intent gesture). This guide provides step-by-step implementation checklist.

### What Gets Built

1. **Backend**: Form setting to enable/disable exit-intent
2. **Frontend UI**: Toggle in Form Builder sidebar (only for Popup forms)
3. **Frontend Script**: Cursor tracking + session state management
4. **Integration**: Script loads conditionally, triggers popup display

### User Journey

```
Admin: Opens form → Selects "Popup" as form type
     → Sees "exit-intent" toggle appear
     → Toggles ON → Saves form
     
Visitor: Visits page with form
       → Moves cursor toward top of browser
       → Cursor hovers near top for 500ms+
       → Popup appears once per session
       → Reloading page resets session
```

---

## Implementation Checklist

### Phase A: Backend Setup (Form Settings)

#### A1. Create Backend Settings Class

**File**: `app/Internal/FormBuilder/src/admin/ExitIntentSetting.php`

```php
<?php
/**
 * Exit Intent Setting Handler
 */

namespace Mint\MRM\Internal\FormBuilder\Admin;

class ExitIntentSetting {
    /**
     * Get exit-intent setting for a form
     */
    public static function get_setting($form_id) {
        $cache_key = 'mm_form_settings_' . intval($form_id);
        $settings = get_transient($cache_key);
        
        if (false === $settings) {
            $form_post = get_post($form_id);
            if (!$form_post) return false;
            
            $settings = json_decode($form_post->post_content, true);
            set_transient($cache_key, $settings, HOUR_IN_SECONDS);
        }
        
        return isset($settings['settings']['extras']['enable_exit_intent']) 
            ? (bool)$settings['settings']['extras']['enable_exit_intent'] 
            : false;
    }
    
    /**
     * Validate setting (only allow for popup forms)
     */
    public static function validate_for_form($form_id) {
        $form_post = get_post($form_id);
        if (!$form_post) return false;
        
        $settings = json_decode($form_post->post_content, true);
        $form_position = $settings['settings']['form_layout']['form_position'] ?? '';
        
        return $form_position === 'popup';
    }
}
```

**Checklist**:
- [ ] Create file and class
- [ ] Implement get_setting() with transient caching
- [ ] Implement validate_for_form() with position check
- [ ] Add DocBlocks with param/return types

#### A2. Register i18n Strings

**File**: `app/Internal/Admin/AdminAssets.php` or similar

Add to `wp_localize_script` when enqueuing admin scripts:

```php
wp_localize_script(
    'mrm-form-builder',
    'MRM_Vars',
    array(
        'mint_trans' => array(
            // ... existing translations ...
            'ExitIntentDisplay' => __('Exit-intent display', 'mrm'),
            'ExitIntentDisplayTooltip' => __('Show the form immediately if the visitor attempts to leave the site.', 'mrm'),
        ),
    )
);
```

**Checklist**:
- [ ] Add 2 i18n strings to admin assets
- [ ] Test string appears in Form Builder UI

### Phase B: Frontend UI (React Component)

#### B1. Modify Form Builder Sidebar

**File**: `app/Internal/FormBuilder/src/components/sidebar/index.jsx`

Find the section where form position is handled (around line 1677), add after the formPosition selector:

```jsx
// Add state
const [enableExitIntent, setEnableExitIntent] = useState(
  settingData?.settings?.extras?.enable_exit_intent ?? false
);

// Add handler
const handleEnableExitIntent = (state) => {
  setEnableExitIntent(state);
  setSettingData(prev => ({
    ...prev,
    settings: {
      ...prev.settings,
      extras: {
        ...prev.settings.extras,
        enable_exit_intent: state
      }
    }
  }));
};

// Add component in JSX (only render when form_position === 'popup')
{formPosition === 'popup' && (
  <div className="single-settings tooltip-on-switcher">
    <hr className="mrm-hr"/>
    <ToggleControl
      className="mrm-switcher-block"
      label={window?.MRM_Vars?.mint_trans?.ExitIntentDisplay || 'Exit-intent display'}
      checked={enableExitIntent}
      onChange={handleEnableExitIntent}
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
```

**Placement**: After the form position selector, before or with other popup-specific settings (time delay, button click)

**Checklist**:
- [ ] Add useState hook for enableExitIntent
- [ ] Add handleEnableExitIntent fn and integrate with setSettingData
- [ ] Add conditional JSX render  (only when formPosition === 'popup')
- [ ] Test UI appears/disappears when changing form type
- [ ] Test toggle saves and persists on reload

### Phase C: Frontend Script (Cursor Tracking)

#### C1. Create Exit Intent Detector Script

**File**: `app/Internal/FormBuilder/src/assets/exit-intent/exit-intent-detector.js`

```javascript
/**
 * Exit Intent Detector
 * Detects when visitor hovers cursor near top of browser
 * Triggers popup display when 500ms sustained hover detected
 */

class ExitIntentDetector {
  constructor(config) {
    if (!config || !config.enabled) {
      console.log('[ExitIntent] Disabled or no config');
      return;
    }
    
    this.config = config;
    this.hovering = false;
    this.hoverTimer = null;
    this.mouseY = 0;
    
    this.init();
  }
  
  init() {
    document.addEventListener('mousemove', (e) => this.handleMouseMove(e));
    document.addEventListener('mouseenter', (e) => this.handleMouseEnter(e));
    document.addEventListener('mouseleave', (e) => this.handleMouseLeave(e));
    
    console.log(`[ExitIntent] Initialized (primary form: ${this.config.primaryFormId})`);
  }
  
  handleMouseMove(event) {
    this.mouseY = event.clientY;
    
    // Check if near top (within threshold)
    if (this.mouseY <= this.config.hoverThreshold) {
      if (!this.hovering) {
        this.hovering = true;
        // Start timer for sustained hover
        this.hoverTimer = setTimeout(
          () => this.trigger(),
          this.config.sustainedHoverDuration
        );
      }
    } else {
      if (this.hovering) {
        this.hovering = false;
        if (this.hoverTimer) {
          clearTimeout(this.hoverTimer);
          this.hoverTimer = null;
        }
      }
    }
  }
  
  handleMouseEnter(event) {
    // Reset on mouse enter (entering viewport from outside)
    this.hovering = false;
    if (this.hoverTimer) clearTimeout(this.hoverTimer);
  }
  
  handleMouseLeave(event) {
    // Reset on mouse leave (cursor left viewport)
    this.hovering = false;
    if (this.hoverTimer) clearTimeout(this.hoverTimer);
  }
  
  trigger() {
    const formId = this.config.primaryFormId;
    
    // Check if already fired in session
    if (this.hasExitIntentFired(formId)) {
      console.log(`[ExitIntent] Form ${formId} already fired in session`);
      return;
    }
    
    // Record as fired
    this.recordExitIntentFired(formId);
    
    // Dispatch event for form display system to listen
    window.dispatchEvent(new CustomEvent('mrm-exit-intent-triggered', {
      detail: { formId: formId, timestamp: Date.now() }
    }));
    
    console.log(`[ExitIntent] Triggered for form ${formId}`);
    
    this.hovering = false;
  }
  
  hasExitIntentFired(formId) {
    const key = `mm_exit_intent_fired_${formId}`;
    return sessionStorage.getItem(key) === 'true';
  }
  
  recordExitIntentFired(formId) {
    const key = `mm_exit_intent_fired_${formId}`;
    sessionStorage.setItem(key, 'true');
  }
}

// Auto-initialize if config available
if (window.MRM_ExitIntentConfig) {
  window.MRM_ExitIntentDetector = new ExitIntentDetector(window.MRM_ExitIntentConfig);
}
```

**Checklist**:
- [ ] Create file
- [ ] Implement constructor and config validation
- [ ] Implement mousemove handler with threshold checking
- [ ] Implement sustained hover timer (500ms)
- [ ] Implement session state tracking (sessionStorage)
- [ ] Implement trigger dispatch event
- [ ] Auto-initialize on script load
- [ ] Add console logging for debugging

#### C2. Connect Detector to Form Display System

**Where**: The existing Mail Mint form display system needs to listen for `mrm-exit-intent-triggered` event

**Pattern**:
```javascript
window.addEventListener('mrm-exit-intent-triggered', function(e) {
  const formId = e.detail.formId;
  // Call existing form display function
  MRM.displayForm(formId);
});
```

**Note**: This assumes Mail Mint has existing form display API. Coordinate with team for exact hooks/functions.

**Checklist**:
- [ ] Identify existing form display trigger mechanism
- [ ] Add event listener in form display system
- [ ] Test event fires and form displays

### Phase D: Frontend Script Loading (Conditional Enqueue)

#### D1. Create Exit Intent Detection Controller

**File**: `app/Internal/FormBuilder/src/frontend/ExitIntentDetection.php`

```php
<?php
/**
 * Exit Intent Frontend Script Loading
 */

namespace Mint\MRM\Internal\FormBuilder\Frontend;

use Mint\Mrm\Internal\Traits\Singleton;

class ExitIntentDetection {
    use Singleton;
    
    public function init() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_script']);
    }
    
    public function enqueue_script() {
        // Only on frontend
        if (is_admin()) return;
        
        // Only on desktop (not mobile)
        if ($this->is_mobile()) return;
        
        // Check if any popup form has exit-intent enabled
        $config = $this->get_config();
        if (!$config['enabled']) return;
        
        // Enqueue script
        wp_enqueue_script(
            'mrm-exit-intent-detector',
            $this->get_script_url(),
            [],
            MRM_VERSION,
            true
        );
        
        // Localize config
        wp_localize_script(
            'mrm-exit-intent-detector',
            'MRM_ExitIntentConfig',
            $config
        );
    }
    
    private function get_config() {
        // Get all popup forms
        $args = array(
            'post_type' => 'mrm_form',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        $form_ids = get_posts($args);
        $exit_intent_forms = [];
        $primary_form_id = null;
        
        foreach ($form_ids as $form_id) {
            $enabled = \Mint\MRM\Internal\FormBuilder\Admin\ExitIntentSetting::get_setting($form_id);
            $exit_intent_forms[] = [
                'id' => (int)$form_id,
                'enable_exit_intent' => (bool)$enabled
            ];
            
            if ($enabled && ($primary_form_id === null || $form_id < $primary_form_id)) {
                $primary_form_id = $form_id;
            }
        }
        
        return [
            'enabled' => $primary_form_id !== null,
            'forms' => $exit_intent_forms,
            'hoverThreshold' => 10,
            'sustainedHoverDuration' => 500,
            'primaryFormId' => $primary_form_id ?? 0,
            'isDesktop' => !$this->is_mobile()
        ];
    }
    
    private function is_mobile() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return preg_match('/iphone|ipad|ipod|android/i', $user_agent);
    }
    
    private function get_script_url() {
        return plugin_dir_url(__FILE__) . 'assets/exit-intent/exit-intent-detector.js';
    }
}
```

#### D2. Register in App Initialization

**File**: `app/App.php`

In the `init()` method, add:

```php
if ( $this->is_request( 'frontend' ) ) {
    // ... existing code ...
    
    // Initialize exit-intent detection
    \Mint\MRM\Internal\FormBuilder\Frontend\ExitIntentDetection::get_instance()->init();
}
```

**Checklist**:
- [ ] Create ExitIntentDetection class
- [ ] Implement conditional enqueue logic
- [ ] Implement config generation
- [ ] Implement mobile detection
- [ ] Register in App.php
- [ ] Test script loads only on desktop + exit-intent enabled
- [ ] Test script does NOT load on mobile
- [ ] Test config contains correct form IDs

---

## Testing Checklist

### Manual Testing

**Admin**:
- [ ] Create Popup form
- [ ] See exit-intent toggle in sidebar
- [ ] Toggle ON → Save → Reload → Still ON
- [ ] Change form type to "Flyins" → Toggl disappears
- [ ] Change back to "Popup" → Toggle reappears

**Frontend (Desktop)**:
- [ ] Load page with exit-intent popup
- [ ] Open browser DevTools → Console
- [ ] Move cursor to top of browser (within 10px)
- [ ] Hold for 500ms → Popup should appear
- [ ] Move cursor away → Popup stays visible
- [ ] Try to trigger again → Popup does NOT appear (already fired)
- [ ] Reload page → Trigger exit-intent again → Popup appears (new session)

**Frontend (Mobile)**:
- [ ] Load page on mobile (or mobile user agent in DevTools)
- [ ] Check DevTools → Network tab → exit-intent script NOT loaded
- [ ] Check Console → No "[ExitIntent]" messages

### Automated TestingWill be specified in `/speckit.tasks`

---

## Debugging Tips

### Enable Detailed Logging

Edit `exit-intent-detector.js` and add to constructor:

```javascript
console.log('[ExitIntent] Config:', this.config);
console.log('[ExitIntent] Mobile detected:', !this.config.isDesktop);
```

### Check Session State

In browser Console:

```javascript
// See all fired forms in session
console.log(Object.entries(sessionStorage)
  .filter(([k,v]) => k.startsWith('mm_exit_intent_fired_')));
```

### Verify Script Loading

In browser DevTools Network tab, filter for `exit-intent-detector.js`

### Verify Form Display Event

Add temporary listener in browser Console:

```javascript
window.addEventListener('mrm-exit-intent-triggered', (e) => {
  console.log('Exit-intent event fired!', e.detail);
});
```

---

## Common Issues & Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| Popup doesn't appear | Form display listener not connected | Check form display system integration |
| Script loads on mobile | Mobile detection failing | Verify user agent detection logic |
| Triggers twice | Session state not saved | Check sessionStorage being set |
| Takes >500ms to trigger | Timer reset by mouse movement | Ensure mouseenter/mouseleave handlers |
| Works locally but not production | Cache issues | Clear transients: `wp_cache_flush()` |

---

## Performance Checklist

- [ ] Script loads within 50ms (check DevTools Performance tab)
- [ ] Cursor tracking maintains 60 fps (check DevTools with hardware acceleration)
- [ ] Form settings query cached via transients
- [ ] Config generated and injected in single page load
- [ ] No memory leaks after 10+ trigger attempts (check DevTools Memory tab)

---

## Next Steps

1. **Implement**: Follow checklist in order (A → B → C → D)
2. **Test**: Run manual test cases
3. **Review**: Code review against Constitution Principle II (quality, PHPCS, PSR-12)
4. **Generate Tasks**: Use `/speckit.tasks` for detailed task breakdown
5. **Deploy**: Merge to main branch after review

---

**Last Updated**: 2026-03-10  
**Version**: 1.0  
**Status**: Ready for Implementation
