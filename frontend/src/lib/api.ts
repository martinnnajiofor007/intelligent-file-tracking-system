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

export async function getDepartments(token: string) {
  const response = await fetch(`${API_BASE_URL}/departments`, {
    headers: authHeaders(token),
    cache: "no-store",
  });

  return parseResponse<{ data: Department[] }>(response);
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
