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
  getUsers,
  resetUserPassword,
  roleLabel,
  updateUser,
  USER_ROLES,
  type Department,
  type User,
  type ValidationErrors,
} from "@/lib/api";

export default function UsersPage() {
  return (
    <ProtectedPage>
      <UsersContent />
    </ProtectedPage>
  );
}

function UsersContent() {
  const { token, user } = useAuth();
  const [users, setUsers] = useState<User[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [reloadKey, setReloadKey] = useState(0);

  const [editingUser, setEditingUser] = useState<User | null>(null);
  const [resettingUser, setResettingUser] = useState<User | null>(null);

  const canManage = useMemo(() => user?.role === "admin", [user]);

  useEffect(() => {
    if (!token) {
      return;
    }

    getUsers(token)
      .then((response) => {
        setUsers(response.data);
        setError(null);
      })
      .catch((error) => {
        setError(error instanceof Error ? error.message : "Unable to load users");
      })
      .finally(() => setIsLoading(false));
  }, [token, reloadKey]);

  useEffect(() => {
    if (!token || !canManage) {
      return;
    }

    getDepartments(token)
      .then((response) => setDepartments(response.data))
      .catch(() => {
        // Departments are optional for the edit form; ignore load failures.
      });
  }, [token, canManage]);

  return (
    <AppShell title="Users" subtitle="Manage system user accounts">
      <div className="flex flex-col gap-4">
        <div className="flex items-center justify-between">
          <p className="text-sm text-slate-600">
            {users.length} active user{users.length === 1 ? "" : "s"}
          </p>
          {canManage && (
            <Link
              href="/users/create"
              className="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
            >
              Create user
            </Link>
          )}
        </div>

        {error && (
          <ErrorState message={error} onRetry={() => setReloadKey((key) => key + 1)} />
        )}

        {isLoading && <LoadingState label="Loading users..." />}

        {!isLoading && users.length === 0 && (
          <EmptyState
            title="No users found"
            description="No active user accounts exist."
          />
        )}

        {!isLoading && users.length > 0 && (
          <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table className="w-full min-w-[760px] text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs uppercase text-slate-500">
                  <th className="py-3 pl-5 pr-4 font-medium">Name</th>
                  <th className="py-3 pr-4 font-medium">Email</th>
                  <th className="py-3 pr-4 font-medium">Role</th>
                  <th className="py-3 pr-4 font-medium">Department</th>
                  <th className="py-3 pr-4 font-medium">Status</th>
                  {canManage && (
                    <th className="py-3 pr-5 font-medium">Actions</th>
                  )}
                </tr>
              </thead>
              <tbody>
                {users.map((u) => (
                  <tr key={u.id} className="border-b border-slate-100">
                    <td className="py-3 pl-5 pr-4 font-medium text-slate-900">
                      {u.name}
                    </td>
                    <td className="py-3 pr-4 text-slate-700">{u.email}</td>
                    <td className="py-3 pr-4 text-slate-700">
                      {roleLabel(u.role)}
                    </td>
                    <td className="py-3 pr-4 text-slate-700">
                      {u.department?.name ?? "—"}
                    </td>
                    <td className="py-3 pr-4">
                      <span
                        className={`inline-flex rounded-md px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${
                          u.is_active === false
                            ? "bg-slate-100 text-slate-600 ring-slate-500/20"
                            : "bg-emerald-50 text-emerald-700 ring-emerald-600/20"
                        }`}
                      >
                        {u.is_active === false ? "Inactive" : "Active"}
                      </span>
                    </td>
                    {canManage && (
                      <td className="py-3 pr-5">
                        <div className="flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => setEditingUser(u)}
                            className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                          >
                            Edit
                          </button>
                          <button
                            type="button"
                            onClick={() => setResettingUser(u)}
                            className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                          >
                            Reset password
                          </button>
                        </div>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {editingUser && (
        <EditUserModal
          user={editingUser}
          departments={departments}
          token={token}
          onClose={() => setEditingUser(null)}
          onSaved={() => {
            setEditingUser(null);
            setReloadKey((key) => key + 1);
          }}
        />
      )}

      {resettingUser && (
        <ResetPasswordModal
          user={resettingUser}
          token={token}
          onClose={() => setResettingUser(null)}
          onSaved={() => {
            setResettingUser(null);
            setReloadKey((key) => key + 1);
          }}
        />
      )}
    </AppShell>
  );
}

function EditUserModal({
  user,
  departments,
  token,
  onClose,
  onSaved,
}: {
  user: User;
  departments: Department[];
  token: string | null;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [name, setName] = useState(user.name);
  const [email, setEmail] = useState(user.email);
  const [role, setRole] = useState<string>(user.role);
  const [departmentId, setDepartmentId] = useState(
    user.department ? String(user.department.id) : "",
  );
  const [isActive, setIsActive] = useState(user.is_active !== false);
  const [validationErrors, setValidationErrors] = useState<ValidationErrors>({});
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!token) {
      return;
    }

    setIsSubmitting(true);
    setError(null);
    setValidationErrors({});

    try {
      await updateUser(token, user.id, {
        name,
        email,
        role,
        department_id: departmentId || undefined,
        is_active: isActive,
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
    <Modal open onClose={onClose} title={`Edit user: ${user.name}`}>
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
          Email
          <input
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
            required
          />
          {validationErrors.email?.[0] && (
            <span className="mt-1 block text-xs text-rose-600">
              {validationErrors.email[0]}
            </span>
          )}
        </label>

        <label className="mt-4 block text-sm font-medium text-slate-700">
          Role
          <select
            value={role}
            onChange={(event) => setRole(event.target.value)}
            className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
            required
          >
            {USER_ROLES.map((r) => (
              <option key={r} value={r}>
                {roleLabel(r)}
              </option>
            ))}
          </select>
          {validationErrors.role?.[0] && (
            <span className="mt-1 block text-xs text-rose-600">
              {validationErrors.role[0]}
            </span>
          )}
        </label>

        <label className="mt-4 block text-sm font-medium text-slate-700">
          Department
          <select
            value={departmentId}
            onChange={(event) => setDepartmentId(event.target.value)}
            className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
          >
            <option value="">No department</option>
            {departments.map((department) => (
              <option key={department.id} value={department.id}>
                {department.name}
              </option>
            ))}
          </select>
          {validationErrors.department_id?.[0] && (
            <span className="mt-1 block text-xs text-rose-600">
              {validationErrors.department_id[0]}
            </span>
          )}
        </label>

        <label className="mt-4 flex items-center gap-2 text-sm font-medium text-slate-700">
          <input
            type="checkbox"
            checked={isActive}
            onChange={(event) => setIsActive(event.target.checked)}
            className="h-4 w-4 rounded border-slate-300"
          />
          Active account
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

function ResetPasswordModal({
  user,
  token,
  onClose,
  onSaved,
}: {
  user: User;
  token: string | null;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [validationErrors, setValidationErrors] = useState<ValidationErrors>({});
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!token) {
      return;
    }

    setIsSubmitting(true);
    setError(null);
    setValidationErrors({});

    try {
      await resetUserPassword(token, user.id, {
        password,
        password_confirmation: passwordConfirmation,
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
    <Modal open onClose={onClose} title={`Reset password for ${user.name}?`}>
      <p className="text-sm leading-6 text-slate-600">
        This will set a new password for {user.name} and sign them out of all
        existing sessions. They will need to sign in again with the new
        password.
      </p>
      <form onSubmit={handleSubmit} className="mt-4">
        {error && (
          <div className="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {error}
          </div>
        )}

        <label className="block text-sm font-medium text-slate-700">
          New password
          <input
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
            required
          />
          {validationErrors.password?.[0] && (
            <span className="mt-1 block text-xs text-rose-600">
              {validationErrors.password[0]}
            </span>
          )}
        </label>

        <label className="mt-4 block text-sm font-medium text-slate-700">
          Confirm new password
          <input
            type="password"
            value={passwordConfirmation}
            onChange={(event) => setPasswordConfirmation(event.target.value)}
            className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
            required
          />
          {validationErrors.password_confirmation?.[0] && (
            <span className="mt-1 block text-xs text-rose-600">
              {validationErrors.password_confirmation[0]}
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
            {isSubmitting ? "Resetting..." : "Reset password"}
          </button>
        </div>
      </form>
    </Modal>
  );
}
