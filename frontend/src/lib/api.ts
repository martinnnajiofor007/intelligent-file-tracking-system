export type BackendHealth = {
  status: string;
  service: string;
  environment: string;
};

export type Department = {
  id: number;
  name: string;
  parent: Pick<Department, "id" | "name"> | null;
};

export type FileCategory = {
  id: number;
  name: string;
  default_due_days: number | null;
};

export type User = {
  id: number;
  name: string;
  email: string;
  role: "admin" | "registry_staff" | "department_staff" | "supervisor";
  is_active?: boolean;
  department: Pick<Department, "id" | "name"> | null;
};

export const USER_ROLES = [
  "admin",
  "registry_staff",
  "department_staff",
  "supervisor",
] as const;

export type UserRole = (typeof USER_ROLES)[number];

export const ROLE_LABELS: Record<UserRole, string> = {
  admin: "Admin",
  registry_staff: "Registry Staff",
  department_staff: "Department Staff",
  supervisor: "Supervisor",
};

export function roleLabel(role: string): string {
  return ROLE_LABELS[role as UserRole] ?? role.replace(/_/g, " ");
}

export type PhysicalFile = {
  id: number;
  file_number: string;
  title: string;
  description: string | null;
  category: FileCategory | null;
  status: "active";
  confirmed_department: Pick<Department, "id" | "name"> | null;
  confirmed_holder: Pick<User, "id" | "name" | "email"> | null;
  registered_by: Pick<User, "id" | "name" | "email"> | null;
  registered_at: string;
  created_at: string;
  updated_at: string;
};

export type PaginatedResponse<T> = {
  data: T[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
};

export type ValidationErrors = Record<string, string[]>;

export type AuditLog = {
  id: number;
  actor: Pick<User, "id" | "name" | "email"> | null;
  action: string;
  entity_type: string;
  entity_id: number;
  before: Record<string, unknown> | null;
  after: Record<string, unknown> | null;
  created_at: string;
  updated_at: string;
};

export type Notification = {
  id: number;
  type: string;
  title: string;
  message: string;
  related_type: string | null;
  related_id: number | null;
  metadata: Record<string, unknown> | null;
  read_at: string | null;
  is_read: boolean;
  created_at: string;
  updated_at: string;
};

export type Transfer = {
  id: number;
  file_id: number;
  from_department: Pick<Department, "id" | "name"> | null;
  from_holder: Pick<User, "id" | "name" | "email"> | null;
  to_department: Pick<Department, "id" | "name"> | null;
  to_holder: Pick<User, "id" | "name" | "email"> | null;
  requested_by: Pick<User, "id" | "name" | "email"> | null;
  requested_at: string | null;
  status: "pending" | "acknowledged" | "rejected";
  acknowledged_by: Pick<User, "id" | "name" | "email"> | null;
  acknowledged_at: string | null;
  rejected_by: Pick<User, "id" | "name" | "email"> | null;
  rejected_at: string | null;
  due_at: string | null;
  is_overdue: boolean;
  created_at: string;
  updated_at: string;
};

export type FileIssue = {
  id: number;
  file: Pick<PhysicalFile, "id" | "file_number" | "title"> | null;
  issue_type: string;
  description: string;
  status: "open" | "in_progress" | "resolved" | "dismissed";
  reported_by: Pick<User, "id" | "name" | "email"> | null;
  resolved_by: Pick<User, "id" | "name" | "email"> | null;
  resolved_at: string | null;
  created_at: string;
  updated_at: string;
};

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api";

export async function getBackendHealth(): Promise<BackendHealth> {
  const response = await fetch(`${API_BASE_URL}/health`, {
    headers: {
      Accept: "application/json",
    },
    cache: "no-store",
  });

  if (!response.ok) {
    throw new Error(`Backend health check failed with ${response.status}`);
  }

  return response.json() as Promise<BackendHealth>;
}

export function getStoredToken(): string | null {
  if (typeof window === "undefined") {
    return null;
  }

  return window.localStorage.getItem("auth_token");
}

export function storeToken(token: string): void {
  window.localStorage.setItem("auth_token", token);
}

export function clearToken(): void {
  window.localStorage.removeItem("auth_token");
}

export async function login(email: string, password: string) {
  const response = await fetch(`${API_BASE_URL}/auth/login`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ email, password }),
  });

  return parseResponse<{ data: { user: User; token: string } }>(response);
}

