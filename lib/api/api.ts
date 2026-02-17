
// Make sure .env has "NEXT_PUBLIC_BACKEND_API_URL="the url of the backend""
const BASE = process.env.NEXT_PUBLIC_BACKEND_API_URL;

type Json = Record<string, unknown> | unknown[] | string | number | boolean | null;

async function getClientToken(): Promise<string | null> {
  if (typeof window === "undefined") return null;
  return localStorage.getItem("token");
}

async function request<T>(
  path: string,
  options: Omit<RequestInit, "body"> & { body?: Json }
): Promise<T> {
  if (!BASE) throw new Error("Missing NEXT_PUBLIC_BACKEND_API_URL");

  const token = await getClientToken();
  const url = `${BASE}${path.startsWith("/") ? path : `/${path}`}`;

  const res = await fetch(url, {
    ...options,
    headers: {
      ...(options.body !== undefined ? { "Content-Type": "application/json" } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers ?? {}),
    },
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
  });

  if (!res.ok) {
    const msg = await res.text().catch(() => "");
    throw new Error(msg || `API error ${res.status}`);
  }

  // Handle endpoints that return no content (common for DELETE)
  if (res.status === 204) return undefined as T;

  return (await res.json()) as T;
}

export const api = {
  get: <T>(path: string, options: Omit<RequestInit, "method" | "body"> = {}) =>
    request<T>(path, { ...options, method: "GET" }),

  post: <T>(path: string, body?: Json, options: Omit<RequestInit, "method" | "body"> = {}) =>
    request<T>(path, { ...options, method: "POST", body }),

  put: <T>(path: string, body?: Json, options: Omit<RequestInit, "method" | "body"> = {}) =>
    request<T>(path, { ...options, method: "PUT", body }),

  patch: <T>(path: string, body?: Json, options: Omit<RequestInit, "method" | "body"> = {}) =>
    request<T>(path, { ...options, method: "PATCH", body }),

  delete: <T>(path: string, options: Omit<RequestInit, "method" | "body"> = {}) =>
    request<T>(path, { ...options, method: "DELETE" }),
};