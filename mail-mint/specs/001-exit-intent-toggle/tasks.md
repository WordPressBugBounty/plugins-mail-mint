# Tasks: Exit Intent Display Toggle for Popup Forms

**Branch**: `001-exit-intent-toggle`  
**Input**: Design documents from `/specs/001-exit-intent-toggle/`  
**Prerequisite Docs**: 
- ✅ plan.md (Technical context, Constitution compliance)
- ✅ spec.md (3 user stories P1-P3, 11 functional requirements)
- ✅ data-model.md (4 entities, storage strategy)
- ✅ quickstart.md (4 implementation phases)
- ✅ research.md (10 discovery questions answered)

**Constitution**: Per Mail Mint Constitution v1.0.0, all code must follow Principles I-VI
- Principle III (Test-First): Tests written first, verified to fail, then implementation
- Principle II (Code Quality): PHPCS compliance, DocBlocks, single-responsibility
- Principle IV (Performance): 50ms script load, 60fps cursor tracking, transient caching

**Format**: `[ID] [P?] [Category] [Story] Description — file`
- **[P]**: Parallelizable (different files, no inter-task dependencies)
- **[Category]**: TEST (test-first), IMPL (implementation), PERF (performance)
- **[Story]**: US1 (P1), US2 (P2), US3 (P3)

---

## Phase 1: Setup (Infrastructure)

**Purpose**: Initialize testing framework and project structure (already exists, minimal setup)

- [x] T001 Create tests directory structure for exit-intent feature — `tests/unit-test/php/ExitIntentSettingTest.php` ✅ COMPLETE
- [x] T002 Create JavaScript test files for exit-intent detector — `tests/unit-test/javascript/exitIntentToggle.test.js` ✅ COMPLETE
- [x] T003 Create Cypress E2E test directory — `tests/cypress/e2e/exitIntent.spec.js` ✅ COMPLETE

---

## Phase 2: Foundational (Prerequisites)

**Purpose**: Blocking infrastructure (none required — can start user stories immediately)

**Note**: No foundational prerequisites identified. Each user story builds independently on existing Mail Mint infrastructure (React components, PHP hooks, WordPress APIs).

---

## Phase 3: User Story 1 — Configure Exit-Intent Display Setting (Priority: P1) 🎯 MVP

**Goal**: Enable users to toggle exit-intent setting in Form Builder sidebar; setting persists to database and hides/shows conditionally based on form type

**Independent Test**: Create Popup form → Find exit-intent toggle in settings panel (hidden for non-Popup types) → Toggle ON → Save → Reload form → Verify toggle is ON and persists

---

### Tests for User Story 1 (Test-First per Constitution III)

> **CRITICAL**: Write these tests FIRST, verify they FAIL, then implement code

- [x] T004 [P] TEST [US1] Unit test: ExitIntentSetting::get_setting() retrieves exit-intent flag from postmeta with transient caching — `tests/unit-test/php/ExitIntentSettingTest.php::testGetSetting` ✅ COMPLETE

- [x] T005 [P] TEST [US1] Unit test: ExitIntentSetting::validate_for_form() returns true only for popup forms, false for other types — `tests/unit-test/php/ExitIntentSettingTest.php::testValidateForForm` ✅ COMPLETE

- [x] T006 [P] TEST [US1] Unit test: Settings JSON serialization/deserialization correctly handles enable_exit_intent boolean in settings.extras — `tests/unit-test/php/ExitIntentSettingTest.php::testSettingsStructure` ✅ COMPLETE

- [x] T007 TEST [US1] Integration test: Form settings controller persists enable_exit_intent to postmeta when form is saved — `tests/unit-test/php/ExitIntentSettingTest.php::testSettingsPersistence` ✅ COMPLETE

- [x] T008 [P] TEST [US1] React component test: ToggleControl renders only when form_position='popup' — `tests/unit-test/javascript/exitIntentToggle.test.js::testToggleVisibility` ✅ COMPLETE

- [x] T009 [P] TEST [US1] React component test: Toggle state changes propagate to settingData and trigger save action — `tests/unit-test/javascript/exitIntentToggle.test.js::testToggleStateChange` ✅ COMPLETE

- [x] T010 [P] TEST [US1] React component test: Form reload restores toggle state from settingData — `tests/unit-test/javascript/exitIntentToggle.test.js::testStateReload` ✅ COMPLETE

---

### Implementation for User Story 1

- [x] T011 IMPL [US1] Create ExitIntentSetting PHP class with get_setting(), validate_for_form(), save_setting() methods — `app/Internal/FormBuilder/src/admin/ExitIntentSetting.php` ✅ COMPLETE

