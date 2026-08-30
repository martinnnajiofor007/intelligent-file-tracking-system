const TRANSFER_STYLES: Record<string, string> = {
  pending: "bg-amber-50 text-amber-700 ring-amber-600/20",
  acknowledged: "bg-emerald-50 text-emerald-700 ring-emerald-600/20",
  rejected: "bg-rose-50 text-rose-700 ring-rose-600/20",
};

const ISSUE_STYLES: Record<string, string> = {
  open: "bg-rose-50 text-rose-700 ring-rose-600/20",
  in_progress: "bg-blue-50 text-blue-700 ring-blue-600/20",
  resolved: "bg-emerald-50 text-emerald-700 ring-emerald-600/20",
  dismissed: "bg-slate-100 text-slate-600 ring-slate-500/20",
};

const FILE_STYLES: Record<string, string> = {
  active: "bg-emerald-50 text-emerald-700 ring-emerald-600/20",
};

const DEFAULT_STYLES = "bg-slate-100 text-slate-600 ring-slate-500/20";

export function StatusBadge({
  status,
  kind = "default",
}: {
  status: string;
  kind?: "transfer" | "issue" | "file" | "default";
}) {
  const styles =
    kind === "transfer"
      ? (TRANSFER_STYLES[status] ?? DEFAULT_STYLES)
      : kind === "issue"
        ? (ISSUE_STYLES[status] ?? DEFAULT_STYLES)
        : kind === "file"
          ? (FILE_STYLES[status] ?? DEFAULT_STYLES)
          : DEFAULT_STYLES;

  return (
    <span
      className={`inline-flex rounded-md px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${styles}`}
    >
      {status.replace(/_/g, " ")}
    </span>
  );
}
