"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { AppShell } from "@/components/AppShell";
import { AppLink } from "@/components/AppLink";
import { ProtectedPage } from "@/components/ProtectedPage";
import { EmptyState, ErrorState, LoadingState } from "@/components/States";
import { StatusBadge } from "@/components/StatusBadge";
import { Pagination } from "@/components/Pagination";
import { ConfirmDialog } from "@/components/ConfirmDialog";
import { CreateTransferModal } from "@/components/CreateTransferModal";
import { useAuth } from "@/lib/auth";
import {
  acknowledgeTransfer,
  getTransfers,
  rejectTransfer,
  type Transfer,
} from "@/lib/api";

const PER_PAGE = 15;

export default function TransfersPage() {
  return (
    <ProtectedPage>
      <TransfersContent />
    </ProtectedPage>
  );
}

function TransfersContent() {
  const { token, user } = useAuth();
  const [transfers, setTransfers] = useState<Transfer[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [status, setStatus] = useState("");
  const [overdue, setOverdue] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [reloadKey, setReloadKey] = useState(0);

  const [showCreate, setShowCreate] = useState(false);
  const [confirmAction, setConfirmAction] = useState<{
    type: "acknowledge" | "reject";
    transfer: Transfer;
  } | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [isActing, setIsActing] = useState(false);

  const canCreate = useMemo(
    () =>
      user?.role === "admin" ||
      user?.role === "registry_staff" ||
      user?.role === "supervisor",
    [user],
  );

  const canActOn = useCallback(
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

    getTransfers(token, {
      status: status || undefined,
      overdue: overdue === "1" ? true : overdue === "0" ? false : undefined,
      search: search || undefined,
      per_page: PER_PAGE,
      page,
    })
      .then((response) => {
        setTransfers(response.data);
        setMeta({
          current_page: response.meta.current_page,
          last_page: response.meta.last_page,
          total: response.meta.total,
        });
        setError(null);
      })
      .catch((error) => {
        setError(error instanceof Error ? error.message : "Unable to load transfers");
      })
      .finally(() => setIsLoading(false));
  }, [token, status, overdue, search, page, reloadKey]);

  function resetToFirstPage() {
    setPage(1);
  }

  async function handleAcknowledge() {
    if (!token || !confirmAction) {
      return;
    }

    setIsActing(true);
    setActionError(null);

    try {
      await acknowledgeTransfer(token, String(confirmAction.transfer.id));
      setConfirmAction(null);
      setReloadKey((key) => key + 1);
    } catch (error) {
      setActionError(error instanceof Error ? error.message : "Unable to acknowledge transfer");
    } finally {
      setIsActing(false);
    }
  }

  async function handleReject() {
    if (!token || !confirmAction) {
      return;
    }

    setIsActing(true);
    setActionError(null);

    try {
      await rejectTransfer(token, String(confirmAction.transfer.id));
      setConfirmAction(null);
      setReloadKey((key) => key + 1);
    } catch (error) {
      setActionError(error instanceof Error ? error.message : "Unable to reject transfer");
    } finally {
      setIsActing(false);
    }
  }

  return (
    <AppShell title="Transfers" subtitle="Manage file transfers and custody">
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
              <option value="pending">Pending</option>
              <option value="acknowledged">Acknowledged</option>
              <option value="rejected">Rejected</option>
            </select>
            <select
              value={overdue}
              onChange={(event) => {
                setOverdue(event.target.value);
                resetToFirstPage();
              }}
              className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-slate-500"
            >
              <option value="">All due states</option>
              <option value="1">Overdue</option>
              <option value="0">Not overdue</option>
            </select>
          </div>
          {canCreate && (
            <button
              type="button"
              onClick={() => setShowCreate(true)}
              className="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
            >
              Create transfer
            </button>
          )}
        </div>

        {error && (
          <ErrorState message={error} onRetry={() => setReloadKey((key) => key + 1)} />
        )}

        {isLoading && <LoadingState label="Loading transfers..." />}

        {!isLoading && transfers.length === 0 && (
          <EmptyState
            title="No transfers found"
            description="Try adjusting your filters or create a new transfer."
          />
        )}

        {!isLoading && transfers.length > 0 && (
          <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table className="w-full min-w-[900px] text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs uppercase text-slate-500">
                  <th className="py-3 pl-5 pr-4 font-medium">File</th>
                  <th className="py-3 pr-4 font-medium">From</th>
                  <th className="py-3 pr-4 font-medium">To</th>
                  <th className="py-3 pr-4 font-medium">Requested by</th>
                  <th className="py-3 pr-4 font-medium">Due</th>
                  <th className="py-3 pr-4 font-medium">Status</th>
                  <th className="py-3 pr-5 font-medium">Actions</th>
                </tr>
              </thead>
              <tbody>
                {transfers.map((transfer) => (
                  <tr key={transfer.id} className="border-b border-slate-100">
                    <td className="py-3 pl-5 pr-4">
                      <AppLink href={`/files/${transfer.file_id}`}>
                        File #{transfer.file_id}
                      </AppLink>
                    </td>
                    <td className="py-3 pr-4 text-slate-700">
                      <div>{transfer.from_department?.name ?? "Unknown"}</div>
                      <div className="text-xs text-slate-500">
                        {transfer.from_holder?.name ?? "Unknown"}
                      </div>
                    </td>
                    <td className="py-3 pr-4 text-slate-700">
                      <div>{transfer.to_department?.name ?? "Unknown"}</div>
                      <div className="text-xs text-slate-500">
                        {transfer.to_holder?.name ?? "Unknown"}
                      </div>
                    </td>
                    <td className="py-3 pr-4 text-slate-700">
                      {transfer.requested_by?.name ?? "Unknown"}
                    </td>
                    <td className="py-3 pr-4 text-slate-700">
                      {transfer.due_at ? (
                        <span
                          className={
                            transfer.is_overdue ? "font-medium text-rose-600" : ""
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
                    <td className="py-3 pr-5">
                      {transfer.status === "pending" && canActOn(transfer) && (
                        <div className="flex gap-2">
                          <button
                            type="button"
                            onClick={() =>
                              setConfirmAction({ type: "acknowledge", transfer })
                            }
                            className="rounded-md border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100"
                          >
                            Acknowledge
                          </button>
                          <button
                            type="button"
                            onClick={() =>
                              setConfirmAction({ type: "reject", transfer })
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

        {!isLoading && transfers.length > 0 && (
          <Pagination
            currentPage={meta.current_page}
            lastPage={meta.last_page}
            total={meta.total}
            onPageChange={setPage}
          />
        )}

        {showCreate && (
          <CreateTransferModal
            token={token}
            onClose={() => setShowCreate(false)}
            onCreated={() => {
              setShowCreate(false);
              resetToFirstPage();
              setReloadKey((key) => key + 1);
            }}
          />
        )}


        <ConfirmDialog
          open={confirmAction !== null}
          onClose={() => setConfirmAction(null)}
          onConfirm={confirmAction?.type === "acknowledge" ? handleAcknowledge : handleReject}
          title={
            confirmAction?.type === "acknowledge"
              ? "Acknowledge transfer"
              : "Reject transfer"
          }
          message={
            confirmAction?.type === "acknowledge"
              ? "Acknowledging this transfer will move confirmed custody of the file to the destination holder. This cannot be undone."
              : "Rejecting this transfer will decline the transfer. Confirmed custody of the file will remain unchanged."
          }
          confirmLabel={
            confirmAction?.type === "acknowledge" ? "Acknowledge" : "Reject"
          }
          tone={confirmAction?.type === "reject" ? "danger" : "default"}
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
