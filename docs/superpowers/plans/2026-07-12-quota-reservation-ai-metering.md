# Message Quota Reservation and Tester AI Metering Plan

**Goal:** Reserve tenant message capacity before any external AI request, write chat pairs atomically, and limit owner/admin tester AI usage to 20 provider attempts per Kuala Lumpur day.

**Architecture:** A short-lived `message_quota_reservations` record represents a limited-plan slot while the provider runs outside database locks. A single service owns reserve, complete, release, and prune operations. Unlimited plans use the same atomic pair writer without a reservation. A separate daily usage row, serialized by a user lock, grants tester AI attempts only when the response service is about to invoke the provider.

## Task 1: Reservation schema and service

- [ ] Add additive, reversible reservation and tester-usage migrations with foreign keys, unique constraints, expiry/query indexes, and casts.
- [ ] Write service tests proving the final slot is reserved once, expired reservations do not block, unlimited plans do not persist reservations, and release is idempotent.
- [ ] Implement a typed permit plus transactional reserve/complete/release/prune behavior.

## Task 2: Public and developer chat integration

- [ ] Change RED integration tests so Cloudflare is never called before a reservation exists.
- [ ] Prove a rejected request makes zero provider calls and zero log writes.
- [ ] Prove finalization writes exactly one user/bot pair and removes its reservation.
- [ ] Prove an injected bot-log failure rolls back both logs and releases the reservation.
- [ ] Route widget and developer API chat through the shared quota service.

## Task 3: Tester AI daily metering

- [ ] Add RED tests for 20 AI attempts per user/day, a blocked 21st provider call, deterministic responses after the limit, and next-day reset in Asia/Kuala_Lumpur.
- [ ] Add an optional pre-provider grant callback to the response service and an `aiLimitReached` response signal.
- [ ] Return a safe Malay notice and display it through the existing top toast without writing chat logs or consuming monthly message quota.

## Task 4: Cleanup and verification

- [ ] Add a scheduled, overlap-safe expired-reservation prune command.
- [ ] Run focused feature/unit suites, migration up/down/up, Pint, syntax checks, and `git diff --check`.
- [ ] Run a separate two-connection MySQL concurrency proof before production deployment.
