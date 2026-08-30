import Link from "next/link";
import type { ReactNode } from "react";

/**
 * Consistent link styling for interactive text that navigates within the app.
 * Visually distinct from plain text (underline + emphasis), with a clear hover
 * state and an accessible keyboard focus ring.
 */
export function AppLink({
  href,
  children,
  className = "",
}: {
  href: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <Link
      href={href}
      className={`font-medium text-slate-900 underline decoration-slate-300 underline-offset-2 transition-colors hover:text-slate-950 hover:decoration-slate-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500 ${className}`}
    >
      {children}
    </Link>
  );
}
