"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { AppShell } from "@/components/AppShell";
import { ProtectedPage } from "@/components/ProtectedPage";
import { Card } from "@/components/Card";
import { ErrorState, LoadingState } from "@/components/States";
import { useAuth } from "@/lib/auth";
import { getDashboardStats, type DashboardStats } from "@/lib/api";

export default function DashboardPage() {
  return (
    <ProtectedPage>
      <DashboardContent />
    </ProtectedPage>
  );
}

function DashboardContent() {
  const { token, user } = useAuth();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    if (!token) {
      return;
    }

    getDashboardStats(token)
      .then(setStats)
      .catch((error) => {
        setError(error instanceof Error ? error.message : "Unable to load dashboard");
      })
      .finally(() => setIsLoading(false));
  }, [token]);

  return (
    <AppShell title="Dashboard" subtitle="Overview of the file tracking system">
      <div className="flex flex-col gap-6">
        <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
          <p className="text-sm text-slate-600">Welcome back,</p>
          <h2 className="mt-1 text-xl font-semibold text-slate-950">
            {user?.name ?? "User"}
          </h2>
          <p className="mt-1 text-sm text-slate-600">
            Here is a summary of the current state of tracked files and activity.
          </p>
        </div>

        {error && <ErrorState message={error} />}

        {isLoading && <LoadingState label="Loading dashboard..." />}

        {!isLoading && stats && (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <StatCard
              label="Total files"
              value={stats.totalFiles}
              href="/files"
            />
            <StatCard
              label="Overdue transfers"
              value={stats.overdueTransfers}
              href="/transfers"
              highlight={stats.overdueTransfers > 0}
            />
            <StatCard
              label="Unread notifications"
              value={stats.unreadNotifications}
              href="/notifications"
              highlight={stats.unreadNotifications > 0}
            />
          </div>
        )}

        {!isLoading && stats && (
          <Card>
            <h3 className="text-base font-semibold text-slate-950">
              Quick actions
            </h3>
            <div className="mt-4 flex flex-wrap gap-3">
              <Link
                href="/files"
                className="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
              >
                Browse files
              </Link>
              <Link
                href="/transfers"
                className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
              >
                View transfers
              </Link>
              <Link
                href="/issues"
                className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
              >
                View issues
              </Link>
              <Link
                href="/notifications"
                className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
              >
                Notifications
              </Link>
            </div>
          </Card>
        )}
      </div>
    </AppShell>
  );
}

function StatCard({
  label,
  value,
  href,
  highlight = false,
}: {
  label: string;
  value: number;
  href: string;
  highlight?: boolean;
}) {
  return (
    <Link
      href={href}
      className={`rounded-lg border bg-white p-5 shadow-sm transition-colors hover:bg-slate-50 ${
        highlight ? "border-rose-200" : "border-slate-200"
      }`}
    >
      <p className="text-sm font-medium text-slate-600">{label}</p>
      <p
        className={`mt-2 text-3xl font-semibold ${
          highlight ? "text-rose-600" : "text-slate-950"
        }`}
      >
        {value}
      </p>
    </Link>
  );
}
