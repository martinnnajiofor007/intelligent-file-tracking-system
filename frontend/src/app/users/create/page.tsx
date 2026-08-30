"use client";

import { FormEvent, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { AppShell } from "@/components/AppShell";
import { ProtectedPage } from "@/components/ProtectedPage";
import { useAuth } from "@/lib/auth";
import {
  createUser,
  getDepartments,
  USER_ROLES,
  roleLabel,
  type Department,
  type ValidationErrors,
} from "@/lib/api";

type UserForm = {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  role: string;
  department_id: string;
};

const emptyForm: UserForm = {
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  role: "",
  department_id: "",
};

export default function CreateUserPage() {
  return (
    <ProtectedPage>
      <CreateUserContent />
    </ProtectedPage>
  );
}

function CreateUserContent() {
  const router = useRouter();
  const { token, user } = useAuth();
  const [departments, setDepartments] = useState<Department[]>([]);
  const [form, setForm] = useState<UserForm>(emptyForm);
  const [validationErrors, setValidationErrors] = useState<ValidationErrors>({});
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const isAdmin = user?.role === "admin";

  useEffect(() => {
    if (!token) {
      return;
    }

    getDepartments(token)
      .then((response) => setDepartments(response.data))
      .catch((err) => {
        setError(err instanceof Error ? err.message : "Unable to load departments");
      });
  }, [token]);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!token) {
      return;
    }

    setIsSubmitting(true);
    setError(null);
    setValidationErrors({});

    try {
      const response = await createUser(token, {
        ...form,
        department_id: form.department_id || undefined,
      });
      router.push(`/users?created=${response.data.id}`);
    } catch (err) {
      const apiError = err as Error & { errors?: ValidationErrors };
      setError(apiError.message);
      setValidationErrors(apiError.errors ?? {});
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <AppShell title="Create user" subtitle="Add a new system user account">
      <div className="mx-auto max-w-xl">
        {!isAdmin ? (
          <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            You do not have permission to create users.
          </div>
        ) : (
          <form
            onSubmit={handleSubmit}
            className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
          >
            <h2 className="text-base font-semibold text-slate-950">
              New user
            </h2>
            <p className="mt-1 text-sm text-slate-600">
              The new user will be able to sign in immediately with the
              credentials you set.
            </p>

            {error && (
              <div className="mt-5 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                {error}
              </div>
            )}

            <FormField
              label="Full name"
              value={form.name}
              onChange={(value) => setForm({ ...form, name: value })}
              error={validationErrors.name?.[0]}
              required
            />
            <FormField
              label="Email"
              type="email"
              value={form.email}
              onChange={(value) => setForm({ ...form, email: value })}
              error={validationErrors.email?.[0]}
              required
            />
            <FormField
              label="Password"
              type="password"
              value={form.password}
              onChange={(value) => setForm({ ...form, password: value })}
              error={validationErrors.password?.[0]}
              required
            />
            <FormField
              label="Confirm password"
              type="password"
              value={form.password_confirmation}
              onChange={(value) =>
                setForm({ ...form, password_confirmation: value })
              }
              error={validationErrors.password_confirmation?.[0]}
              required
            />

            <label className="mt-4 block text-sm font-medium text-slate-700">
              Role
              <select
                value={form.role}
                onChange={(event) => setForm({ ...form, role: event.target.value })}
                className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
                required
              >
                <option value="">Select a role</option>
                {USER_ROLES.map((role) => (
                  <option key={role} value={role}>
                    {roleLabel(role)}
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
                value={form.department_id}
                onChange={(event) =>
                  setForm({ ...form, department_id: event.target.value })
                }
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

            <div className="mt-6 flex items-center gap-3">
              <button
                type="submit"
                disabled={isSubmitting}
                className="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-400"
              >
                {isSubmitting ? "Creating..." : "Create user"}
              </button>
              <button
                type="button"
                onClick={() => router.push("/users")}
                className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
              >
                Cancel
              </button>
            </div>
          </form>
        )}
      </div>
    </AppShell>
  );
}

function FormField({
  label,
  value,
  onChange,
  error,
  required,
  type = "text",
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  required?: boolean;
  type?: string;
}) {
  return (
    <label className="mt-4 block text-sm font-medium text-slate-700">
      {label}
      <input
        type={type}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        required={required}
        className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
      />
      {error && <span className="mt-1 block text-xs text-rose-600">{error}</span>}
    </label>
  );
}