- [x] T012 IMPL [US1] Add DocBlocks with param/return types and side effects to ExitIntentSetting class — `app/Internal/FormBuilder/src/admin/ExitIntentSetting.php` ✅ COMPLETE (included in T011)

- [ ] T013 IMPL [US1] Register i18n strings for exit-intent UI labels ("Display on exit-intent", "Show form when visitor attempts to leave", toggle label) — `includes/Mrmi18n.php` or language files

- [x] T014 [P] IMPL [US1] Add React ToggleControl component to FormBuilder sidebar with conditional rendering `{formPosition === 'popup' && <ToggleControl ... />}` — `app/Internal/FormBuilder/src/components/exitIntentToggle.jsx` ✅ COMPLETE

- [ ] T015 [P] IMPL [US1] Wire up toggle state to settingData object (onChange handler updates settingData.settings.extras.enable_exit_intent) — `app/Internal/FormBuilder/src/components/sidebar/index.jsx` (requires integration)

- [ ] T016 [P] IMPL [US1] Add exit-intent toggle to form layout UI constants/defaults (ensure defaults to OFF/false) — `app/Internal/FormBuilder/src/components/sidebar/index.jsx` or constants file

- [ ] T017 IMPL [US1] Integrate ExitIntentSetting with form save endpoint to persist enable_exit_intent to postmeta — `app/API/FormBuilder/FormBuilder.php` or relevant controller

- [ ] T018 IMPL [US1] Add transient invalidation hook on form save to clear cached settings (invalidate mm_form_settings_{form_id} transient) — `app/Internal/FormBuilder/FormBuilderHook.php` or similar

- [x] T019 IMPL [US1] Ensure form settings retrieval method respects PHPCS standards and includes inline comments explaining transient caching strategy — `app/Internal/FormBuilder/src/admin/ExitIntentSetting.php` ✅ COMPLETE

**Checkpoint**: User Story 1 complete — Users can enable/disable exit-intent setting in Popup forms, setting persists and reloads correctly

---

## Phase 4: User Story 2 — Exit-Intent Detection and Popup Trigger (Priority: P2)

**Goal**: Implement frontend cursor detection; trigger popup once per session when cursor hovers near top of browser for 500ms+

**Independent Test**: Publish Popup form with exit-intent enabled → Visit page in desktop browser → Move cursor to top edge and sustain for 500ms+ → Verify popup displays exactly once → Reload page → Verify exit-intent fires again (new session)

---

### Tests for User Story 2 (Test-First per Constitution III)

- [ ] T020 [P] TEST [US2] Unit test: ExitIntentDetector detects cursor within 10px of top edge with mousemove event listener — `tests/unit-test/javascript/exitIntentDetector.test.js::testCursorDetection`

- [ ] T021 [P] TEST [US2] Unit test: ExitIntentDetector enforces 500ms sustained hover requirement (timer fires after exactly 500ms of hovering) — `tests/unit-test/javascript/exitIntentDetector.test.js::testSustainedHoverTimer`

- [ ] T022 [P] TEST [US2] Unit test: Session state tracking prevents duplicate firing in same page session (checks sessionStorage mm_exit_intent_fired_[formId]) — `tests/unit-test/javascript/exitIntentDetector.test.js::testSessionState`

- [ ] T023 [P] TEST [US2] Unit test: Custom event 'mrm-exit-intent-triggered' dispatches with correct formId detail when exit-intent fires — `tests/unit-test/javascript/exitIntentDetector.test.js::testCustomEventDispatch`

- [ ] T024 TEST [US2] Integration test: End-to-end cursor tracking → session check → event dispatch → popup display (Cypress) — `tests/cypress/e2e/exitIntent.spec.js::testExitIntentFlow` (depends on T020-T023)

- [ ] T025 TEST [US2] Integration test: Session reset on page reload (sessionStorage cleared, exit-intent can fire again on new page load) — `tests/cypress/e2e/exitIntent.spec.js::testSessionReset` (depends on T020-T023)

- [ ] T026 TEST [US2] Integration test: Multiple exit-intent forms on same page display only primary form (lowest form ID with exit-intent enabled) — `tests/cypress/e2e/exitIntent.spec.js::testPrimaryFormOnly` (depends on T020-T023)

---

### Implementation for User Story 2

- [ ] T027 [P] IMPL [US2] Create ExitIntentDetector JavaScript class with mousemove listener and 500ms sustained hover timer — `app/Internal/FormBuilder/src/assets/exit-intent/exit-intent-detector.js`

