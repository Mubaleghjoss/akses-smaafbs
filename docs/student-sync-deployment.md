# Student sync deployment runbook

This runbook deploys the student-sync receiver on the central server first, then enables the local sender. It deliberately contains no production credentials or student data.

> **Safety warning:** the server working tree and `storage/` may be dirty. Do **not** use `git reset --hard`, `git clean`, or any destructive storage replacement. Review and preserve operator-managed changes before deployment.

## 1. Prepare and back up

1. Schedule a maintenance window and obtain approval from the application/database owner.
2. Record the deployed commit, `git status --short`, current migration status, and the current configuration cache state.
3. Create and verify an **encrypted** database backup before any migration. Store it outside the application tree with restricted access, and test that the backup can be listed/read by the approved recovery process.
4. Back up any relevant writable `storage/` content without overwriting existing server files. Keep the database backup location and encryption/recovery procedure with the change record.

## 2. Deploy the receiver first (central server)

Deploy the reviewed application release using the site's normal non-destructive release process. Preserve dirty worktree/storage content; do not hard-reset the server.

Apply only the two required migrations, one path at a time:

```bash
php artisan migrate --path=database/migrations/2026_08_20_120000_create_student_sync_tables.php --force
php artisan migrate --path=database/migrations/2026_08_20_130000_create_student_sync_scope_tokens_table.php --force
php artisan student-sync:install-defaults
```

Confirm both migration names show `Ran` with `php artisan migrate:status`, and confirm the designated full-admin roles received the student-push permission. Do not grant this permission broadly.

## 3. Configure the receiver secret

On the central server's protected environment configuration, set placeholders to real, unique values supplied through the approved secret manager:

```dotenv
STUDENT_SYNC_RECEIVER_ENABLED=false
STUDENT_SYNC_RECEIVER_CLIENT_ID=
STUDENT_SYNC_RECEIVER_SECRET=
```

Use a long randomly generated receiver secret and a dedicated client ID. Never place either value in Git, deployment logs, shell history, screenshots, fixtures, or support tickets. Restrict the environment file's owner and permissions according to the hosting account policy.

After the approved values are installed, rebuild and verify the server configuration cache:

```bash
php artisan config:clear
php artisan config:cache
php artisan route:list --path=api/internal/student-sync --no-ansi
```

Keep `STUDENT_SYNC_RECEIVER_ENABLED=false` while the credential values are first installed and checked. Keep the local client disabled at this stage. When the receiver is ready for the controlled fixture check, set `STUDENT_SYNC_RECEIVER_ENABLED=true`, then immediately rebuild the server configuration cache again:

```bash
php artisan config:clear
php artisan config:cache
```

Do not send the fixture request until that second cache rebuild has completed.

## 4. Receiver fixture check

From an approved controlled client or test environment, send a minimal signed **non-production fixture** request to preview. Verify authentication/signature enforcement, preview expiry, audit recording, and that preview creates no student mutation. Do not use a real student payload for this check.

Use the receiver's expected routes:

- `POST /api/internal/student-sync/preview`
- `POST /api/internal/student-sync/apply`

Do not run apply during this fixture check unless a separately approved non-production fixture is in use.

## 5. Configure and enable the local client

On the authorized local instance only, configure the central-server URL and the matching dedicated credential:

```dotenv
STUDENT_SYNC_CLIENT_ENABLED=false
STUDENT_SYNC_SERVER_URL=https://app.smaafbs.sch.id
STUDENT_SYNC_CLIENT_ID=
STUDENT_SYNC_SECRET=
STUDENT_SYNC_CLOCK_SKEW=300
STUDENT_SYNC_PREVIEW_TTL=900
STUDENT_SYNC_MAX_BATCH=250
STUDENT_SYNC_TIMEOUT=60
```

Use HTTPS for a production URL. Set a long random `STUDENT_SYNC_SECRET` matching the receiver secret through the approved secret channel, protect the local environment file, then run `php artisan config:clear && php artisan config:cache`. Only then set `STUDENT_SYNC_CLIENT_ENABLED=true` and rebuild the cache again.

## 6. Preview, approval, and apply

1. Use the authorized admin page to create a preview of the intended real batch.
2. Compare the safe preview counts/statuses with the approved local source scope. Resolve conflicts, not-found records, or unexpected changed fields before proceeding.
3. Obtain explicit human approval of that exact preview. A preview is not authorization to apply automatically.
4. Perform the apply using the approved UI/workflow and preserve the change/audit identifiers and counts.
5. Verify receiver audit counts, expected target records, and the absence of unintended updates. Check the local client did not expose secrets in logs.

## 7. Rollback and incident response

If verification identifies an incorrect application result, immediately disable `STUDENT_SYNC_CLIENT_ENABLED` locally and rebuild configuration cache. Preserve audit and encrypted change-backup records. Restore only through the approved, tested recovery procedure using the encrypted pre-deployment backup or the feature's encrypted per-apply backups, with database-owner approval. Do not use `git reset --hard`, do not delete dirty server storage, and do not make unreviewed bulk reversions.

Document the incident, preview/apply IDs, affected counts, configuration state, backup used, and verification result before re-enabling the client.

## Deployment completion checklist

- [ ] Encrypted server database backup verified and recovery owner identified.
- [ ] Dirty server worktree/storage preserved; no hard reset or destructive clean used.
- [ ] Receiver release deployed first.
- [ ] Both student-sync migrations applied individually and confirmed.
- [ ] Defaults installed and student-push permission checked.
- [ ] Receiver credentials stored securely; config cache rebuilt.
- [ ] Controlled fixture preview verified before enabling local client.
- [ ] Local client credentials stored securely; HTTPS URL and config cache verified.
- [ ] Real preview reviewed and manually approved before apply.
- [ ] Apply/audit counts verified and rollback material retained.