export async function getCurrentUser(token: string) {
  const response = await fetch(`${API_BASE_URL}/auth/me`, {
    headers: authHeaders(token),
    cache: "no-store",
  });

  return parseResponse<{ data: User }>(response);
}

export async function logout(token: string) {
  const response = await fetch(`${API_BASE_URL}/auth/logout`, {
    method: "POST",
    headers: authHeaders(token),
  });

  return parseResponse<{ message: string }>(response);
}

export async function getDepartments(token: string) {
  const response = await fetch(`${API_BASE_URL}/departments`, {
    headers: authHeaders(token),
    cache: "no-store",
  });

  return parseResponse<{ data: Department[] }>(response);
}

export async function createDepartment(
  token: string,
  payload: { name: string; parent_id?: string },
) {
  const response = await fetch(`${API_BASE_URL}/departments`, {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });

  return parseResponse<{ data: Department }>(response);
}

export async function updateDepartment(
  token: string,
  id: number,
  payload: { name: string; parent_id?: string },
) {
  const response = await fetch(`${API_BASE_URL}/departments/${id}`, {
    method: "PATCH",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });

  return parseResponse<{ data: Department }>(response);
}

export async function getFileCategories(token: string) {
  const response = await fetch(`${API_BASE_URL}/file-categories`, {
    headers: authHeaders(token),
    cache: "no-store",
  });

  return parseResponse<{ data: FileCategory[] }>(response);
}

export async function getUsers(token: string) {
  const response = await fetch(`${API_BASE_URL}/users`, {
    headers: authHeaders(token),
    cache: "no-store",
  });

  return parseResponse<{ data: User[] }>(response);
}

export async function createUser(
  token: string,
  payload: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: string;
    department_id?: string;
  },
) {
  const response = await fetch(`${API_BASE_URL}/users`, {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });

  return parseResponse<{ data: User }>(response);
}

export async function updateUser(
  token: string,
  id: number,
  payload: {
    name: string;
    email: string;
    role: string;
    department_id?: string;
    is_active?: boolean;
  },
) {
  const response = await fetch(`${API_BASE_URL}/users/${id}`, {
    method: "PATCH",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });

  return parseResponse<{ data: User }>(response);
}

export async function resetUserPassword(
  token: string,
  id: number,
  payload: { password: string; password_confirmation: string },
) {
  const response = await fetch(`${API_BASE_URL}/users/${id}/password`, {
    method: "PATCH",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });

  return parseResponse<{ message: string }>(response);
}

export async function updateProfile(
  token: string,
  payload: { name: string; email: string },
) {
  const response = await fetch(`${API_BASE_URL}/auth/profile`, {
    method: "PATCH",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });

  return parseResponse<{ data: User }>(response);
}

export async function changePassword(
  token: string,
  payload: {
    current_password: string;
    password: string;
    password_confirmation: string;
  },
) {
  const response = await fetch(`${API_BASE_URL}/auth/change-password`, {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });

  return parseResponse<{ message: string }>(response);
}

export async function getFiles(
  token: string,
  filters: {
    search?: string;
    category_id?: string;
    department_id?: string;
    status?: string;
  },
) {
  const params = new URLSearchParams();

  Object.entries(filters).forEach(([key, value]) => {
    if (value) {
      params.set(key, value);
    }
  });

  const response = await fetch(`${API_BASE_URL}/files?${params.toString()}`, {
    headers: authHeaders(token),
    cache: "no-store",
  });

  return parseResponse<PaginatedResponse<PhysicalFile>>(response);
}

export async function getFile(token: string, id: string) {
  const response = await fetch(`${API_BASE_URL}/files/${id}`, {
    headers: authHeaders(token),
    cache: "no-store",
  });

  return parseResponse<{ data: PhysicalFile }>(response);
}

export async function getAuditLogs(
  token: string,
  filters: {
    actor_user_id?: string;
    action?: string;
    entity_type?: string;
    entity_id?: string;
    from?: string;
    to?: string;
  } = {},
) {
  const params = new URLSearchParams();

  Object.entries(filters).forEach(([key, value]) => {
    if (value) {
      params.set(key, value);
    }
  });

  const response = await fetch(`${API_BASE_URL}/audit-logs?${params.toString()}`, {
    headers: authHeaders(token),
    cache: "no-store",
  });

  return parseResponse<PaginatedResponse<AuditLog>>(response);
}

