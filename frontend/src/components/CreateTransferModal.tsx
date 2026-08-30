"use client";

import { useEffect, useMemo, useState } from "react";
import { Modal } from "@/components/Modal";
import {
  createTransfer,
  getDepartments,
  getFiles,
  getUsers,
  type Department,
  type PhysicalFile,
  type User,
} from "@/lib/api";

export function CreateTransferModal({
  token,
  onClose,
  onCreated,
  defaultFileId,
}: {
  token: string | null;
  onClose: () => void;
  onCreated: () => void;
  defaultFileId?: string;
}) {
  const [files, setFiles] = useState<PhysicalFile[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [fileId, setFileId] = useState(defaultFileId ?? "");
  const [toDepartmentId, setToDepartmentId] = useState("");
  const [toHolderUserId, setToHolderUserId] = useState("");
  const [dueAt, setDueAt] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (!token) {
      return;
    }

    Promise.all([getFiles(token, {}), getDepartments(token), getUsers(token)])
      .then(([fileResponse, departmentResponse, userResponse]) => {
        setFiles(fileResponse.data);
        setDepartments(departmentResponse.data);
        setUsers(userResponse.data);
      })
      .catch((error) => {
        setError(error instanceof Error ? error.message : "Unable to load form data");
      });
  }, [token]);

  const eligibleUsers = useMemo(
    () => users.filter((u) => u.department?.id === Number(toDepartmentId)),
    [users, toDepartmentId],
  );

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!token) {
      return;
    }

    setIsSubmitting(true);
    setError(null);

    try {
      await createTransfer(token, {
        file_id: fileId,
        to_department_id: toDepartmentId,
        to_holder_user_id: toHolderUserId,
        due_at: dueAt || undefined,
      });
      onCreated();
    } catch (error) {
      setError(error instanceof Error ? error.message : "Unable to create transfer");
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <Modal open onClose={onClose} title="Create transfer">
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <label className="block text-sm font-medium text-slate-700">
          File
          <select
            value={fileId}
            onChange={(event) => setFileId(event.target.value)}
            className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
            required
          >
            <option value="">Select a file</option>
            {files.map((file) => (
              <option key={file.id} value={file.id}>
                {file.file_number} — {file.title}
              </option>
            ))}
          </select>
        </label>

        <label className="block text-sm font-medium text-slate-700">
          Destination department
          <select
            value={toDepartmentId}
            onChange={(event) => {
              setToDepartmentId(event.target.value);
              setToHolderUserId("");
            }}
            className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
            required
          >
            <option value="">Select a department</option>
            {departments.map((department) => (
              <option key={department.id} value={department.id}>
                {department.name}
              </option>
            ))}
          </select>
        </label>

        <label className="block text-sm font-medium text-slate-700">
          Destination holder
          <select
            value={toHolderUserId}
            onChange={(event) => setToHolderUserId(event.target.value)}
            className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
            required
            disabled={!toDepartmentId}
          >
            <option value="">Select a holder</option>
            {eligibleUsers.map((u) => (
              <option key={u.id} value={u.id}>
                {u.name}
              </option>
            ))}
          </select>
        </label>

        <label className="block text-sm font-medium text-slate-700">
          Due date
          <input
            type="datetime-local"
            value={dueAt}
            onChange={(event) => setDueAt(event.target.value)}
            className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
          />
          <span className="mt-1 block text-xs text-slate-500">
            When should this transfer be acknowledged? Leave blank to use the file category&apos;s default deadline.
          </span>
        </label>

        {error && (
          <div className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {error}
          </div>
        )}

        <div className="mt-2 flex justify-end gap-3">
          <button
            type="button"
            onClick={onClose}
            disabled={isSubmitting}
            className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={isSubmitting}
            className="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {isSubmitting ? "Creating..." : "Create transfer"}
          </button>
        </div>
      </form>
    </Modal>
  );
}
