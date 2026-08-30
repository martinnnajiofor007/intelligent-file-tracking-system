"use client";

import { useEffect, useState } from "react";
import { AppShell } from "@/components/AppShell";
import { ProtectedPage } from "@/components/ProtectedPage";
import { EmptyState, ErrorState, LoadingState } from "@/components/States";
import { useAuth } from "@/lib/auth";
import {
  getNotifications,
  markAllNotificationsAsRead,
  markNotificationAsRead,
  type Notification,
} from "@/lib/api";

export default function NotificationsPage() {
  return (
    <ProtectedPage>
      <NotificationsContent />
    </ProtectedPage>
  );
}

function NotificationsContent() {
  const { token } = useAuth();
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isMarkingAll, setIsMarkingAll] = useState(false);
  const [reloadKey, setReloadKey] = useState(0);

  useEffect(() => {
    if (!token) {
      return;
    }

    getNotifications(token, { per_page: 50 })
      .then((response) => {
        setNotifications(response.data);
        setError(null);
      })
      .catch((error) => {
        setError(error instanceof Error ? error.message : "Unable to load notifications");
      })
      .finally(() => setIsLoading(false));
  }, [token, reloadKey]);

  async function handleMarkRead(notification: Notification) {
    if (!token || notification.is_read) {
      return;
    }

    try {
      await markNotificationAsRead(token, notification.id);
      setNotifications((current) =>
        current.map((n) =>
          n.id === notification.id ? { ...n, is_read: true, read_at: new Date().toISOString() } : n,
        ),
      );
    } catch {
      // Ignore individual read failures; the list remains usable.
    }
  }

  async function handleMarkAllRead() {
    if (!token) {
      return;
    }

    setIsMarkingAll(true);

    try {
      await markAllNotificationsAsRead(token);
      setNotifications((current) =>
        current.map((n) => ({ ...n, is_read: true, read_at: new Date().toISOString() })),
      );
    } catch (error) {
      setError(error instanceof Error ? error.message : "Unable to mark notifications as read");
    } finally {
      setIsMarkingAll(false);
    }
  }

  const unreadCount = notifications.filter((n) => !n.is_read).length;

  return (
    <AppShell title="Notifications" subtitle="Your recent activity alerts">
      <div className="flex flex-col gap-4">
        <div className="flex items-center justify-between">
          <p className="text-sm text-slate-600">
            {unreadCount > 0
              ? `${unreadCount} unread notification${unreadCount === 1 ? "" : "s"}`
              : "You are all caught up"}
          </p>
          {unreadCount > 0 && (
            <button
              type="button"
              onClick={handleMarkAllRead}
              disabled={isMarkingAll}
              className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {isMarkingAll ? "Marking..." : "Mark all as read"}
            </button>
          )}
        </div>

        {error && (
          <ErrorState message={error} onRetry={() => setReloadKey((key) => key + 1)} />
        )}

        {isLoading && <LoadingState label="Loading notifications..." />}

        {!isLoading && notifications.length === 0 && (
          <EmptyState
            title="No notifications"
            description="You have no notifications yet."
          />
        )}

        {!isLoading && notifications.length > 0 && (
          <div className="flex flex-col gap-3">
            {notifications.map((notification) => (
              <button
                key={notification.id}
                type="button"
                onClick={() => handleMarkRead(notification)}
                className={`rounded-lg border bg-white p-4 text-left shadow-sm transition-colors hover:bg-slate-50 ${
                  notification.is_read ? "border-slate-200" : "border-slate-300"
                }`}
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="text-sm font-semibold text-slate-950">
                      {notification.title}
                    </p>
                    <p className="mt-1 text-sm leading-6 text-slate-600">
                      {notification.message}
                    </p>
                    <p className="mt-2 text-xs text-slate-400">
                      {new Date(notification.created_at).toLocaleString()}
                    </p>
                  </div>
                  {!notification.is_read && (
                    <span className="mt-1 inline-flex h-2.5 w-2.5 shrink-0 rounded-full bg-rose-600" />
                  )}
                </div>
              </button>
            ))}
          </div>
        )}
      </div>
    </AppShell>
  );
}
