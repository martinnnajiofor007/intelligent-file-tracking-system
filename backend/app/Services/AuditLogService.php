<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    /**
     * Keys that must never be persisted in audit before/after payloads.
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'password_hash',
        'remember_token',
        'api_key',
        'api_token',
        'access_token',
        'refresh_token',
        'auth_token',
        'client_secret',
        'token',
        'secret',
        'authorization',
    ];

    /**
     * Substrings that mark a key as sensitive when present in the key name.
     */
    private const SENSITIVE_SUBSTRINGS = [
        'password',
        'token',
        'secret',
        'authorization',
    ];

    /**
     * Record an audit event. The actor may be a User, a user id, or null.
     *
     * @param  User|int|null  $actor
     */
    public function record($actor, string $action, string $entityType, int $entityId, $before = null, $after = null): AuditLog
    {
        return AuditLog::create([
            'actor_user_id' => $actor instanceof User ? $actor->id : $actor,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before' => $this->sanitize($before),
            'after' => $this->sanitize($after),
        ]);
    }

    /**
     * Normalize a model or array payload and strip sensitive keys.
     */
    private function sanitize($payload): ?array
    {
        if ($payload instanceof Model) {
            $payload = $payload->toArray();
        }

        if ($payload === null) {
            return null;
        }

        if (! is_array($payload)) {
            return (array) $payload;
        }

        return $this->stripSensitive($payload);
    }

    private function stripSensitive(array $data): array
    {
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if ($this->isSensitiveKey($normalizedKey)) {
                unset($data[$key]);
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->stripSensitive($value);
            }
        }

        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        if (in_array($key, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        foreach (self::SENSITIVE_SUBSTRINGS as $substring) {
            if (str_contains($key, $substring)) {
                return true;
            }
        }

        return false;
    }
}