- [ ] T028 [P] IMPL [US2] Implement cursor position tracking (event.clientY to detect hover within 10px of top = y < 10) — `app/Internal/FormBuilder/src/assets/exit-intent/exit-intent-detector.js`

- [ ] T029 [P] IMPL [US2] Implement session state tracking via sessionStorage (key: mm_exit_intent_fired_[formId], value: boolean) — `app/Internal/FormBuilder/src/assets/exit-intent/exit-intent-detector.js`

- [ ] T030 IMPL [US2] Implement custom JavaScript event dispatch ('mrm-exit-intent-triggered' with detail.formId, detail.timestamp) when exit-intent fires — `app/Internal/FormBuilder/src/assets/exit-intent/exit-intent-detector.js`

- [ ] T031 IMPL [US2] Add event listener on form element to listen for 'mrm-exit-intent-triggered' and trigger popup display via existing Mail Mint display mechanism — `app/Internal/FormBuilder/src/components/Form.jsx` or similar

- [ ] T032 IMPL [US2] Add DocBlocks and JSDoc comments to ExitIntentDetector class explaining class purpose, method parameters, and event flow — `app/Internal/FormBuilder/src/assets/exit-intent/exit-intent-detector.js`

- [ ] T033 IMPL [US2] Implement form ID filtering to ensure only primary form displays (select lowest form ID where exit-intent enabled from window.MRM_ExitIntentConfig.forms) — `app/Internal/FormBuilder/src/assets/exit-intent/exit-intent-detector.js`

- [ ] T034 IMPL [US2] Add console logging and debugging helpers (log cursor position, timer state, session checks) for QA and troubleshooting — `app/Internal/FormBuilder/src/assets/exit-intent/exit-intent-detector.js`

**Checkpoint**: User Story 2 complete — Exit-intent detection works on desktop; popup displays once per session; cursor tracking maintains session state

---

## Phase 5: User Story 3 — Device and Performance Optimization (Priority: P3)

**Goal**: Load exit-intent script conditionally (only Popup forms with exit-intent enabled); exclude mobile devices; ensure <50ms script load and 60fps cursor tracking

**Independent Test**: Desktop form with exit-intent enabled→ Network tab: script loads in <50ms; Mobile form with same settings → Network tab: script does NOT load; Performance audit: cursor moves don't impact 60fps interaction performance

---

### Tests for User Story 3 (Test-First per Constitution III)

- [ ] T035 [P] TEST [US3] Unit test: Mobile device detection identifies iOS, Android, iPad, Tablet user agents and returns isDesktop=false — `tests/unit-test/php/ExitIntentDetectionTest.php::testMobileDetection`

- [ ] T036 [P] TEST [US3] Unit test: Desktop device detection identifies non-mobile user agents and returns isDesktop=true — `tests/unit-test/php/ExitIntentDetectionTest.php::testDesktopDetection`

- [ ] T037 [P] TEST [US3] Unit test: Script enqueuing only occurs when Form Type='popup' AND exit-intent enabled — `tests/unit-test/php/ExitIntentDetectionTest.php::testConditionalEnqueue`

- [ ] T038 [P] TEST [US3] Unit test: Script does NOT enqueue on mobile devices even if exit-intent enabled — `tests/unit-test/php/ExitIntentDetectionTest.php::testMobileExecution`

- [ ] T039 [P] TEST [US3] Unit test: Transient caching with mm_form_settings_{form_id} key and 1-hour TTL saves database queries — `tests/unit-test/php/ExitIntentDetectionTest.php::testTransientCaching`

- [ ] T040 TEST [US3] Integration test: Script load performance <50ms (Performance Observer measurement) — `tests/cypress/e2e/exitIntent.spec.js::testScriptLoadPerformance` (depends on T035-T039)

- [ ] T041 TEST [US3] Integration test: Cursor tracking maintains 60fps (no jank during mousemove events with debouncing/throttling) — `tests/cypress/e2e/exitIntent.spec.js::testCursorTrackingPerformance` (depends on T035-T039)

- [ ] T042 TEST [US3] Integration test: Transient invalidation clears cache when form is updated (postmeta change invalidates transient) — `tests/cypress/e2e/exitIntent.spec.js::testTransientInvalidation` (depends on T035-T039)

---

### Implementation for User Story 3

- [ ] T043 [P] IMPL [US3] Create ExitIntentDetection PHP controller class for conditional script enqueuing — `app/Internal/FormBuilder/src/frontend/ExitIntentDetection.php`

- [ ] T044 [P] IMPL [US3] Implement mobile device detection via user agent filtering (exclude iOS, Android, iPad, Tablet keywords) — `app/Internal/FormBuilder/src/frontend/ExitIntentDetection.php`

