"use client";

import { FormEvent, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { AppShell } from "@/components/AppShell";
import { ProtectedPage } from "@/components/ProtectedPage";
import { useAuth } from "@/lib/auth";
import {
  createDepartment,
  getDepartments,
  type Department,
  type ValidationErrors,
} from "@/lib/api";

export default function CreateDepartmentPage() {
  return (
    <ProtectedPage>
      <CreateDepartmentContent />
    </ProtectedPage>
  );
}

function CreateDepartmentContent() {
  const router = useRouter();
  const { token, user } = useAuth();
  const [departments, setDepartments] = useState<Department[]>([]);
  const [name, setName] = useState("");
  const [parentId, setParentId] = useState("");
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
      const response = await createDepartment(token, {
        name,
        parent_id: parentId || undefined,
      });
      router.push(`/departments?created=${response.data.id}`);
    } catch (err) {
      const apiError = err as Error & { errors?: ValidationErrors };
      setError(apiError.message);
      setValidationErrors(apiError.errors ?? {});
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <AppShell
      title="Create department"
      subtitle="Add a new organizational department"
    >
      <div className="mx-auto max-w-xl">
        {!isAdmin ? (
          <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            You do not have permission to create departments.
          </div>
        ) : (
          <form
            onSubmit={handleSubmit}
            className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
          >
            <h2 className="text-base font-semibold text-slate-950">
              New department
            </h2>
            <p className="mt-1 text-sm text-slate-600">
              Departments are used for file routing, custody, and user
              assignments.
            </p>

            {error && (
              <div className="mt-5 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                {error}
              </div>
            )}

            <label className="mt-4 block text-sm font-medium text-slate-700">
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
                {departments.map((department) => (
                  <option key={department.id} value={department.id}>
                    {department.name}
                  </option>
                ))}
              </select>
              {validationErrors.parent_id?.[0] && (
                <span className="mt-1 block text-xs text-rose-600">
                  {validationErrors.parent_id[0]}
                </span>
              )}
            </label>

            <div className="mt-6 flex items-center gap-3">
              <button
                type="submit"
                disabled={isSubmitting}
                className="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-400"
              >
                {isSubmitting ? "Creating..." : "Create department"}
              </button>
              <button
                type="button"
                onClick={() => router.push("/departments")}
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
