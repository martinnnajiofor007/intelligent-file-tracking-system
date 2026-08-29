"use client";

import { useEffect, useState } from "react";
import { getBackendHealth, type BackendHealth } from "@/lib/api";

type HealthState =
  | { status: "loading" }
  | { status: "online"; data: BackendHealth }
  | { status: "offline"; message: string };

export function BackendHealthCard() {
  const [health, setHealth] = useState<HealthState>({ status: "loading" });

  useEffect(() => {
    let isMounted = true;

    getBackendHealth()
      .then((data) => {
        if (isMounted) {
          setHealth({ status: "online", data });
        }
      })
      .catch((error: unknown) => {
        if (isMounted) {
          setHealth({
            status: "offline",
            message:
              error instanceof Error
                ? error.message
                : "Unable to reach backend API",
          });
        }
      });

    return () => {
      isMounted = false;
    };
  }, []);

  return (
    <section className="w-full max-w-xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h2 className="text-base font-semibold text-slate-950">
            Backend API
          </h2>
          <p className="mt-1 text-sm text-slate-600">
            Laravel health endpoint connection status.
          </p>
        </div>
        <StatusBadge status={health.status} />
      </div>

      <div className="mt-5 rounded-md bg-slate-50 p-4 text-sm text-slate-700">
        {health.status === "loading" && "Checking API connection..."}
        {health.status === "offline" && health.message}
        {health.status === "online" && (
          <dl className="grid gap-3 sm:grid-cols-3">
            <div>
              <dt className="text-xs font-medium uppercase text-slate-500">
                Status
              </dt>
              <dd className="mt-1 font-semibold text-slate-950">
                {health.data.status}
              </dd>
            </div>
            <div>
              <dt className="text-xs font-medium uppercase text-slate-500">
                Service
              </dt>
              <dd className="mt-1 font-semibold text-slate-950">
                {health.data.service}
              </dd>
            </div>
            <div>
              <dt className="text-xs font-medium uppercase text-slate-500">
                Environment
              </dt>
              <dd className="mt-1 font-semibold text-slate-950">
                {health.data.environment}
              </dd>
            </div>
          </dl>
        )}
      </div>
    </section>
  );
}

function StatusBadge({ status }: { status: HealthState["status"] }) {
  const label =
    status === "online" ? "Online" : status === "offline" ? "Offline" : "Checking";
  const classes =
    status === "online"
      ? "bg-emerald-50 text-emerald-700 ring-emerald-600/20"
      : status === "offline"
        ? "bg-rose-50 text-rose-700 ring-rose-600/20"
        : "bg-amber-50 text-amber-700 ring-amber-600/20";

  return (
    <span
      className={`rounded-md px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${classes}`}
    >
      {label}
    </span>
  );
}
