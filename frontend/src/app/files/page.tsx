"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { AppShell } from "@/components/AppShell";
import { AppLink } from "@/components/AppLink";
import { ProtectedPage } from "@/components/ProtectedPage";
import { StatusBadge } from "@/components/StatusBadge";
import { useAuth } from "@/lib/auth";
import {
  createFile,
  getDepartments,
  getFileCategories,
  getFiles,
  getUsers,
  type Department,
  type FileCategory,
  type PhysicalFile,
  type User,
  type ValidationErrors,
} from "@/lib/api";

type FileForm = {
  file_number: string;
  title: string;
  description: string;
  category_id: string;
  confirmed_department_id: string;
  confirmed_holder_user_id: string;
};

const emptyForm: FileForm = {
  file_number: "",
  title: "",
  description: "",
  category_id: "",
  confirmed_department_id: "",
  confirmed_holder_user_id: "",
};

export default function FilesPage() {
  return (
    <ProtectedPage>
      <FilesContent />
    </ProtectedPage>
  );
}

function FilesContent() {
  const router = useRouter();
  const { token, user } = useAuth();
  const [files, setFiles] = useState<PhysicalFile[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [categories, setCategories] = useState<FileCategory[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [search, setSearch] = useState("");
  const [categoryId, setCategoryId] = useState("");
  const [departmentId, setDepartmentId] = useState("");
  const [status, setStatus] = useState("");
  const [form, setForm] = useState<FileForm>(emptyForm);
  const [validationErrors, setValidationErrors] = useState<ValidationErrors>({});
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isCreating, setIsCreating] = useState(false);

  const canRegisterFiles = useMemo(
    () => user?.role === "admin" || user?.role === "registry_staff",
    [user],
  );

  useEffect(() => {
    if (!token) {
      return;
    }

    Promise.all([
      getDepartments(token),
      getFileCategories(token),
      getUsers(token),
    ])
      .then(([departmentResponse, categoryResponse, userResponse]) => {
        setDepartments(departmentResponse.data);
        setCategories(categoryResponse.data);
        setUsers(userResponse.data);
      })
      .catch((error) => {
        setError(error instanceof Error ? error.message : "Unable to load setup data");
      });
  }, [token]);

  useEffect(() => {
    if (!token) {
      return;
    }

    getFiles(token, {
      search,
      category_id: categoryId,
      department_id: departmentId,
      status,
    })
      .then((response) => {
        setFiles(response.data);
      })
      .catch((error) => {
        setError(error instanceof Error ? error.message : "Unable to load files");
      })
      .finally(() => setIsLoading(false));
  }, [token, search, categoryId, departmentId, status]);

  async function handleCreateFile(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!token) {
      return;
    }

    setIsCreating(true);
    setError(null);
    setMessage(null);
    setValidationErrors({});

    try {
      const response = await createFile(token, form);
      setMessage("File registered successfully.");
      setForm(emptyForm);
      router.push(`/files/${response.data.id}`);
    } catch (error) {
      const apiError = error as Error & { errors?: ValidationErrors };
      setError(apiError.message);
      setValidationErrors(apiError.errors ?? {});
    } finally {
      setIsCreating(false);
    }
  }

  return (
    <AppShell title="Files" subtitle="Register and track physical files">
      <div className="flex flex-col gap-4">
        {error && (
          <div className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {error}
          </div>
        )}
        {message && (
          <div className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            {message}
          </div>
        )}

        <section className="grid gap-6 lg:grid-cols-[1fr_360px]">
          <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div className="grid gap-3 md:grid-cols-4">
              <input
                value={search}
                onChange={(event) => {
                  setSearch(event.target.value);
                  setIsLoading(true);
                }}
                placeholder="Search number or title"
                className="rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
              />
              <select
                value={categoryId}
                onChange={(event) => {
                  setCategoryId(event.target.value);
                  setIsLoading(true);
                }}
                className="rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
              >
                <option value="">All categories</option>
                {categories.map((category) => (
                  <option key={category.id} value={category.id}>
                    {category.name}
                  </option>
                ))}
              </select>
              <select
                value={departmentId}
                onChange={(event) => {
                  setDepartmentId(event.target.value);
                  setIsLoading(true);
                }}
                className="rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
              >
                <option value="">All departments</option>
                {departments.map((department) => (
                  <option key={department.id} value={department.id}>
                    {department.name}
                  </option>
                ))}
              </select>
              <select
                value={status}
                onChange={(event) => {
                  setStatus(event.target.value);
                  setIsLoading(true);
                }}
                className="rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
              >
                <option value="">All statuses</option>
                <option value="active">Active</option>
              </select>
            </div>

            <div className="mt-5 overflow-x-auto">
              <table className="w-full min-w-[760px] text-left text-sm">
                <thead>
                  <tr className="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <th className="py-3 pr-4 font-medium">File number</th>
                    <th className="py-3 pr-4 font-medium">Title</th>
                    <th className="py-3 pr-4 font-medium">Status</th>
                    <th className="py-3 pr-4 font-medium">Department</th>
                    <th className="py-3 pr-4 font-medium">Holder</th>
                  </tr>
                </thead>
                <tbody>
                  {isLoading && (
                    <tr>
                      <td className="py-5 text-slate-500" colSpan={5}>
                        Loading files...
                      </td>
                    </tr>
                  )}
                  {!isLoading &&
                    files.map((file) => (
                      <tr key={file.id} className="border-b border-slate-100">
                        <td className="py-3 pr-4 font-medium">
                          <AppLink href={`/files/${file.id}`}>
                            {file.file_number}
                          </AppLink>
                        </td>
                        <td className="py-3 pr-4 text-slate-700">{file.title}</td>
                        <td className="py-3 pr-4">
                          <StatusBadge status={file.status} kind="file" />
                        </td>
                        <td className="py-3 pr-4 text-slate-700">
                          {file.confirmed_department?.name ?? "Unassigned"}
                        </td>
                        <td className="py-3 pr-4 text-slate-700">
                          {file.confirmed_holder?.name ?? "Unassigned"}
                        </td>
                      </tr>
                    ))}
                  {!isLoading && files.length === 0 && (
                    <tr>
                      <td className="py-5 text-slate-500" colSpan={5}>
                        No files found.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {canRegisterFiles && (
            <form
              onSubmit={handleCreateFile}
              className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
            >
              <h2 className="text-base font-semibold">Register File</h2>
              <p className="mt-1 text-sm text-slate-600">
                Initial custody is confirmed when the file is registered.
              </p>

              <FormField
                label="File number"
                value={form.file_number}
                onChange={(value) => setForm({ ...form, file_number: value })}
                error={validationErrors.file_number?.[0]}
                required
              />
              <FormField
                label="Title"
                value={form.title}
                onChange={(value) => setForm({ ...form, title: value })}
                error={validationErrors.title?.[0]}
                required
              />
              <label className="mt-4 block text-sm font-medium text-slate-700">
                Description
                <textarea
                  value={form.description}
                  onChange={(event) =>
                    setForm({ ...form, description: event.target.value })
                  }
                  rows={3}
                  className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
                />
              </label>
              <SelectField
                label="Category"
                value={form.category_id}
                onChange={(value) => setForm({ ...form, category_id: value })}
                options={categories}
                error={validationErrors.category_id?.[0]}
              />
              <SelectField
                label="Confirmed department"
                value={form.confirmed_department_id}
                onChange={(value) =>
                  setForm({ ...form, confirmed_department_id: value })
                }
                options={departments}
                error={validationErrors.confirmed_department_id?.[0]}
              />
              <SelectField
                label="Confirmed holder"
                value={form.confirmed_holder_user_id}
                onChange={(value) =>
                  setForm({ ...form, confirmed_holder_user_id: value })
                }
                options={users}
                error={validationErrors.confirmed_holder_user_id?.[0]}
              />

              <button
                type="submit"
                disabled={isCreating}
                className="mt-5 w-full rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:bg-slate-400"
              >
                {isCreating ? "Registering..." : "Register file"}
              </button>
            </form>
          )}
        </section>
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
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  required?: boolean;
}) {
  return (
    <label className="mt-4 block text-sm font-medium text-slate-700">
      {label}
      <input
        value={value}
        onChange={(event) => onChange(event.target.value)}
        required={required}
        className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
      />
      {error && <span className="mt-1 block text-xs text-rose-600">{error}</span>}
    </label>
  );
}

function SelectField<T extends { id: number; name: string }>({
  label,
  value,
  onChange,
  options,
  error,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  options: T[];
  error?: string;
}) {
  return (
    <label className="mt-4 block text-sm font-medium text-slate-700">
      {label}
      <select
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
      >
        <option value="">Unassigned</option>
        {options.map((option) => (
          <option key={option.id} value={option.id}>
            {option.name}
          </option>
        ))}
      </select>
      {error && <span className="mt-1 block text-xs text-rose-600">{error}</span>}
    </label>
  );
}
