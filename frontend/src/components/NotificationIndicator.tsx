"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { getNotifications } from "@/lib/api";

export function NotificationIndicator({ token }: { token: string }) {
  const [unreadCount, setUnreadCount] = useState<number | null>(null);

  useEffect(() => {
    let isMounted = true;

    getNotifications(token, { unread: true, per_page: 1 })
      .then((response) => {
        if (isMounted) {
          setUnreadCount(response.meta.total);
        }
      })
      .catch(() => {
        if (isMounted) {
          setUnreadCount(null);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [token]);

  return (
    <Link
      href="/notifications"
      className="relative inline-flex items-center rounded-md p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900"
      aria-label="Notifications"
      title="Notifications"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
        className="h-5 w-5"
        aria-hidden="true"
      >
        <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
        <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
      </svg>
      {unreadCount !== null && unreadCount > 0 && (
        <span className="absolute -right-0.5 -top-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-semibold text-white">
          {unreadCount > 99 ? "99+" : unreadCount}
        </span>
      )}
    </Link>
  );
}
