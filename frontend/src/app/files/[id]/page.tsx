"use client";

import { useParams } from "next/navigation";
import { useCallback, useEffect, useMemo, useState } from "react";
import { AppShell } from "@/components/AppShell";
import { AppLink } from "@/components/AppLink";
import { ProtectedPage } from "@/components/ProtectedPage";
import { StatusBadge } from "@/components/StatusBadge";
import { ErrorState, LoadingState } from "@/components/States";
import { ConfirmDialog } from "@/components/ConfirmDialog";
import { CreateTransferModal } from "@/components/CreateTransferModal";
import { ReportIssueModal } from "@/components/ReportIssueModal";
import { useAuth } from "@/lib/auth";
import {
  acknowledgeTransfer,
  getFile,
  getFileAuditLogs,
  getFileIssues,
  getTransfersForFile,
  rejectTransfer,
  updateIssueStatus,
  type AuditLog,
  type FileIssue,
  type PhysicalFile,
  type Transfer,
} from "@/lib/api";

const ISSUE_TRANSITIONS: Record<string, string[]> = {
  open: ["in_progress", "resolved", "dismissed"],
  in_progress: ["open", "resolved", "dismissed"],
  resolved: ["open"],
  dismissed: ["open"],
};

export default function FileDetailsPage() {
  return (
    <ProtectedPage>
      <FileDetailsContent />
    </ProtectedPage>
  );
}

