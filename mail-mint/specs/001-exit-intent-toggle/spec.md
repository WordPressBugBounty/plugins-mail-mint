# Feature Specification: Exit Intent Display Toggle for Popup Forms

**Feature Branch**: `001-exit-intent-toggle`  
**Created**: 2026-03-10  
**Status**: Draft  
**Input**: User description: "Allow users to enable an exit-intent trigger for Mail Mint popup forms so the form appears when a visitor attempts to leave the site."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configure Exit-Intent Display Setting (Priority: P1)

As a Mail Mint user, I want to enable an exit-intent trigger in the form settings so that my popup form displays when visitors attempt to leave the site.

**Why this priority**: This is the core feature - without the ability to configure exit-intent, nothing else works. It's the MVP requirement directly addressing the user's problem of losing leads at page exit.

**Independent Test**: Can be fully tested by creating a Popup form, accessing the form settings panel, toggling the exit-intent setting ON/OFF, saving the form, and verifying the setting persists and the UI matches design standards.

**Acceptance Scenarios**:

1. **Given** the user is configuring a form with type "Popup", **When** they view the form settings panel, **Then** a "Display on exit-intent" setting block appears below the form type selection.
2. **Given** the exit-intent setting is visible, **When** the user views the setting block, **Then** it displays the title "Display on exit-intent", description "Show the form immediately if the visitor attempts to leave the site", a toggle switch labeled "Exit-intent display", and the toggle is OFF by default.
3. **Given** the user toggles the exit-intent switch ON, **When** they save the form, **Then** the setting value is persisted in the database and remains ON on reload.
4. **Given** the form type is changed from "Popup" to another type (e.g., "Inline"), **When** the form settings are reloaded, **Then** the exit-intent setting is hidden from the UI.
5. **Given** a form with exit-intent enabled, **When** the user views the form edit page, **Then** the toggle displays as ON.

---

### User Story 2 - Exit-Intent Detection and Popup Trigger (Priority: P2)

As a visitor to a website with an exit-intent popup form enabled, I want the form to appear when I attempt to leave the site so that I have a final opportunity to engage with the content or provide information.

**Why this priority**: This is the core frontend behavior - once the setting is configured, the exit-intent detection must work reliably. Without this, the feature is incomplete.

**Independent Test**: Can be fully tested by publishing a Popup form with exit-intent enabled, opening the page in a desktop browser, moving the cursor toward the top browser edge (exit intent), and verifying the popup displays exactly once per session and does not fire again until the page is reloaded.

**Acceptance Scenarios**:

1. **Given** a Popup form is published with exit-intent enabled, **When** a visitor's cursor hovers within 10px of the top edge of the browser viewport for 500ms or more, **Then** the popup form is displayed.
2. **Given** the popup has displayed due to exit-intent, **When** the visitor moves their cursor away from the top edge, **Then** the popup remains visible (does not dismiss automatically).
3. **Given** a visitor has triggered the exit-intent popup once, **When** they hover their cursor toward the top edge again in the same page session (even for 500ms+), **Then** the popup does NOT display (only triggers once per session).
4. **Given** a visitor closes the popup and then again moves the cursor toward the top edge, **When** they attempt to trigger exit-intent again, **Then** the popup does not display (session already triggered).
5. **Given** a visitor reloads the page, **When** they move the cursor toward the top edge, **Then** the popup displays again (new session).

---

### User Story 3 - Device and Performance Optimization (Priority: P3)

As a Mail Mint user, I want exit-intent detection to work efficiently and only on devices where it makes sense so that my site performance is not impacted and my visitors have a good experience on mobile.

**Why this priority**: Non-functional requirements that ensure the feature is production-ready. Without this, the feature could negatively impact mobile UX and site performance.

**Independent Test**: Can be fully tested by inspecting browser console for script load status, verifying the exit-intent script only loads when Form Type=Popup AND exit-intent toggle=ON, checking that the script does not load on mobile devices (iOS/Android user agents), and running performance tests to ensure no measurable impact to page load time or interaction performance.

**Acceptance Scenarios**:

1. **Given** a Popup form with exit-intent disabled, **When** the page loads, **Then** the exit-intent detection script does NOT load.
2. **Given** a Popup form with exit-intent enabled, **When** the page loads on a desktop browser, **Then** the exit-intent detection script loads and is ready to listen for cursor movement.
3. **Given** a Popup form with exit-intent enabled, **When** the page loads on a mobile device (iOS/Android user agent), **Then** the exit-intent detection script does NOT load and no exit-intent listeners are active.
4. **Given** a Popup form with exit-intent enabled, **When** the page is fully loaded, **Then** the script loading does not add more than 50ms to page load time (performance budget).
5. **Given** a visitor interacts with the page while exit-intent is listening, **When** they move the cursor, **Then** cursor event debouncing prevents measurable impact to page responsiveness (maintained 60 fps interaction performance tested via browser DevTools).