- [ ] T045 [P] IMPL [US3] Implement conditional script enqueuing: only when FormType='popup' AND exit-intent enabled AND isDesktop=true — `app/Internal/FormBuilder/src/frontend/ExitIntentDetection.php`

- [ ] T046 IMPL [US3] Register exit-intent script with wp_enqueue_script() with dependency on jQuery or vanilla JS as appropriate — `app/Internal/FormBuilder/src/frontend/ExitIntentDetection.php`

- [ ] T047 IMPL [US3] Inject form configuration via wp_localize_script() (window.MRM_ExitIntentConfig with enabled, forms, thresholds, primaryFormId, isDesktop) — `app/Internal/FormBuilder/src/frontend/ExitIntentDetection.php`

- [ ] T048 [P] IMPL [US3] Implement transient caching for form settings with mm_form_settings_{form_id} key, 1-hour TTL — `app/Internal/FormBuilder/src/frontend/ExitIntentDetection.php`

- [ ] T049 [P] IMPL [US3] Add transient invalidation on form update (hook into form_save_action to delete_transient mm_form_settings_{form_id}) — `app/Internal/FormBuilder/FormBuilderHook.php` or ExitIntentDetection.php

- [ ] T050 IMPL [US3] Implement script debouncing/throttling for mousemove events to maintain 60fps cursor tracking (use requestAnimationFrame or 16ms throttle) — `app/Internal/FormBuilder/src/assets/exit-intent/exit-intent-detector.js` (refine from T027-T034)

- [ ] T051 IMPL [US3] Add DocBlocks to ExitIntentDetection with transient caching rationale and mobile detection logic — `app/Internal/FormBuilder/src/frontend/ExitIntentDetection.php`

- [ ] T052 IMPL [US3] Add performance monitoring comments (script load target <50ms, cursor tracking target 60fps) — `app/Internal/FormBuilder/src/frontend/ExitIntentDetection.php`

**Checkpoint**: User Story 3 complete — Script loads conditionally and only on desktop; performance optimized with transients and cursor event debouncing

---

## Phase 6: Integration & Polish ✨

**Purpose**: Cross-feature validation, performance audit, code quality review

- [ ] T053 IMPL Accessibility audit: Verify exit-intent toggle meets WCAG 2.1 AA (4.5:1 contrast ratio, keyboard navigable, screen reader compatible) — _docs/exit-intent-a11y-audit.md_

- [ ] T054 TEST Integration test: End-to-end user journey with multiple forms, exit-intent enabled/disabled mix, multiple page sessions — `tests/cypress/e2e/exitIntent.spec.js::testComplexScenarios`

- [ ] T055 TEST Integration test: Form persistence across different edit sessions; exit-intent setting survives form updates and version changes — `tests/cypress/e2e/exitIntent.spec.js::testFormPersistence`

- [ ] T056 IMPL Code quality review: Verify all new PHP code passes PHPCS validation (`npm run phpcs tests/`) — No file changes; verification only

- [ ] T057 IMPL Code quality review: Verify all new JavaScript code passes ESLint validation per project config — No file changes; verification only

- [ ] T058 [P] IMPL Documentation: Update README.md or plugin docs with exit-intent feature description and user guide — `README.md` (Exit-Intent Feature section)

- [ ] T059 [P] IMPL i18n verification: Confirm all UI strings use gettext and are translatable, not hardcoded — `includes/Mrmi18n.php` and `app/Internal/FormBuilder/src/components/sidebar/index.jsx`

- [ ] T060 IMPL Performance audit: Measure page load impact with exit-intent enabled/disabled using Lighthouse or Performance Observer — _reports/performance-audit.md_

- [ ] T061 IMPL Documentation: Create troubleshooting guide for exit-intent not firing (common issues: script not loading on mobile, session already fired, cursor detection tolerance) — `docs/exit-intent-troubleshooting.md`

- [ ] T062 IMPL Security audit: Verify no XSS vulnerabilities in form ID injection, event detail passing, or localized data — No file changes; verification only

- [ ] T063 TEST Run full test suite with coverage report (target: 80% coverage for new code, 100% for session firing logic) — `npm run test:php` and `npm run test:jest`

- [ ] T064 IMPL Commit: All changes tested, documentation complete, ready for code review — Git commit message with Constitution compliance notes

**Checkpoint**: Feature complete, all acceptance criteria met, ready for QA and release

---

## Dependency Graph & Execution Strategy

### Dependency Order (Critical Path)