export async function getFileAuditLogs(token: string, fileId: string) {
  const response = await fetch(`${API_BASE_URL}/files/${fileId}/audit-logs`, {
    headers: authHeaders(token),
    cache: "no-store",
  });

  return parseResponse<PaginatedResponse<AuditLog>>(response);
}

export async function getNotifications(
  token: string,
  filters: { unread?: boolean; per_page?: number } = {},
) {
  const params = new URLSearchParams();

  if (filters.unread !== undefined) {
    params.set("unread", filters.unread ? "1" : "0");
  }
  if (filters.per_page !== undefined) {
    params.set("per_page", String(filters.per_page));
  }

  const response = await fetch(`${API_BASE_URL}/notifications?${params.toString()}`, {
    headers: authHeaders(token),
    cache: "no-store",
  });

  return parseResponse<PaginatedResponse<Notification>>(response);
}

export async function getNotification(token: string, id: number) {
  const response = await fetch(`${API_BASE_URL}/notifications/${id}`, {
    headers: authHeaders(token),
    cache: "no-store",
  });

  return parseResponse<{ data: Notification }>(response);
}

export async function markNotificationAsRead(token: string, id: number) {
  const response = await fetch(`${API_BASE_URL}/notifications/${id}/read`, {
    method: "PATCH",
    headers: authHeaders(token),
  });

  return parseResponse<{ data: Notification }>(response);
}

export async function markAllNotificationsAsRead(token: string) {
  const response = await fetch(`${API_BASE_URL}/notifications/read-all`, {
    method: "POST",
    headers: authHeaders(token),
  });

  return parseResponse<{ data: { updated: number } }>(response);
}

export async function getTransfersForFile(token: string, fileId: string) {
  const response = await fetch(`${API_BASE_URL}/files/${fileId}/transfers`, {
    headers: authHeaders(token),
    cache: "no-store",
  });

  return parseResponse<{ data: Transfer[] }>(response);
}

export async function getTransfers(
  token: string,
  filters: {
    status?: string;
    overdue?: boolean;
    search?: string;
    per_page?: number;
    page?: number;
  } = {},
) {
  const params = new URLSearchParams();

  if (filters.status) {
    params.set("status", filters.status);
  }
  if (filters.overdue !== undefined) {
    params.set("overdue", filters.overdue ? "1" : "0");
  }
  if (filters.search) {
    params.set("search", filters.search);
  }
  if (filters.per_page !== undefined) {
    params.set("per_page", String(filters.per_page));
  }
  if (filters.page !== undefined) {
    params.set("page", String(filters.page));
  }

  const response = await fetch(`${API_BASE_URL}/transfers?${params.toString()}`, {
    headers: authHeaders(token),
    cache: "no-store",
  });

  return parseResponse<PaginatedResponse<Transfer>>(response);
}

export async function getOverdueTransfers(
  token: string,
  filters: { per_page?: number } = {},
) {
  const params = new URLSearchParams();

  if (filters.per_page !== undefined) {
    params.set("per_page", String(filters.per_page));
  }

  const response = await fetch(
    `${API_BASE_URL}/transfers/overdue?${params.toString()}`,
    {
      headers: authHeaders(token),
      cache: "no-store",
    },
  );

  return parseResponse<PaginatedResponse<Transfer>>(response);
}

export async function getTransfer(token: string, id: string) {
  const response = await fetch(`${API_BASE_URL}/transfers/${id}`, {
    headers: authHeaders(token),
    cache: "no-store",
  });

  return parseResponse<{ data: Transfer }>(response);
}

export async function createTransfer(
  token: string,
  payload: {
    file_id: string;
    to_department_id: string;
    to_holder_user_id: string;
    due_at?: string;
  },
) {
  const response = await fetch(`${API_BASE_URL}/transfers`, {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });

  return parseResponse<{ data: Transfer }>(response);
}

export async function acknowledgeTransfer(token: string, id: string) {
  const response = await fetch(`${API_BASE_URL}/transfers/${id}/acknowledge`, {
    method: "POST",
    headers: authHeaders(token),
  });

  return parseResponse<{ data: Transfer }>(response);
}