---

### Edge Cases

- What happens if a visitor uses a keyboard shortcut to close the hover state before the page unloads? (Popup should not fire; exit-intent only triggers at the browser/page boundary)
- How does the feature behave if the popup form itself is hidden by CSS or visibility rules? (Script should still detect exit intent and trigger, but if popup fails to render, a console warning should log the issue)
- What if a visitor has multiple exit-intent popups on the same page? (Each form with exit-intent enabled should track its own session state independently)
- How does the system handle rapid page reloads or navigation? (Session state should be cleared on page unload; new session created on page load)
- What user experience occurs if a visitor's browser does not support the required JavaScript APIs? (Graceful degradation - exit-intent script loads but does not crash; popup can still be triggered by other display rules if configured)

## Testing Strategy *(mandatory)*

*Per Mail Mint Constitution Principle III: Test-First Development*

### Test Categories Required

- **Unit Tests**: 
  - Exit-intent detection logic correctly identifies cursor within 10px of top edge after 500ms sustained hover
  - Popup trigger logic correctly checks: form type=Popup, exit-intent enabled, not already triggered in session per page load
  - Session state management correctly tracks which forms have fired exit-intent in current page session
  - Device detection correctly identifies desktop vs. mobile user agents (iOS, Android excluded)
  - Form settings retrieval correctly fetches the exit-intent toggle state from database and applies caching

- **Integration Tests**: 
  - End-to-end user flow: Create Popup form → Enable exit-intent → Publish → Visit page → Trigger exit-intent on desktop → Verify popup displays
  - Form settings persistence: Mobile device (user agent change) → Visit page with exit-intent enabled → Verify script does not load
  - Multiple form instance: Page with two Popup forms, both with exit-intent enabled → Trigger exit-intent → Both forms display independently with separate session tracking

- **Acceptance Tests**: Map each User Story's acceptance scenarios to specific test cases
  - US1 acceptance scenarios 1-5: Form settings UI, persistence, visibility rules → Test via form editor and database inspection
  - US2 acceptance scenarios 1-5: Cursor detection, single-trigger-per-session, reload behavior → Test via recorded mouse movements and session storage inspection
  - US3 acceptance scenarios 1-5: Script loading conditions, mobile detection, performance → Test via network tab and performance observer

### Coverage Requirements

- **New Code**: Minimum 80% coverage
  - Exit intent detection script: All branches (cursor near top, away, already fired, etc.) covered
  - Form settings update logic: Toggle ON/OFF, form type visibility, database persistence covered
  - Session state management: Script covered

- **Critical business logic**: 100% coverage required
  - Session firing logic must have 100% coverage to prevent accidental re-fires

### Performance & Scalability Testing

*Per Mail Mint Constitution Principle IV*

- **Page Load Impact**: Verify exit-intent script loads and initializes within 50ms budget
- **Runtime Memory**: Exit-intent listeners must not accumulate memory leaks on repeated page views
- **Cursor Tracking**: Cursor event listeners must use debouncing to maintain 60 fps interaction performance; event frequency per debounce strategy documented
- **Database**: Form settings retrieval MUST use WordPress transients for caching; single database query per page load maximum (transient expires/regenerates on form update via hook)

### Accessibility Testing

*Per Mail Mint Constitution Principle V*

- **Keyboard Navigation**: Exit-intent trigger is not keyboard-accessible (by design - exit-intent is mouse/trackpad only); if popup appears via exit-intent, popup itself must be fully keyboard navigable
- **Screen Reader**: Popup triggered by exit-intent should announce "Form has appeared" to screen readers; form transcript reads correctly
- **Color Contrast**: Setting UI toggle switch and label meet 4.5:1 contrast ratio

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST display an "Display on exit-intent" setting block in the form settings panel ONLY when Form Type = "Popup"
- **FR-002**: Setting block MUST include a title "Display on exit-intent", description "Show the form immediately if the visitor attempts to leave the site", and a toggle switch labeled "Exit-intent display"
- **FR-003**: Exit-intent toggle MUST default to OFF (disabled) when a new Popup form is created or the setting is first accessed
- **FR-004**: Toggle state MUST be persisted to the database when the form is saved; state MUST persist across page reloads and subsequent edits
- **FR-005**: System MUST detect exit-intent when visitor's cursor hovers within 10px of the top edge of the browser viewport for at least 500ms (sustained hover required on desktop devices)
- **FR-006**: System MUST trigger the popup form display when exit-intent is detected and the exit-intent toggle is enabled for that form; if multiple exit-intent forms are present on the same page, only the first/primary form (by form ID order) displays
- **FR-007**: Popup MUST trigger only once per page session; subsequent exit-intent cursor movements in the same session MUST NOT trigger the popup again
- **FR-008**: Session state MUST reset when the page is reloaded or navigated away from; a new page load MUST create a new session
- **FR-009**: Exit-intent detection script MUST only load when Form Type = "Popup" AND exit-intent toggle = ON; script MUST NOT load if either condition is false
- **FR-010**: Exit-intent detection script MUST NOT load on mobile devices (iOS, Android user agents); mobile visitors MUST NOT have exit-intent detection active
- **FR-011**: System MUST gracefully degrade if JavaScript is disabled or required APIs are unavailable; popup MUST still be displayable via other triggers

