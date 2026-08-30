"use client";

import { useEffect, useState } from "react";
import { Modal } from "@/components/Modal";
import { createIssue, getFiles, type PhysicalFile } from "@/lib/api";

export function ReportIssueModal({
  token,
  onClose,
  onCreated,
  defaultFileId,
}: {
  token: string | null;
  onClose: () => void;
  onCreated: () => void;
  defaultFileId?: string;
}) {
  const [files, setFiles] = useState<PhysicalFile[]>([]);
  const [fileId, setFileId] = useState(defaultFileId ?? "");
  const [issueType, setIssueType] = useState("");
  const [description, setDescription] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (!token) {
      return;
    }

    getFiles(token, {})
      .then((response) => setFiles(response.data))
      .catch((error) => {
        setError(error instanceof Error ? error.message : "Unable to load files");
      });
  }, [token]);

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!token) {
      return;
    }

    setIsSubmitting(true);
    setError(null);

    try {
      await createIssue(token, fileId, { issue_type: issueType, description });
      onCreated();
    } catch (error) {
      setError(error instanceof Error ? error.message : "Unable to report issue");
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <Modal open onClose={onClose} title="Report issue">
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <label className="block text-sm font-medium text-slate-700">
          File
          <select
            value={fileId}
            onChange={(event) => setFileId(event.target.value)}
            className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
            required
          >
            <option value="">Select a file</option>
            {files.map((file) => (
              <option key={file.id} value={file.id}>
                {file.file_number} — {file.title}
              </option>
            ))}
          </select>
        </label>

        <label className="block text-sm font-medium text-slate-700">
          Issue type
          <input
            value={issueType}
            onChange={(event) => setIssueType(event.target.value)}
            placeholder="e.g. damage, missing_document, misplaced"
            className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
            required
          />
        </label>

        <label className="block text-sm font-medium text-slate-700">
          Description
          <textarea
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            rows={4}
            className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
            required
          />
        </label>

        {error && (
          <div className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {error}
          </div>
        )}

        <div className="mt-2 flex justify-end gap-3">
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
            className="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {isSubmitting ? "Reporting..." : "Report issue"}
          </button>
        </div>
      </form>
    </Modal>
  );
}
