# MySQL concurrency gate — 2026-07-12

- Tested commit: `a106fc9`
- Engine: cPanel MySQL/MariaDB, using a disposable database and database user.
- Production customer database: not touched.
- Workers: 2 independent PHP processes and 2 distinct MySQL connection IDs.
- Final-slot winners: 1
- Provider calls: 1
- User chat logs: 1
- Bot chat logs: 1
- Remaining quota reservations: 0
- Total test chat logs: 2
- Completed workers: 1

The first live-MySQL run exposed a strict-type mismatch because MySQL returned
`chatbots.user_id` as a numeric string. Commit `a106fc9` adds the integer
cast and a regression test. The gate then passed with the expected atomic
one-winner result.

Cleanup was verified after the passing run:

- Disposable test directory removed.
- Disposable database removed.
- Disposable database user removed.