```
Phase 1: Setup (T001-T003) ✓ Prerequisite
    ↓
Phase 2: Foundational (none)
    ↓
Phase 3: US1 (T004-T019) — REQUIRED before US2, US3
    │   Tests T004-T010 (test-first)
    │   Implementation T011-T019 (depends on passing tests)
    │
    ├─→ Phase 4: US2 (T020-T034) — Can start after T019
    │   Tests T020-T026 (test-first)
    │   Implementation T027-T034 (depends on passing tests + US1)
    │
    └─→ Phase 5: US3 (T035-T052) — Can start after T019
        Tests T035-T042 (test-first)
        Implementation T043-T052 (depends on passing tests + US1, can run in parallel with US2)

        ↓
    Phase 6: Integration & Polish (T053-T064)
```

### Parallelization Opportunities

**Parallel Execution (within each phase**):

- **Phase 3**: T004-T006, T008-T009, T014-T016 can run in parallel (different components)
- **Phase 4**: T020-T023 can run in parallel (different detector behaviors), then T024-T026 (integration)
- **Phase 5**: T035-T039 can run in parallel (different validation scenarios), T040-T042 (performance), then T048-T049 (transients)
- **Phase 6**: T053, T055, T058-T062 can run in parallel (separate concerns)

**Sequential Within Phases**:

- Tests MUST pass before implementation (T004-T010 → T011-T019)
- Implementation within phase can be parallel for different components

### MVP Scope (Recommended)

**Minimum for User-Facing Feature (Phase 3 only)**:

- ✅ US1 (T004-T019): Users can enable exit-intent in form settings
- ✅ US2 frontend (T020-T034): Exit-intent detection works on desktop

**Recommended for Production (Phase 3 + 4 + 5 + core of Phase 6)**:

- ✅ US1: Settings complete
- ✅ US2: Detection complete
- ✅ US3: Mobile optimization complete
- ✓ Integration tests (T054-T055)
- ✓ Code quality (T056-T057)
- ✓ Performance audit (T060)

---

## Task Summary

| Phase | Count | Focus | Est. Time |
|-------|-------|-------|-----------|
| **Setup** | 3 | Test infrastructure | 0.5h |
| **US1 (P1)** | 16 | Settings UI + persistence | 6h |
| **US2 (P2)** | 15 | Detection + trigger | 8h |
| **US3 (P3)** | 18 | Performance + mobile | 6h |
| **Integration** | 12 | Testing + docs | 4h |
| **TOTAL** | **64 tasks** | **Complete feature** | **24.5h** |

---

## Constitution Compliance Checklist

All tasks aligned with Mail Mint Constitution v1.0.0:

- ✅ **I. Spec-Driven Development**: Specs locked, tasks derived from spec.md requirements
- ✅ **II. Code Quality Standards**: All IMPL tasks include DocBlocks, validation, error handling
- ✅ **III. Test-First Development**: TEST tasks (T004-T042) written before IMPL tasks
- ✅ **IV. Performance & Scalability**: PERF tasks include transient caching, cursor debouncing, <50ms load
- ✅ **V. UX Consistency & Accessibility**: T053 includes WCAG AA audit; T014 uses existing ToggleControl patterns
- ✅ **VI. WordPress Plugin Standards**: SECURITY considerations in T062; all hooks/filters documented

---

## Success Criteria per User Story

| Story | Criterion | Task(s) | Verified By |
|-------|-----------|---------|------------|
| **US1** | Toggle appears only for Popup forms | T014, T008 | T024-T026 integration |
| **US1** | Setting persists on form save | T017-T018, T007 | Form reload verification |
| **US2** | Exit-intent fires on sustained hover | T020-T021, T027-T029 | T024 integration |
| **US2** | Fires once per session | T022, T029 | T025 integration |
| **US3** | Script loads only on desktop | T038, T045 | T041 integration |
| **US3** | Performance <50ms + 60fps | T040-T041, T050 | T060 audit |

---

## Notes for Implementation

1. **Test-First Discipline**: Each test task (TEST) must be written to FAIL before corresponding IMPL task is coded
2. **Constitution Alignment**: All IMPL tasks include PHPCS compliance checks and DocBlocks
3. **Transient Strategy**: Invalidate `mm_form_settings_{form_id}` transient on any form update to prevent stale data
4. **Mobile Detection**: Use `stripos($user_agent, ['ios', 'android', 'ipad', 'tablet'])` pattern from research findings
5. **Event Binding**: Use `window.mrm = window.mrm || {}; window.mrm.events = ...` pattern for custom events (prevent naming conflicts)
6. **Performance Budget**: Monitor script load via Performance Observer; cursor tracking via DevTools 60fps monitor