### Key Entities *(include if feature involves data)*

- **Form Setting - Exit Intent Toggle**: 
  - Attribute: `enable_exit_intent` (boolean, default: false)
  - Relationship: One per Form entity, stored in form metadata
  - Visibility: Only applicable when Form Type = "Popup"

- **Session State - Exit Intent Fired**:
  - Storage: Browser sessionStorage (key: `mm_exit_intent_fired_[formId]`)
  - Value: Boolean flag (true if exit-intent triggered, false if not yet triggered)
  - Lifetime: Cleared on page unload; new session on page load
  - Scope: Per form ID (multiple forms on same page tracked independently)

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of Popup forms with exit-intent enabled MUST display the setting toggle in the form editor within 2.5s page load (verified via UI screenshot comparison and timing audit)
- **SC-002**: Exit-intent popup MUST trigger when cursor sustains hover within 10px of top of viewport for 500ms on desktop; 95% of sustained hover gestures (500ms+) MUST result in popup display (validated via QA testing)
- **SC-003**: Exit-intent popup MUST fire exactly once per page session in 100% of test cases (verified via test automation: load page → trigger exit-intent → verify fires → trigger again → verify does NOT fire)
- **SC-004**: Page load time MUST NOT increase by more than 50ms when exit-intent script is active (measured with Performance Observer API; baseline vs. with-script comparison)
- **SC-005**: Exit-intent detection MUST be disabled on mobile devices 100% of the time; script MUST NOT load for iOS, Android, or tablet user agents (verified via network inspection and user agent testing)
- **SC-006**: Setting toggle state MUST persist with 100% accuracy across page reloads and form re-edits (verified via database inspection after save, and form reload verification)
- **SC-007**: Popup triggered by exit-intent MUST be fully keyboard navigable and screen-reader compatible (WCAG 2.1 AA compliance verified via accessibility audit tool)
- **SC-008**: Exit-intent feature availability across form instances MUST NOT cause memory leaks; repeated page loads with exit-intent enabled MUST maintain stable memory usage within 10MB variance (verified via heap snapshots)

## Clarifications

### Session 2026-03-10

- Q: How should the exit-intent setting be cached for performance? → A: Cache via WordPress transients (persistent cache) with single query per page load
- Q: Should rapid cursor movements be debounced/throttled? → A: Require sustained hover (500ms+) near top edge before triggering
- Q: How should multiple exit-intent forms display? → A: Display only the first/primary form (by form ID order)
- Q: What browser versions are required? → A: Follow Mail Mint's existing browser support policy
- Q: Should Testing Strategy reference specific function names? → A: Remove implementation details; describe behaviors in technology-agnostic terms

## Assumptions

- **Browser Support**: Exit-intent detection targets browsers supported by Mail Mint's existing compatibility policy (see main README for minimum versions); older browsers without `mousemove` and `mouseenter/mouseleave` events will not trigger exit-intent (graceful degradation acceptable)
- **Desktop vs. Mobile**: Desktop is defined as any device with a pointing device (mouse/trackpad) and `navigator.userAgent` excluding iOS, Android, iPad, or Tablet keywords
- **Session Definition**: A session is defined by the current page load; navigation to a different URL or page reload starts a new session. When multiple exit-intent forms exist on the same page, exit-intent triggers the primary/first form by form ID only.
- **Cursor Tracking**: Exit-intent requires sustained hover within 10px of top edge for 500ms to minimize false positives from quick cursor movements
- **Caching Strategy**: Form exit-intent settings are retrieved via WordPress transients on each page load (single database query max)
- **Popup Display**: This spec assumes Mail Mint already has a working popup form display mechanism; exit-intent is only the trigger, not the display implementation
- **Form Persistence**: Form settings are persisted via WordPress options/postmeta; exit-intent toggle follows the same pattern as existing form settings
- **No User Consent Required**: Exit-intent detection assumes visitor consent is handled separately (not part of this feature); exit-intent may be subject to GDPR/CCPA depending on tracking implementation

## Out of Scope

- Custom cursor detection thresholds (always 10px from top)
- Exit-intent animation or transition effects (use existing Mail Mint popup display animations)
- Analytics or event tracking for exit-intent fires (can be added in future feature)
- Integration with email service provider APIs (forms already have this capability)
- A/B testing variations of exit-intent triggers (future feature)
