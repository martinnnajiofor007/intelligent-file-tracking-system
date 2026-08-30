"use client";

import Link from "next/link";
import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth";
import { BackendHealthCard } from "@/components/BackendHealthCard";

export default function Home() {
  const router = useRouter();
  const { isAuthenticated, isLoading } = useAuth();

  useEffect(() => {
    if (!isLoading && isAuthenticated) {
      router.replace("/dashboard");
    }
  }, [isLoading, isAuthenticated, router]);

  return (
    <main className="min-h-screen bg-slate-100 px-6 py-10 text-slate-950">
      <div className="mx-auto flex w-full max-w-5xl flex-col gap-8">
        <header className="border-b border-slate-200 pb-6">
          <p className="text-sm font-medium text-slate-600">
            Frontier Engineering Challenge 2026
          </p>
          <h1 className="mt-3 max-w-3xl text-3xl font-semibold">
            Intelligent File Tracking System
          </h1>
          <p className="mt-3 max-w-2xl text-base leading-7 text-slate-600">
            Track physical files, their custody, transfers, issues, audit
            history, overdue transfers, and user notifications.
          </p>
          <div className="mt-6 flex flex-wrap gap-3">
            <Link
              href="/login"
              className="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white"
            >
              Sign in
            </Link>
            <Link
              href="/dashboard"
              className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700"
            >
              Dashboard
            </Link>
          </div>
        </header>

        <BackendHealthCard />
      </div>
    </main>
  );
}
