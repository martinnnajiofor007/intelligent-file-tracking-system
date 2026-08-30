"use client";

import Link from "next/link";
import { FormEvent, useEffect, useMemo, useState } from "react";
import { AppShell } from "@/components/AppShell";
import { ProtectedPage } from "@/components/ProtectedPage";
import { Modal } from "@/components/Modal";
import { EmptyState, ErrorState, LoadingState } from "@/components/States";
import { useAuth } from "@/lib/auth";
import {
  getDepartments,
  updateDepartment,
  type Department,
  type ValidationErrors,
} from "@/lib/api";

export default function DepartmentsPage() {
  return (
    <ProtectedPage>
      <DepartmentsContent />
    </ProtectedPage>
  );
}

function DepartmentsContent() {
  const { token, user } = useAuth();
  const [departments, setDepartments] = useState<Department[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [reloadKey, setReloadKey] = useState(0);
  const [editingDepartment, setEditingDepartment] = useState<Department | null>(
    null,
  );

  const canManage = useMemo(() => user?.role === "admin", [user]);

  useEffect(() => {
    if (!token) {
      return;
    }

    getDepartments(token)
      .then((response) => {
        setDepartments(response.data);
        setError(null);
      })
      .catch((error) => {
        setError(
          error instanceof Error ? error.message : "Unable to load departments",
        );
      })
      .finally(() => setIsLoading(false));
  }, [token, reloadKey]);

  return (
    <AppShell
      title="Departments"
      subtitle="Manage organizational departments used for file routing, custody, and user assignments"
    >
      <div className="flex flex-col gap-4">
        <div className="flex items-center justify-between">
          <p className="text-sm text-slate-600">
            {departments.length} department{departments.length === 1 ? "" : "s"}
          </p>
          {canManage && (
            <Link
              href="/departments/create"
              className="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
            >
              Create department
            </Link>
          )}
        </div>

        {error && (
          <ErrorState
            message={error}
            onRetry={() => setReloadKey((key) => key + 1)}
          />
        )}

        {isLoading && <LoadingState label="Loading departments..." />}

        {!isLoading && departments.length === 0 && (
          <EmptyState
            title="No departments found"
            description="No organizational departments exist yet."
          />
        )}

        {!isLoading && departments.length > 0 && (
          <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table className="w-full min-w-[560px] text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs uppercase text-slate-500">
                  <th className="py-3 pl-5 pr-4 font-medium">Name</th>
                  <th className="py-3 pr-4 font-medium">Parent department</th>
                  {canManage && (
                    <th className="py-3 pr-5 font-medium">Actions</th>
                  )}
                </tr>
              </thead>
              <tbody>
                {departments.map((department) => (
                  <tr
                    key={department.id}
                    className="border-b border-slate-100"
                  >
                    <td className="py-3 pl-5 pr-4 font-medium text-slate-900">
                      {department.name}
                    </td>
                    <td className="py-3 pr-4 text-slate-700">
                      {department.parent?.name ?? "—"}
                    </td>
                    {canManage && (
                      <td className="py-3 pr-5">
                        <button
                          type="button"
                          onClick={() => setEditingDepartment(department)}
                          className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        >
                          Edit
                        </button>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {editingDepartment && (
        <EditDepartmentModal
          department={editingDepartment}
          departments={departments}
          token={token}
          onClose={() => setEditingDepartment(null)}
          onSaved={() => {
            setEditingDepartment(null);
            setReloadKey((key) => key + 1);
          }}
        />
      )}
    </AppShell>
  );
}

function EditDepartmentModal({
  department,
  departments,
  token,
  onClose,
  onSaved,
}: {
  department: Department;
  departments: Department[];
  token: string | null;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [name, setName] = useState(department.name);
  const [parentId, setParentId] = useState(
    department.parent ? String(department.parent.id) : "",
  );
  const [validationErrors, setValidationErrors] = useState<ValidationErrors>({});
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const parentOptions = departments.filter((d) => d.id !== department.id);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!token) {
      return;
    }

    setIsSubmitting(true);
    setError(null);
    setValidationErrors({});

    try {
      await updateDepartment(token, department.id, {
        name,
        parent_id: parentId || undefined,
      });
      onSaved();
    } catch (err) {
      const apiError = err as Error & { errors?: ValidationErrors };
      setError(apiError.message);
      setValidationErrors(apiError.errors ?? {});
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <Modal open onClose={onClose} title={`Edit department: ${department.name}`}>
      <form onSubmit={handleSubmit}>
        {error && (
          <div className="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {error}
          </div>
        )}

        <label className="block text-sm font-medium text-slate-700">
          Name
          <input
            value={name}
            onChange={(event) => setName(event.target.value)}
            className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
            required
          />
          {validationErrors.name?.[0] && (
            <span className="mt-1 block text-xs text-rose-600">
              {validationErrors.name[0]}
            </span>
          )}
        </label>

        <label className="mt-4 block text-sm font-medium text-slate-700">
          Parent department
          <select
            value={parentId}
            onChange={(event) => setParentId(event.target.value)}
            className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
          >
            <option value="">No parent department</option>
            {parentOptions.map((d) => (
              <option key={d.id} value={d.id}>
                {d.name}
              </option>
            ))}
          </select>
          {validationErrors.parent_id?.[0] && (
            <span className="mt-1 block text-xs text-rose-600">
              {validationErrors.parent_id[0]}
            </span>
          )}
        </label>

        <div className="mt-6 flex justify-end gap-3">
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
            className="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-400"
          >
            {isSubmitting ? "Saving..." : "Save changes"}
          </button>
        </div>
      </form>
    </Modal>
  );
}
