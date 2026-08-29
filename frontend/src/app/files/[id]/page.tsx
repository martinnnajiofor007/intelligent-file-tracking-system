"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { getFile, getStoredToken, type PhysicalFile } from "@/lib/api";

export default function FileDetailsPage() {
  const params = useParams<{ id: string }>();
  const router = useRouter();
  const [file, setFile] = useState<PhysicalFile | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const token = getStoredToken();

    if (!token) {
      router.push("/login");
      return;
    }

    getFile(token, params.id)
      .then((response) => setFile(response.data))
      .catch((error) => {
        setError(error instanceof Error ? error.message : "Unable to load file");
      })
      .finally(() => setIsLoading(false));
  }, [params.id, router]);

  return (
    <main className="min-h-screen bg-slate-100 px-6 py-8 text-slate-950">
      <div className="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <header className="flex items-center justify-between border-b border-slate-200 pb-5">
          <div>
            <p className="text-sm font-medium text-slate-600">File details</p>
            <h1 className="mt-1 text-2xl font-semibold">
              {file?.file_number ?? "Loading file"}
            </h1>
          </div>
          <Link href="/files" className="text-sm font-medium text-slate-600">
            Back to files
          </Link>
        </header>

        {isLoading && (
          <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            Loading file details...
          </section>
        )}

        {error && (
          <div className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {error}
          </div>
        )}

        {file && (
          <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div className="flex flex-col justify-between gap-4 border-b border-slate-100 pb-5 md:flex-row">
              <div>
                <h2 className="text-xl font-semibold">{file.title}</h2>
                <p className="mt-2 text-sm leading-6 text-slate-600">
                  {file.description ?? "No description provided."}
                </p>
              </div>
              <span className="h-fit rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                {file.status}
              </span>
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
          </section>
        )}
      </div>
    </main>
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
