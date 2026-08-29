import { BackendHealthCard } from "@/components/BackendHealthCard";

export default function Home() {
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
            Project foundation shell for the Laravel API and Next.js frontend.
          </p>
        </header>

        <BackendHealthCard />
      </div>
    </main>
  );
}