export async function rejectTransfer(token: string, id: string) {
  const response = await fetch(`${API_BASE_URL}/transfers/${id}/reject`, {
    method: "POST",
    headers: authHeaders(token),
  });

  return parseResponse<{ data: Transfer }>(response);
}

export async function getFileIssues(
  token: string,
  fileId: string,
  filters: { per_page?: number } = {},
) {
  const params = new URLSearchParams();

  if (filters.per_page !== undefined) {
    params.set("per_page", String(filters.per_page));
  }

  const response = await fetch(
    `${API_BASE_URL}/files/${fileId}/issues?${params.toString()}`,
    {
      headers: authHeaders(token),
      cache: "no-store",
    },
  );

  return parseResponse<PaginatedResponse<FileIssue>>(response);
}

export async function getIssues(
  token: string,
  filters: {
    status?: string;
    search?: string;
    per_page?: number;
    page?: number;
  } = {},
) {
  const params = new URLSearchParams();

  if (filters.status) {
    params.set("status", filters.status);
  }
  if (filters.search) {
    params.set("search", filters.search);
  }
  if (filters.per_page !== undefined) {
    params.set("per_page", String(filters.per_page));
  }
  if (filters.page !== undefined) {
    params.set("page", String(filters.page));
  }

  const response = await fetch(`${API_BASE_URL}/issues?${params.toString()}`, {
    headers: authHeaders(token),
    cache: "no-store",
  });

  return parseResponse<PaginatedResponse<FileIssue>>(response);
}

export async function getIssue(token: string, id: string) {
  const response = await fetch(`${API_BASE_URL}/issues/${id}`, {
    headers: authHeaders(token),
    cache: "no-store",
  });

  return parseResponse<{ data: FileIssue }>(response);
}

export async function createIssue(
  token: string,
  fileId: string,
  payload: { issue_type: string; description: string },
) {
  const response = await fetch(`${API_BASE_URL}/files/${fileId}/issues`, {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });

  return parseResponse<{ data: FileIssue }>(response);
}

export async function updateIssueStatus(
  token: string,
  id: string,
  status: string,
) {
  const response = await fetch(`${API_BASE_URL}/issues/${id}`, {
    method: "PATCH",
    headers: authHeaders(token),
    body: JSON.stringify({ status }),
  });

  return parseResponse<{ data: FileIssue }>(response);
}

export type DashboardStats = {
  totalFiles: number;
  overdueTransfers: number;
  unreadNotifications: number;
};

export async function getDashboardStats(token: string): Promise<DashboardStats> {
  const [filesResponse, overdueResponse, notificationsResponse] =
    await Promise.all([
      getFiles(token, {}),
      getOverdueTransfers(token, { per_page: 1 }),
      getNotifications(token, { unread: true, per_page: 1 }),
    ]);

  return {
    totalFiles: filesResponse.meta.total,
    overdueTransfers: overdueResponse.meta.total,
    unreadNotifications: notificationsResponse.meta.total,
  };
}

export async function createFile(
  token: string,
  payload: {
    file_number: string;
    title: string;
    description?: string;
    category_id?: string;
    confirmed_department_id?: string;
    confirmed_holder_user_id?: string;
  },
) {
  const normalizedPayload = Object.fromEntries(
    Object.entries(payload).filter(([, value]) => value !== undefined && value !== ""),
  );

  const response = await fetch(`${API_BASE_URL}/files`, {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify(normalizedPayload),
  });

  return parseResponse<{ data: PhysicalFile }>(response);
}

function authHeaders(token: string): HeadersInit {
  return {
    Accept: "application/json",
    "Content-Type": "application/json",
    Authorization: `Bearer ${token}`,
  };
}

async function parseResponse<T>(response: Response): Promise<T> {
  const body = await response.json().catch(() => ({}));

  if (!response.ok) {
    const message =
      typeof body.message === "string"
        ? body.message
        : `Request failed with ${response.status}`;
    const error = new Error(message) as Error & {
      status?: number;
      errors?: ValidationErrors;
    };
    error.status = response.status;
    error.errors = body.errors;
    throw error;
  }

  return body as T;
}
