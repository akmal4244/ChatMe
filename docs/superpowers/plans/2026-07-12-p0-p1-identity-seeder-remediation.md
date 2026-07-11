# P0/P1 Identity and Seeder Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make homepage and administrator provisioning collision-safe, explicitly adopt only the production-selected legacy chatbot, and reconcile official knowledge without deleting customer data.

**Architecture:** Nullable unique role markers identify trusted system records without exposing them through model mass assignment. Homepage provisioning runs in one transaction, creates or validates a dedicated non-admin owner, and may reassign an unmarked legacy chatbot only when its locked numeric ID is explicitly configured and its slug is exact. Official knowledge uses stable per-chatbot source keys so reruns update tagged rows while preserving API keys, logs, and untagged knowledge.

**Tech Stack:** Laravel 12, Eloquent/query builder, SQLite feature tests, PHPUnit 11, Laravel Pint.

## Global Constraints

- Do not modify `AuthController`, `User`, `Chatbot`, routes, layouts, `AppServiceProvider`, `.env.example`, operations/backup files, or production.
- Do not auto-adopt by chatbot name, owner e-mail, entitlement tuple, or administrator status.
- `CHATME_HOMEPAGE_LEGACY_CHATBOT_ID` is the only legacy-adoption selector; without it, an unmarked official slug is a hard collision.
- Preserve the selected legacy chatbot's API key, chat logs, and every unmarked knowledge item.
- The dedicated homepage owner is never an administrator.
- `AdminSeeder` never promotes, resets, or reuses an unmarked account.
- Follow RED -> GREEN for every production behavior and do not commit.

---

### Task 1: Schema markers and constraints

**Files:**
- Create: `database/migrations/2026_07_12_100000_add_system_identity_and_knowledge_source_keys.php`
- Modify: `tests/Feature/DatabaseIndexTest.php`

**Interfaces:**
- Produces nullable unique `users.system_role`, nullable unique `chatbots.system_role`, nullable `knowledge_items.source_key`, and unique `(chatbot_id, source_key)`.

- [ ] **Step 1: Write failing schema assertions** for all three columns and the composite unique index.
- [ ] **Step 2: Run** `php artisan test --do-not-cache-result tests/Feature/DatabaseIndexTest.php` **and verify RED** because the columns are absent.
- [ ] **Step 3: Add the additive migration** with explicitly named indexes and reversible drops.
- [ ] **Step 4: Rerun the focused test and verify GREEN.**

### Task 2: Homepage identity, explicit adoption, and non-destructive knowledge reconciliation

**Files:**
- Modify: `config/chatme.php`
- Modify: `database/seeders/HomepageChatbotSeeder.php`
- Modify: `tests/Feature/HomepageChatbotTest.php`

**Interfaces:**
- Consumes `config('chatme.homepage_chatbot.legacy_chatbot_id')` as a nullable positive integer.
- Produces `homepage_owner`, `homepage_chatbot`, and stable `homepage:001` through `homepage:033` markers.

- [ ] **Step 1: Write failing feature tests** proving: arbitrary admin/customer bot and knowledge remain byte-for-byte unchanged; an unmarked official slug fails without explicit ID; a preclaimed reserved owner fails closed; an explicit locked ID with exact slug is reassigned to a dedicated non-admin owner while API key/logs/unmarked knowledge survive; fresh install creates marked records; reruns keep IDs/password/API key and do not duplicate official rows.
- [ ] **Step 2: Run** `php artisan test --do-not-cache-result tests/Feature/HomepageChatbotTest.php` **and verify RED** from destructive/name-based behavior or missing columns.
- [ ] **Step 3: Implement minimal transactional provisioning:** validate dataset size, resolve/create the dedicated owner with `forceFill`, resolve the marked bot or explicitly selected locked legacy ID, reject every partial collision, provision the system entitlement, update only platform-owned bot settings, and never call broad knowledge deletion.
- [ ] **Step 4: Reconcile each official row by stable source key;** on explicit adoption only, tag an exact unmarked official-question match when unambiguous; preserve all other unmarked rows; remove only stale `homepage:*` tagged rows.
- [ ] **Step 5: Rerun the focused test and verify GREEN.**

### Task 3: Collision-safe administrator provisioning

**Files:**
- Modify: `database/seeders/AdminSeeder.php`
- Modify: `tests/Feature/AdminSeederSecurityTest.php`

**Interfaces:**
- Produces exactly one trusted `primary_admin` record; reruns update neither password nor identity.

- [ ] **Step 1: Write failing tests** proving an e-mail preclaim plus active session is not promoted or reset, a role/e-mail collision fails and rolls back, first provisioning creates the marker, and rerun preserves password and user ID.
- [ ] **Step 2: Run** `php artisan test --do-not-cache-result tests/Feature/AdminSeederSecurityTest.php` **and verify RED** because current `updateOrCreate` promotes a preclaim.
- [ ] **Step 3: Implement a transaction** that locks by `primary_admin` role and configured e-mail, rejects unmarked or mismatched collisions, creates only when both are absent, and leaves the existing password untouched on trusted reruns.
- [ ] **Step 4: Rerun the focused test and verify GREEN.**

### Task 4: Regression and formatting verification

**Files:** All files above only.

- [ ] **Step 1: Run focused identity/seeder/schema tests together** and record tests/assertions.
- [ ] **Step 2: Run adjacent subscription, authorization, and migration tests** to catch entitlement/tenant regressions.
- [ ] **Step 3: Run** `php vendor/bin/pint --test` **and fix only touched-file formatting if needed.**
- [ ] **Step 4: Run** `git diff --check` **and review the diff for prohibited files or destructive deletes.**
