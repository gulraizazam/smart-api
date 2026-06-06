# TESTING.md — the test-sync system (Laravel backend)

> One promise: **no stale tests, ever.** Whatever we finalise ships with a test —
> written if missing, updated if present. Same system as the SPA
> (`../frontend/TESTING.md`); this file covers the Laravel mechanics. The
> principles below are the senior-QA contract, not a checklist.

A test is an **executable specification of behaviour**. If the behaviour is real,
a test proves it; if the test can't fail when the behaviour breaks, it isn't a
test. Everything here keeps that true over time.

---

## 1. Definition of Done — what "finalised / approved" means

<!-- TESTING-SYNC:BEGIN -- shared cross-repo test contract: byte-identical in ../frontend/TESTING.md, enforced by scripts/check-guidelines-sync.sh. Edit BOTH. -->
A change is **not** done — not mergeable, not deployable — until *all* of these hold:

1. **Pinned by a test that can fail.** At least one test asserts the new/changed behaviour and would go red if it regressed.
2. **Red-first for bug fixes.** The reproducing test is written first and *observed to fail*, then the fix makes it pass.
3. **Suite green.** The repo's full test suite passes (commands listed under Commands below).
4. **Critical modules carry their test in the same change.** Touching the invariant map (pricing, permissions, money, lifecycle) has **no waiver**.
5. **Coexistence holds.** Any DB / permission / shared-endpoint change → the coexistence check **PASSES** (never break crm2).
6. **Changed lines are covered.** The diff-coverage gate passes; critical modules clear the mutation bar.
7. **No focus/rot left behind.** No focused (`.only` / `->only()`) tests committed; every skip has a reason + tracking; deleted code took its tests with it.
<!-- TESTING-SYNC:END -->

Backend specifics: the suite runs via `php -d memory_limit=-1 vendor/bin/pest`
(`php artisan test` OOMs at 128M — see `project_backend_test_runner_memory`). Item 5's
coexistence contract has a CI-runnable slice, `tests/Feature/Coexistence/Crm2ContractTest.php`,
that pins the destructive-break subset (shared columns still exist + legacy umbrellas still
declared) so it is gated on **every push**; the full HTTP/multi-app check stays local. Mutation
(item 6) uses Infection. Exact commands: §7.

---

## 2. The change→test map — what to write when

| You changed… | You owe… | Suite |
|---|---|---|
| Service / business logic (`app/Services/**`) | Unit or Feature test of behaviour + edges | `tests/Unit` or `tests/Feature` |
| Controller / endpoint | Feature test: happy path, validation (422), authorization (403), unauth (401) | `tests/Feature` |
| Model scope / accessor / cast with logic | Unit test | `tests/Unit/Models` |
| Observer / cron / lifecycle | Feature test of the full cascade | `tests/Feature` |
| **Pricing math** (`Bundles::calculatePrices`, `PlanDiscountService`, tax) | **Mandatory** regression test with exact numbers — **never weaken** | `tests/Feature` — **no waiver** |
| Permission / role / grant | Feature test **and** `coexistence-check.sh` PASS | `tests/Feature/Permissions` + script |
| Migration on a shared column/perm | `coexistence-check.sh` PASS; additive only (never drop/rename what crm2 reads) | script |
| FormRequest validation | Rule test | `tests/Unit/Requests` or Feature |
| Bug fix | **Failing test first** (§4) | nearest suite |

**Naming:** our tests are named by **behaviour**, not by class — `PlanService.php`
is covered by `CascadeDeleteGroupTest.php`, not `PlanServiceTest.php`. So the gates
don't demand a name-matched file; they demand that a logic change ships **with some
test change**, and **diff-coverage** verifies the changed *lines* are actually
exercised.

---

## 3. Test quality standards — why these tests won't go stale

- **Behaviour, not implementation.** Assert on responses, DB state, thrown
  exceptions, dispatched events — not private internals. Implementation-coupled
  tests rot on the first refactor.
- **AAA + one behaviour per test.** The name states the behaviour.
- **Deterministic.** Freeze time (`Carbon::setTestNow`), seed randomness, use
  `RefreshDatabase` + factories — never depend on existing rows or wall-clock.
- **Factories, not hand-built fixtures.** Reuse `Tests\Concerns\*`.
- **Cover the unhappy paths** — validation failures, 403/401, null/empty/zero/max,
  permission-denied — not just the golden path.
- **Test the contract, not the framework.**

---

## 4. Bug-fix protocol — red, green, refactor (non-negotiable)

1. Write the smallest test that fails *because of the bug*. Run it. **See red.**
2. Fix minimally → green.
3. Refactor with the test as a safety net.

The red step proves the test has teeth. For bugs, the test comes first — always.

---

## 5. Anti-staleness

- **Orphan / focus / skip sweep** — `composer test:stale`: fails on a stray Pest
  `->only()`; lists every `markTestSkipped` / `markTestIncomplete` so they can't
  pile up silently.
- **Missing-test gate** — `composer test:sync` (`scripts/test-sync-check.sh`):
  a logic change must come with a test change; honors `Test-exempt:` commit
  trailers. Mirrors the SPA's in-session gate for the committed diff.
- **Diff-coverage** — `composer test:diff-cover`: changed lines must be covered.
  Needs a coverage driver (pcov/xdebug).
- **Mutation testing** — `composer test:mutation` (Infection, `infection.json5`):
  deliberately breaks the critical modules and checks a test catches it. **A test
  that stays green against mutated code is stale.** Needs pcov/xdebug; CI installs
  it. **Ratchet the thresholds UP, never down.**

---

## 6. Waiver policy — block-by-default, waive-on-the-record

<!-- TESTING-SYNC:BEGIN -- shared cross-repo test contract: byte-identical in ../frontend/TESTING.md, enforced by scripts/check-guidelines-sync.sh. Edit BOTH. -->
A genuinely test-exempt change is waived **with a reason**, never by disabling the gate — via a commit trailer `Test-exempt: <path>` (auditable in history forever), or the in-session ack file where the repo has one.

**Valid:** pure rename/move, formatting, comments/docs, config with no logic, generated code, type-only declarations, dependency bump with no behaviour change.
**Invalid:** "no time", "will add later", "it's simple", or **anything touching a critical/invariant module** — those have **no waiver**.
<!-- TESTING-SYNC:END -->

This repo waives in CI/commits via the trailer, e.g. `Test-exempt: app/Models/Foo.php`.

---

## 7. Commands

```bash
composer test                    # the full suite (= php -d memory_limit=-1 vendor/bin/pest)
                                 # NB: a raw `php artisan test` OOMs at the default 128M — use composer test
composer test:stale              # stray ->only + skip/incomplete register
composer test:sync               # missing-test gate on the committed diff
composer test:coverage           # clover coverage (needs pcov/xdebug)
composer test:diff-cover         # changed lines must be covered (needs driver)
composer test:mutation           # Infection on critical modules (needs driver)
bash scripts/coexistence-check.sh   # crm2/crm3 shared-DB contract (MUST PASS)
bash scripts/parity-check.sh        # prod parity (signature diff + crm2 200)
```

Coverage driver locally: `pecl install pcov` (then enable it). CI uses
`setup-php`/`pecl` with `coverage: pcov` in the quality step.

---

## 8. When code and test disagree

A red test is a question, not always a code bug. Either the code is wrong (fix the
code) or the test encodes an unrealistic expectation (fix the test). **Don't
reflexively edit whichever is faster.** Weakening a test to get green is how suites
rot — if it's a genuine judgment call, decide it deliberately.
