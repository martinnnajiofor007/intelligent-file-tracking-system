"use client";

import { useEffect, useMemo, useState } from "react";
import { AppShell } from "@/components/AppShell";
import { AppLink } from "@/components/AppLink";
import { ProtectedPage } from "@/components/ProtectedPage";
import { EmptyState, ErrorState, LoadingState } from "@/components/States";
import { StatusBadge } from "@/components/StatusBadge";
import { Pagination } from "@/components/Pagination";
import { ConfirmDialog } from "@/components/ConfirmDialog";
import { ReportIssueModal } from "@/components/ReportIssueModal";
import { useAuth } from "@/lib/auth";
import {
  getIssues,
  updateIssueStatus,
  type FileIssue,
} from "@/lib/api";

const PER_PAGE = 15;

const TRANSITIONS: Record<string, string[]> = {
  open: ["in_progress", "resolved", "dismissed"],
  in_progress: ["open", "resolved", "dismissed"],
  resolved: ["open"],
  dismissed: ["open"],
};

export default function IssuesPage() {
  return (
    <ProtectedPage>
      <IssuesContent />
    </ProtectedPage>
  );
}

function IssuesContent() {
  const { token, user } = useAuth();
  const [issues, setIssues] = useState<FileIssue[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [status, setStatus] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [reloadKey, setReloadKey] = useState(0);

  const [showReport, setShowReport] = useState(false);
  const [statusAction, setStatusAction] = useState<{
    issue: FileIssue;
    newStatus: string;
  } | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [isActing, setIsActing] = useState(false);

  const canManage = useMemo(
    () =>
      user?.role === "admin" ||
      user?.role === "registry_staff" ||
      user?.role === "supervisor",
    [user],
  );

  useEffect(() => {
    if (!token) {
      return;
    }

    getIssues(token, {
      status: status || undefined,
      search: search || undefined,
      per_page: PER_PAGE,
      page,
    })
      .then((response) => {
        setIssues(response.data);
        setMeta({
          current_page: response.meta.current_page,
          last_page: response.meta.last_page,
          total: response.meta.total,
        });
        setError(null);
      })
      .catch((error) => {
        setError(error instanceof Error ? error.message : "Unable to load issues");
      })
      .finally(() => setIsLoading(false));
  }, [token, status, search, page, reloadKey]);

  function resetToFirstPage() {
    setPage(1);
  }

  async function handleStatusChange() {
    if (!token || !statusAction) {
      return;
    }

    setIsActing(true);
    setActionError(null);

    try {
      await updateIssueStatus(token, String(statusAction.issue.id), statusAction.newStatus);
      setStatusAction(null);
      setReloadKey((key) => key + 1);
    } catch (error) {
      setActionError(error instanceof Error ? error.message : "Unable to update issue status");
    } finally {
      setIsActing(false);
    }
  }

  return (
    <AppShell title="Issues" subtitle="Manage file issues and their status">
      <div className="flex flex-col gap-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <input
              value={search}
              onChange={(event) => {
                setSearch(event.target.value);
                resetToFirstPage();
              }}
              placeholder="Search file number or title"
              className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-slate-500"
            />
            <select
              value={status}
              onChange={(event) => {
                setStatus(event.target.value);
                resetToFirstPage();
              }}
              className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-slate-500"
            >
              <option value="">All statuses</option>
              <option value="open">Open</option>
              <option value="in_progress">In progress</option>
              <option value="resolved">Resolved</option>
              <option value="dismissed">Dismissed</option>
            </select>
          </div>
          <button
            type="button"
            onClick={() => setShowReport(true)}
            className="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
          >
            Report issue
          </button>
        </div>

        {error && (
          <ErrorState message={error} onRetry={() => setReloadKey((key) => key + 1)} />
        )}

        {isLoading && <LoadingState label="Loading issues..." />}

        {!isLoading && issues.length === 0 && (
          <EmptyState
            title="No issues found"
            description="Try adjusting your filters or report a new issue."
          />
        )}

        {!isLoading && issues.length > 0 && (
          <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table className="w-full min-w-[820px] text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs uppercase text-slate-500">
                  <th className="py-3 pl-5 pr-4 font-medium">File</th>
                  <th className="py-3 pr-4 font-medium">Issue</th>
                  <th className="py-3 pr-4 font-medium">Reporter</th>
                  <th className="py-3 pr-4 font-medium">Created</th>
                  <th className="py-3 pr-4 font-medium">Status</th>
                  <th className="py-3 pr-5 font-medium">Actions</th>
                </tr>
              </thead>
              <tbody>
                {issues.map((issue) => (
                  <tr key={issue.id} className="border-b border-slate-100">
                    <td className="py-3 pl-5 pr-4">
                      {issue.file ? (
                        <AppLink href={`/files/${issue.file.id}`}>
                          {issue.file.file_number}
                        </AppLink>
                      ) : (
                        "Unknown"
                      )}
                    </td>
                    <td className="py-3 pr-4">
                      <div className="font-medium text-slate-950">
                        {issue.issue_type}
                      </div>
                      <div className="mt-0.5 max-w-xs truncate text-xs text-slate-500">
                        {issue.description}
                      </div>
                    </td>
                    <td className="py-3 pr-4 text-slate-700">
                      {issue.reported_by?.name ?? "Unknown"}
                    </td>
                    <td className="py-3 pr-4 text-slate-700">
                      {new Date(issue.created_at).toLocaleDateString()}
                    </td>
                    <td className="py-3 pr-4">
                      <StatusBadge status={issue.status} kind="issue" />
                    </td>
                    <td className="py-3 pr-5">
                      {canManage && (
                        <StatusMenu
                          issue={issue}
                          onSelect={(newStatus) =>
                            setStatusAction({ issue, newStatus })
                          }
                        />
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {!isLoading && issues.length > 0 && (
          <Pagination
            currentPage={meta.current_page}
            lastPage={meta.last_page}
            total={meta.total}
            onPageChange={setPage}
          />
        )}

        {showReport && (
          <ReportIssueModal
            token={token}
            onClose={() => setShowReport(false)}
            onCreated={() => {
              setShowReport(false);
              resetToFirstPage();
              setReloadKey((key) => key + 1);
            }}
          />
        )}

        <ConfirmDialog
          open={statusAction !== null}
          onClose={() => setStatusAction(null)}
          onConfirm={handleStatusChange}
          title="Update issue status"
          message={
            statusAction
              ? `Change this issue from "${statusAction.issue.status.replace(/_/g, " ")}" to "${statusAction.newStatus.replace(/_/g, " ")}"?`
              : ""
          }
          confirmLabel="Update status"
          isSubmitting={isActing}
        />
        {actionError && (
          <div className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {actionError}
          </div>
        )}
      </div>
    </AppShell>
  );
}

function StatusMenu({
  issue,
  onSelect,
}: {
  issue: FileIssue;
  onSelect: (newStatus: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const nextStatuses = TRANSITIONS[issue.status] ?? [];

  if (nextStatuses.length === 0) {
    return <span className="text-xs text-slate-400">No transitions</span>;
  }

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        className="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
      >
        Change status
      </button>
      {open && (
        <>
          <div
            className="fixed inset-0 z-10"
            onClick={() => setOpen(false)}
            aria-hidden="true"
          />
          <div className="absolute right-0 z-20 mt-1 w-44 rounded-md border border-slate-200 bg-white py-1 shadow-lg">
            {nextStatuses.map((nextStatus) => (
              <button
                key={nextStatus}
                type="button"
                onClick={() => {
                  setOpen(false);
                  onSelect(nextStatus);
                }}
                className="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
              >
                {nextStatus.replace(/_/g, " ")}
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
