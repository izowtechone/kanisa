# SwalaSMS and login OTP deployment

This application uses SwalaSMS for SMS delivery and requires a one-time SMS code after password authentication for every active user except the exact `Super Admin` role.

## Before deployment

1. Back up the database and application files.
2. Verify that SwalaSMS is enabled in **Settings → SMS** and that its API key is stored using the encrypted settings flow. Do not put an API key in this file or in a frontend asset.
3. Use **Verify sender IDs** in SMS settings to retrieve the provider's list with `sender-ids:read`. Choose an approved and usable sender and explicitly save the selection; the configured sender is never changed automatically. Manual entry remains available, but does not establish approval. A successful balance check does not verify sender approval, and approval for one route does not guarantee delivery to every country.
   The last successful list belongs to the saved API key and provider URL. If the latest check fails, the older list remains visible with a warning and cannot be used for a new selection until a check succeeds. SwalaSMS documents HTTP 401 for invalid or restricted keys and missing permissions; HTTP 403 can also indicate insufficient permission. Obtain a key with `sender-ids:read` or ask SwalaSMS if access is denied. An unavailable check does not itself block sending.
4. Ensure every ordinary account has a current phone number. A Super Admin must mark the number as verified on that user's account. Changing the number removes its verified state; set and verify the new number in a separate edit.
5. Configure a persistent database queue and cache. OTP dispatch intentionally fails closed when `QUEUE_CONNECTION=sync`, because synchronous delivery would block the login request.

## Environment

The defaults are in `.env.example`. The production environment should include:

```dotenv
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=180
CACHE_STORE=database
LOGIN_OTP_EXPIRATION_MINUTES=5
LOGIN_OTP_MAX_ATTEMPTS=5
LOGIN_OTP_RESEND_COOLDOWN_SECONDS=60
LOGIN_OTP_SEND_LIMIT_PER_HOUR=5
LOGIN_OTP_IP_SEND_LIMIT_PER_HOUR=20
LOGIN_ACCOUNT_FAILURES_PER_HOUR=10
LOGIN_IP_FAILURES_PER_HOUR=50
LOGIN_PROGRESSIVE_LOCKOUT_SECONDS=30
LOGIN_PROGRESSIVE_LOCKOUT_MAX_SECONDS=900
LOGIN_SECURITY_EVENTS_RETENTION_DAYS=180
```

`LOGIN_OTP_EXPIRATION_MINUTES` is bounded to 1–10 minutes. Login and OTP limits are configurable through environment configuration; after changing them, rebuild Laravel's config cache.

## Database deployment

Before applying migrations, independently verify the target connection and environment, take and test a backup, inspect the pending migration list, and obtain approval. Do not run a blanket migration command merely to repair SMS: other pending migrations may alter login behavior. Review the sender/encoding and SMS status-events migrations individually; their rollback methods remove data and should not be used as a routine recovery mechanism.

The sender-storage expansion migration widens `financial_sms_logs.sender_id` to 32 characters and `financial_settings.value` to long text so the encrypted sender list fits. Apply it only after the sender/encoding migration and before deploying this version; schema changes may lock tables. Its rollback is intentionally refused because narrowing either column could lose data. No production migration is run automatically.

The separate OTP migration adds `users.phone_verified_at`, hashed OTP challenge storage, and login security events. Existing ordinary users will need a Super Admin to verify their account phone before they can sign in. Plan it separately and test the login transition before enabling it.

## Queue priority

OTP, bulk campaign, and ordinary jobs use separate database queue names. A persistent worker should prioritize them in this order:

Before starting any worker, inspect existing pending jobs and SMS logs. Starting a worker can send previously queued messages, including test messages. Reconcile submissions with an unknown provider outcome before retrying them; the application does not automatically retry those submissions. Allow any in-flight OTP jobs from an earlier deployment to expire before switching sender endpoints.

Member financial confirmations and bulk messages require recorded SMS opt-in; manual messages are not associated with a member record and the sender must verify permission. Duplicate bulk destinations are skipped across the campaign and recorded on the campaign page; the earliest non-cancelled recorded recipient is normally selected, while a prior submission takes precedence to prevent a duplicate. Eligibility is rechecked before sending; if the selected recipient becomes ineligible, another duplicate is not automatically substituted. Stop existing workers before deploying this change so old and new duplicate-handling code do not run concurrently.

```sh
php artisan queue:work database --queue=otp,bulk,default --sleep=1 --tries=3 --timeout=120
```

The longest SMS job timeout is 120 seconds. Keep the queue connection's `retry_after` longer than the worker timeout; the database default is 180 seconds. For Redis or Beanstalkd, keep their `retry_after` above 150 seconds; for SQS, configure a visibility timeout longer than the worker timeout. OTP queue payloads contain an encrypted OTP value, not plaintext; the challenge table stores only a password hash. Failed delivery errors are sanitized before being persisted.

For DirectAdmin hosting without a permanent worker, add a cron entry that runs every minute. Replace the PHP binary and application paths with those configured by the host:

```sh
cd /home/ACCOUNT/domains/DOMAIN/public_html && /usr/local/bin/php artisan queue:work database --queue=otp,bulk,default --stop-when-empty --max-time=150 --tries=3 --timeout=120
```

Set the worker timeout below the database queue's `retry_after`. Ensure the hosting provider's process execution limits permit a 120-second job plus process startup and shutdown overhead. Queue processing is asynchronous but cron-based delivery may wait until the next minute.

## Scheduler

Add a second DirectAdmin cron entry to run every minute:

```sh
cd /home/ACCOUNT/domains/DOMAIN/public_html && /usr/local/bin/php artisan schedule:run
```

The scheduler refreshes up to five pending SwalaSMS statuses every five minutes and prunes expired OTP challenges hourly. Meaningful provider status changes are stored in the SMS history; unchanged poll responses are not duplicated.

## Verification

Run the focused automated tests before rollout:

```sh
php artisan test --compact tests/Feature/LoginOtpTest.php tests/Feature/Auth/AuthenticationTest.php tests/Feature/SwalaSmsTest.php
```

Tests fake queued SMS work and HTTP requests; they do not send real SMS. After deployment, perform a controlled test SMS to an authorized test recipient and verify the returned provider status. A provider `queued` or HTTP 202 response is not a delivery confirmation.

## Security and provider limitations

- Only a trusted `Super Admin` role bypasses SMS OTP. Administrators with other roles still require OTP.
- Password success does not authenticate a regular user. Protected routes require a server-side OTP-verified session marker; remembered sessions without that marker are logged out.
- Phone verification is an administrative attestation, not a self-service phone ownership challenge. Establish identity before marking an account's phone verified.
- The SwalaSMS base URL remains server-configured to avoid sending credentials to an administrator-entered arbitrary host.
- Single-recipient messages and OTP use `/v2/sms/send-bulk`; individual delivery status uses `/v1/sms/messages/{uid}`. An accepted response does not confirm handset delivery.
- Delivery refresh uses the documented message-status endpoint. Webhook delivery is not implemented; do not expose an unauthenticated webhook endpoint.
- SMS configuration remains encrypted and can be preserved by leaving credential fields blank. Sender IDs remain manually configured; verification checks the documented sender list without sending an SMS.
