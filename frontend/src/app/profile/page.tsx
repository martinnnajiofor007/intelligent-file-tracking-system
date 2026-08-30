"use client";

import { FormEvent, useState } from "react";
import { AppShell } from "@/components/AppShell";
import { ProtectedPage } from "@/components/ProtectedPage";
import { Card } from "@/components/Card";
import { useAuth } from "@/lib/auth";
import {
  changePassword,
  roleLabel,
  updateProfile,
  type ValidationErrors,
} from "@/lib/api";

export default function ProfilePage() {
  return (
    <ProtectedPage>
      <ProfileContent />
    </ProtectedPage>
  );
}

function ProfileContent() {
  const { user, token, refreshUser } = useAuth();

  const [name, setName] = useState(user?.name ?? "");
  const [email, setEmail] = useState(user?.email ?? "");
  const [profileErrors, setProfileErrors] = useState<ValidationErrors>({});
  const [profileMessage, setProfileMessage] = useState<string | null>(null);
  const [profileError, setProfileError] = useState<string | null>(null);
  const [isSavingProfile, setIsSavingProfile] = useState(false);

  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [passwordErrors, setPasswordErrors] = useState<ValidationErrors>({});
  const [passwordMessage, setPasswordMessage] = useState<string | null>(null);
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [isSavingPassword, setIsSavingPassword] = useState(false);

  async function handleProfileSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!token) {
      return;
    }

    setIsSavingProfile(true);
    setProfileMessage(null);
    setProfileError(null);
    setProfileErrors({});

    try {
      const response = await updateProfile(token, { name, email });
      await refreshUser();
      setName(response.data.name);
      setEmail(response.data.email);
      setProfileMessage("Profile updated successfully.");
    } catch (err) {
      const apiError = err as Error & { errors?: ValidationErrors };
      setProfileError(apiError.message);
      setProfileErrors(apiError.errors ?? {});
    } finally {
      setIsSavingProfile(false);
    }
  }

  async function handlePasswordSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!token) {
      return;
    }

    setIsSavingPassword(true);
    setPasswordMessage(null);
    setPasswordError(null);
    setPasswordErrors({});

    try {
      await changePassword(token, {
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: confirmPassword,
      });
      setCurrentPassword("");
      setNewPassword("");
      setConfirmPassword("");
      setPasswordMessage("Password changed successfully.");
    } catch (err) {
      const apiError = err as Error & { errors?: ValidationErrors };
      setPasswordError(apiError.message);
      setPasswordErrors(apiError.errors ?? {});
    } finally {
      setIsSavingPassword(false);
    }
  }

  return (
    <AppShell title="Profile" subtitle="Your account details and security">
      <div className="mx-auto max-w-2xl">
        <Card>
          <div className="flex items-center gap-4">
            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-slate-200 text-xl font-semibold text-slate-700">
              {(user?.name ?? "U").charAt(0).toUpperCase()}
            </div>
            <div>
              <h2 className="text-lg font-semibold text-slate-950">
                {user?.name ?? "User"}
              </h2>
              <p className="text-sm text-slate-600">{user?.email}</p>
              <p className="mt-1 text-xs font-medium text-slate-500">
                {user ? roleLabel(user.role) : ""}
              </p>
            </div>
          </div>
        </Card>

        <Card className="mt-6">
          <h3 className="text-base font-semibold text-slate-950">
            Edit profile
          </h3>
          <p className="mt-1 text-sm text-slate-600">
            Update your name and email. Your role is managed by an
            administrator.
          </p>

          {profileMessage && (
            <div className="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
              {profileMessage}
            </div>
          )}
          {profileError && (
            <div className="mt-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
              {profileError}
            </div>
          )}

          <form onSubmit={handleProfileSubmit} className="mt-4">
            <label className="block text-sm font-medium text-slate-700">
              Name
              <input
                value={name}
                onChange={(event) => setName(event.target.value)}
                className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
                required
              />
              {profileErrors.name?.[0] && (
                <span className="mt-1 block text-xs text-rose-600">
                  {profileErrors.name[0]}
                </span>
              )}
            </label>

            <label className="mt-4 block text-sm font-medium text-slate-700">
              Email
              <input
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
                required
              />
              {profileErrors.email?.[0] && (
                <span className="mt-1 block text-xs text-rose-600">
                  {profileErrors.email[0]}
                </span>
              )}
            </label>

            <button
              type="submit"
              disabled={isSavingProfile}
              className="mt-5 rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-400"
            >
              {isSavingProfile ? "Saving..." : "Save changes"}
            </button>
          </form>
        </Card>

        <Card className="mt-6">
          <h3 className="text-base font-semibold text-slate-950">
            Change password
          </h3>
          <p className="mt-1 text-sm text-slate-600">
            Choose a new password. You will need to enter your current password
            to confirm the change.
          </p>

          {passwordMessage && (
            <div className="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
              {passwordMessage}
            </div>
          )}
          {passwordError && (
            <div className="mt-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
              {passwordError}
            </div>
          )}

          <form onSubmit={handlePasswordSubmit} className="mt-4">
            <label className="block text-sm font-medium text-slate-700">
              Current password
              <input
                type="password"
                value={currentPassword}
                onChange={(event) => setCurrentPassword(event.target.value)}
                className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
                required
              />
              {passwordErrors.current_password?.[0] && (
                <span className="mt-1 block text-xs text-rose-600">
                  {passwordErrors.current_password[0]}
                </span>
              )}
            </label>

            <label className="mt-4 block text-sm font-medium text-slate-700">
              New password
              <input
                type="password"
                value={newPassword}
                onChange={(event) => setNewPassword(event.target.value)}
                className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
                required
              />
              {passwordErrors.password?.[0] && (
                <span className="mt-1 block text-xs text-rose-600">
                  {passwordErrors.password[0]}
                </span>
              )}
            </label>

            <label className="mt-4 block text-sm font-medium text-slate-700">
              Confirm new password
              <input
                type="password"
                value={confirmPassword}
                onChange={(event) => setConfirmPassword(event.target.value)}
                className="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
                required
              />
              {passwordErrors.password_confirmation?.[0] && (
                <span className="mt-1 block text-xs text-rose-600">
                  {passwordErrors.password_confirmation[0]}
                </span>
              )}
            </label>

            <button
              type="submit"
              disabled={isSavingPassword}
              className="mt-5 rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-400"
            >
              {isSavingPassword ? "Updating..." : "Change password"}
            </button>
          </form>
        </Card>
      </div>
    </AppShell>
  );
}