function FileDetailsContent() {
  const params = useParams<{ id: string }>();
  const { token, user } = useAuth();
  const [file, setFile] = useState<PhysicalFile | null>(null);
  const [transfers, setTransfers] = useState<Transfer[]>([]);
  const [issues, setIssues] = useState<FileIssue[]>([]);
  const [auditLogs, setAuditLogs] = useState<AuditLog[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [reloadKey, setReloadKey] = useState(0);

  const [showCreateTransfer, setShowCreateTransfer] = useState(false);
  const [showReportIssue, setShowReportIssue] = useState(false);
  const [confirmTransfer, setConfirmTransfer] = useState<{
    type: "acknowledge" | "reject";
    transfer: Transfer;
  } | null>(null);
  const [confirmIssue, setConfirmIssue] = useState<{
    issue: FileIssue;
    newStatus: string;
  } | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [isActing, setIsActing] = useState(false);

  const canCreateTransfer = useMemo(
    () =>
      user?.role === "admin" ||
      user?.role === "registry_staff" ||
      user?.role === "supervisor",
    [user],
  );

  const canManageIssues = useMemo(
    () =>
      user?.role === "admin" ||
      user?.role === "registry_staff" ||
      user?.role === "supervisor",
    [user],
  );

  const canActOnTransfer = useCallback(
    (transfer: Transfer) =>
      user?.id === transfer.to_holder?.id ||
      user?.role === "admin" ||
      user?.role === "supervisor",
    [user],
  );

  useEffect(() => {
    if (!token) {
      return;
    }

    Promise.all([
      getFile(token, params.id),
      getTransfersForFile(token, params.id),
      getFileIssues(token, params.id, { per_page: 50 }),
      getFileAuditLogs(token, params.id),
    ])
      .then(([fileResponse, transferResponse, issueResponse, auditResponse]) => {
        setFile(fileResponse.data);
        setTransfers(transferResponse.data);
        setIssues(issueResponse.data);
        setAuditLogs(auditResponse.data);
        setError(null);
      })
      .catch((error) => {
        setError(error instanceof Error ? error.message : "Unable to load file");
      })
      .finally(() => setIsLoading(false));
  }, [params.id, token, reloadKey]);

  async function handleTransferAction() {
    if (!token || !confirmTransfer) {
      return;
    }

    setIsActing(true);
    setActionError(null);

    try {
      if (confirmTransfer.type === "acknowledge") {
        await acknowledgeTransfer(token, String(confirmTransfer.transfer.id));
      } else {
        await rejectTransfer(token, String(confirmTransfer.transfer.id));
      }
      setConfirmTransfer(null);
      setReloadKey((key) => key + 1);
    } catch (error) {
      setActionError(error instanceof Error ? error.message : "Unable to update transfer");
    } finally {
      setIsActing(false);
    }
  }

  async function handleIssueStatusChange() {
    if (!token || !confirmIssue) {
      return;
    }

    setIsActing(true);
    setActionError(null);

    try {
      await updateIssueStatus(token, String(confirmIssue.issue.id), confirmIssue.newStatus);
      setConfirmIssue(null);
      setReloadKey((key) => key + 1);
    } catch (error) {
      setActionError(error instanceof Error ? error.message : "Unable to update issue");
    } finally {
      setIsActing(false);
    }
  }

  return (
    <AppShell
      title={file?.file_number ?? "File details"}
      subtitle="Custody, transfers, issues and history"
    >
      <div className="flex flex-col gap-6">
        {isLoading && <LoadingState label="Loading file details..." />}

        {error && <ErrorState message={error} />}

        {file && (
          <>
            <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
              <div className="flex flex-col justify-between gap-4 border-b border-slate-100 pb-5 md:flex-row">
                <div>
                  <h2 className="text-xl font-semibold">{file.title}</h2>
                  <p className="mt-2 text-sm leading-6 text-slate-600">
                    {file.description ?? "No description provided."}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <StatusBadge status={file.status} kind="file" />
                </div>
              </div>

              <dl className="mt-6 grid gap-5 md:grid-cols-2">
                <Detail label="File number" value={file.file_number} />
                <Detail label="Category" value={file.category?.name ?? "Unassigned"} />
                <Detail
                  label="Confirmed department"
                  value={file.confirmed_department?.name ?? "Unassigned"}
                />
                <Detail
                  label="Confirmed holder"
                  value={file.confirmed_holder?.name ?? "Unassigned"}
                />
                <Detail
                  label="Registered by"
                  value={file.registered_by?.name ?? "Unknown"}
                />
                <Detail
                  label="Registered date"
                  value={new Date(file.registered_at).toLocaleString()}
                />
                <Detail
                  label="Created"
                  value={new Date(file.created_at).toLocaleString()}
                />
                <Detail
                  label="Last updated"
                  value={new Date(file.updated_at).toLocaleString()}
                />
              </dl>

              <div className="mt-6 flex flex-wrap gap-3 border-t border-slate-100 pt-5">
                {canCreateTransfer && (
                  <button
                    type="button"
                    onClick={() => setShowCreateTransfer(true)}
                    className="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                  >
                    Create transfer
                  </button>
                )}
                <button
                  type="button"
                  onClick={() => setShowReportIssue(true)}
                  className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                  Report issue
                </button>
              </div>
            </section>

            <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
              <h3 className="text-base font-semibold text-slate-950">
                Transfers
              </h3>
              {transfers.length === 0 ? (
                <p className="mt-3 text-sm text-slate-500">
                  No transfers have been created for this file.
                </p>
              ) : (
                <div className="mt-4 overflow-x-auto">
                  <table className="w-full min-w-[720px] text-left text-sm">
                    <thead>
                      <tr className="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <th className="py-3 pr-4 font-medium">From</th>
                        <th className="py-3 pr-4 font-medium">To</th>
                        <th className="py-3 pr-4 font-medium">Requested</th>
                        <th className="py-3 pr-4 font-medium">Due</th>
                        <th className="py-3 pr-4 font-medium">Status</th>
                        <th className="py-3 pr-4 font-medium">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      {transfers.map((transfer) => (
                        <tr key={transfer.id} className="border-b border-slate-100">
                          <td className="py-3 pr-4 text-slate-700">
                            {transfer.from_department?.name ?? "Unknown"}
                          </td>
                          <td className="py-3 pr-4 text-slate-700">
                            {transfer.to_department?.name ?? "Unknown"}
                          </td>
                          <td className="py-3 pr-4 text-slate-700">
                            {transfer.requested_at
                              ? new Date(transfer.requested_at).toLocaleDateString()
                              : "—"}
                          </td>
                          <td className="py-3 pr-4 text-slate-700">
                            {transfer.due_at ? (
                              <span
                                className={
                                  transfer.is_overdue
                                    ? "font-medium text-rose-600"
                                    : ""
                                }
                              >
                                {new Date(transfer.due_at).toLocaleDateString()}
                                {transfer.is_overdue && (
                                  <span className="ml-1 rounded bg-rose-100 px-1.5 py-0.5 text-xs font-semibold text-rose-700">
                                    overdue
                                  </span>
                                )}
                              </span>
                            ) : (
                              "—"
                            )}
                          </td>
                          <td className="py-3 pr-4">
                            <StatusBadge status={transfer.status} kind="transfer" />
                          </td>
                          <td className="py-3 pr-4">
                            {transfer.status === "pending" &&
                              canActOnTransfer(transfer) && (
                                <div className="flex gap-2">
                                  <button
                                    type="button"
                                    onClick={() =>
                                      setConfirmTransfer({
                                        type: "acknowledge",
                                        transfer,
                                      })
                                    }
                                    className="rounded-md border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100"
                                  >
                                    Acknowledge
                                  </button>
                                  <button
                                    type="button"
                                    onClick={() =>
                                      setConfirmTransfer({
                                        type: "reject",
                                        transfer,
                                      })
                                    }
                                    className="rounded-md border border-rose-300 bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100"
                                  >
                                    Reject
                                  </button>
                                </div>
                              )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>

            <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
              <h3 className="text-base font-semibold text-slate-950">Issues</h3>
              {issues.length === 0 ? (
                <p className="mt-3 text-sm text-slate-500">
                  No issues have been reported for this file.
                </p>
              ) : (
                <div className="mt-4 flex flex-col gap-3">
                  {issues.map((issue) => (
                    <div
                      key={issue.id}
                      className="rounded-md border border-slate-200 p-4"
                    >
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <p className="text-sm font-semibold text-slate-950">
                            {issue.issue_type}
                          </p>
                          <p className="mt-1 text-sm leading-6 text-slate-600">
                            {issue.description}
                          </p>
                          <p className="mt-2 text-xs text-slate-400">
                            Reported by {issue.reported_by?.name ?? "Unknown"} on{" "}
                            {new Date(issue.created_at).toLocaleDateString()}
                          </p>
                        </div>
                        <div className="flex items-center gap-2">
                          <StatusBadge status={issue.status} kind="issue" />
                          {canManageIssues && (
                            <IssueStatusMenu
                              issue={issue}
                              onSelect={(newStatus) =>
                                setConfirmIssue({ issue, newStatus })
                              }
                            />
                          )}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </section>

            <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
              <h3 className="text-base font-semibold text-slate-950">
                Audit history
              </h3>
              {auditLogs.length === 0 ? (
                <p className="mt-3 text-sm text-slate-500">
                  No audit records for this file.
                </p>
              ) : (
                <div className="mt-4 flex flex-col gap-2">
                  {auditLogs.map((log) => (
                    <div
                      key={log.id}
                      className="flex items-center justify-between gap-3 rounded-md border border-slate-100 px-3 py-2 text-sm"
                    >
                      <span className="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                        {log.action.replace(/_/g, " ")}
                      </span>
                      <span className="text-slate-500">
                        {log.actor?.name ?? "System"} ·{" "}
                        {new Date(log.created_at).toLocaleString()}
                      </span>
                    </div>
                  ))}
                </div>
              )}
            </section>
          </>
        )}

        <AppLink href="/files">← Back to files</AppLink>

        {showCreateTransfer && (
          <CreateTransferModal
            token={token}
            defaultFileId={params.id}
            onClose={() => setShowCreateTransfer(false)}
            onCreated={() => {
              setShowCreateTransfer(false);
              setReloadKey((key) => key + 1);
            }}
          />
        )}

        {showReportIssue && (
          <ReportIssueModal
            token={token}
            defaultFileId={params.id}
            onClose={() => setShowReportIssue(false)}
            onCreated={() => {
              setShowReportIssue(false);
              setReloadKey((key) => key + 1);
            }}
          />
        )}

        <ConfirmDialog
          open={confirmTransfer !== null}
          onClose={() => setConfirmTransfer(null)}
          onConfirm={handleTransferAction}
          title={
            confirmTransfer?.type === "acknowledge"
              ? "Acknowledge transfer"
              : "Reject transfer"
          }
          message={
            confirmTransfer?.type === "acknowledge"
              ? "Acknowledging this transfer will move confirmed custody of the file to the destination holder. This cannot be undone."
              : "Rejecting this transfer will decline the transfer. Confirmed custody of the file will remain unchanged."
          }
          confirmLabel={
            confirmTransfer?.type === "acknowledge" ? "Acknowledge" : "Reject"
          }
          tone={confirmTransfer?.type === "reject" ? "danger" : "default"}
          isSubmitting={isActing}
        />

        <ConfirmDialog
          open={confirmIssue !== null}
          onClose={() => setConfirmIssue(null)}
          onConfirm={handleIssueStatusChange}
          title="Update issue status"
          message={
            confirmIssue
              ? `Change this issue from "${confirmIssue.issue.status.replace(/_/g, " ")}" to "${confirmIssue.newStatus.replace(/_/g, " ")}"?`
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

function IssueStatusMenu({
  issue,
  onSelect,
}: {
  issue: FileIssue;
  onSelect: (newStatus: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const nextStatuses = ISSUE_TRANSITIONS[issue.status] ?? [];

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

function Detail({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-xs font-medium uppercase text-slate-500">{label}</dt>
      <dd className="mt-1 text-sm font-medium text-slate-900">{value}</dd>
    </div>
  );
}
