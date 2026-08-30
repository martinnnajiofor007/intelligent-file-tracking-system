"use client";

import { useEffect, useState } from "react";
import { AppShell } from "@/components/AppShell";
import { ProtectedPage } from "@/components/ProtectedPage";
import { Card } from "@/components/Card";
import { EmptyState, ErrorState, LoadingState } from "@/components/States";
import { useAuth } from "@/lib/auth";
import { getAuditLogs, type AuditLog } from "@/lib/api";

export default function AuditLogsPage() {
  return (
    <ProtectedPage>
      <AuditLogsContent />
    </ProtectedPage>
  );
}

function AuditLogsContent() {
  const { token } = useAuth();
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [reloadKey, setReloadKey] = useState(0);

  useEffect(() => {
    if (!token) {
      return;
    }

    getAuditLogs(token, {})
      .then((response) => {
        setLogs(response.data);
        setError(null);
      })
      .catch((error) => {
        setError(error instanceof Error ? error.message : "Unable to load audit logs");
      })
      .finally(() => setIsLoading(false));
  }, [token, reloadKey]);

  return (
    <AppShell title="Audit Logs" subtitle="Append-only record of system activity">
      <div className="flex flex-col gap-4">
        <Card>
          <h2 className="text-base font-semibold text-slate-950">
            Recent activity
          </h2>
          <p className="mt-1 text-sm text-slate-600">
            Newest events first. Audit records are read-only.
          </p>
        </Card>

        {error && (
          <ErrorState message={error} onRetry={() => setReloadKey((key) => key + 1)} />
        )}

        {isLoading && <LoadingState label="Loading audit logs..." />}

        {!isLoading && logs.length === 0 && (
          <EmptyState
            title="No audit records"
            description="No activity has been recorded yet."
          />
        )}

        {!isLoading && logs.length > 0 && (
          <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table className="w-full min-w-[760px] text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs uppercase text-slate-500">
                  <th className="py-3 pl-5 pr-4 font-medium">Action</th>
                  <th className="py-3 pr-4 font-medium">Entity</th>
                  <th className="py-3 pr-4 font-medium">Actor</th>
                  <th className="py-3 pr-4 font-medium">Date</th>
                </tr>
              </thead>
              <tbody>
                {logs.map((log) => (
                  <tr key={log.id} className="border-b border-slate-100">
                    <td className="py-3 pl-5 pr-4">
                      <span className="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">
                        {log.action.replace(/_/g, " ")}
                      </span>
                    </td>
                    <td className="py-3 pr-4 text-slate-700">
                      {log.entity_type.replace(/^App\\Models\\/, "")} #{log.entity_id}
                    </td>
                    <td className="py-3 pr-4 text-slate-700">
                      {log.actor?.name ?? "System"}
                    </td>
                    <td className="py-3 pr-4 text-slate-700">
                      {new Date(log.created_at).toLocaleString()}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </AppShell>
  );
}
