# Notifications

This document describes the notification system for the Intelligent File Tracking System.

## Data model

A `notifications` table stores application notifications. Each record identifies:

- `user_id` — the recipient (FK to `users`, cascade on delete).
- `type` — the notification type (see below).
- `title` — short human-readable title.
- `message` — longer human-readable message.
- `related_type` / `related_id` — the related entity (e.g. `App\Models\Transfer` and its id). These are **not** foreign-key constrained so a notification remains useful even if the related entity is later removed.
- `metadata` — optional JSON payload for the frontend (never contains credentials/secrets).
- `read_at` — timestamp when the recipient read it; `null` means unread.
- `created_at` / `updated_at`.

Indexes exist on `(user_id, read_at)` and `(related_type, related_id)`.

## Notification types

| Type | Recipient | Trigger |
|------|-----------|---------|
| `transfer_created` | intended recipient (`to_holder_user_id`) | a transfer is created |
| `transfer_acknowledged` | original requester (`requested_by_user_id`) | a transfer is acknowledged |
| `transfer_rejected` | original requester (`requested_by_user_id`) | a transfer is rejected |
| `transfer_overdue` | intended recipient (`to_holder_user_id`) | overdue detection command runs |
| `issue_reported` | active admin / supervisor / registry_staff (excluding the reporter) | an issue is reported |
| `issue_status_changed` | reporter (`reported_by_user_id`) | an issue status changes |

## Recipient rules

- Transfer notifications use server-controlled recipient IDs derived from the transfer record (`to_holder_user_id` / `requested_by_user_id`). Clients cannot choose recipients.
- Issue-reported notifications go to all active users with the `admin`, `supervisor`, or `registry_staff` role, excluding the reporter to avoid self-notification.
- Issue-status notifications go to the reporter.

## Overdue notification behavior

- Overdue is a derived condition (`status = pending` AND `due_at IS NOT NULL` AND `due_at < now()`).
- The `transfers:notify-overdue` command finds overdue pending transfers via `OverdueTransferService` and creates a `transfer_overdue` notification for each intended recipient.
- It **never** changes transfer status and **never** changes confirmed custody.
- It is idempotent: `NotificationService::notifyTransferOverdue()` skips creation if a `transfer_overdue` notification already exists for that recipient and transfer. Running the command repeatedly does not create duplicates.

## Duplicate-prevention strategy

- Overdue notifications are deduplicated by `(user_id, type, related_type, related_id)`.
- Transfer-created/acknowledged/rejected and issue notifications are one-shot events tied to a single business action, so they are naturally created once.

## Scheduler configuration

The scheduler is defined in `app/Console/Kernel.php`:

```php
$schedule->command('transfers:notify-overdue')->hourly();
```

- **Development:** run `php artisan schedule:work` to execute scheduled tasks continuously, or run `php artisan transfers:notify-overdue` manually.
- **Production:** add a single cron entry that runs Laravel's scheduler every minute:

  ```
  * * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
  ```

- Inspect registered tasks with `php artisan schedule:list`.

The scheduler only invokes the command; all business logic lives in `OverdueTransferService` and `NotificationService`.

## API endpoints

All endpoints require authentication (`auth:sanctum`).

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/notifications` | List the current user's notifications (newest first, paginated). Supports `unread=1` and `per_page` (max 100). |
| `GET` | `/api/notifications/{notification}` | Get a single notification owned by the current user. |
| `PATCH` | `/api/notifications/{notification}/read` | Mark a notification as read. |
| `POST` | `/api/notifications/read-all` | Mark all of the current user's notifications as read. |

## Security / ownership rules

- A user can only ever see, read, or mark **their own** notifications.
- Ownership is enforced server-side: if a requested notification's `user_id` does not match the authenticated user, the API returns `404` (avoiding existence disclosure).
- Recipient IDs and related-entity info are always server-controlled; clients cannot inject them.
- Notification payloads never contain passwords, tokens, secrets, or credentials.
