// api.ts
import { MessageStore } from "@/lib/globalVariables/messages";
import { useLoadingStore } from "../globalVariables/isLoading";

function normalizeBase(u: string): string {
  return u.trim().replace(/\/$/, "");
}

/**
 * Naršyklėje: jei NEXT_PUBLIC_BACKEND_API_URL tuščias – naudojamas santykinis /api/... (Next.js rewrite į Symfony, be CORS).
 * SSR / Node: tiesiogiai į BACKEND_INTERNAL_URL arba BACKEND_URL (rewrite čia neveikia).
 */
function resolveApiUrl(path: string): string {
  const direct = normalizeBase(process.env.NEXT_PUBLIC_BACKEND_API_URL ?? "");
  if (direct !== "") {
    return `${direct}${path}`;
  }
  if (typeof window !== "undefined") {
    return path;
  }
  const internal = normalizeBase(
    process.env.BACKEND_INTERNAL_URL ?? process.env.BACKEND_URL ?? "http://127.0.0.1:8000"
  );
  return `${internal}${path}`;
}

export type Json =
  | null
  | boolean
  | number
  | string
  | Json[]
  | { [key: string]: Json };

export type DownloadResult = {
  blob: Blob;
  filename: string;
  status?: string;
};

type BaseRequestConfig = {
  method: string;
  path: string;
  body?: Json | File | FormData;
  errorMessage?: string;
  errorTitle?: string;
  loadingMessage?: string;
  fallbackFilename?: string;
};

type JsonRequestConfig = BaseRequestConfig & {
  responseType?: "json";
};

type BlobRequestConfig = BaseRequestConfig & {
  responseType: "blob";
};

type RequestConfig = JsonRequestConfig | BlobRequestConfig;

type RequestOptions = {
  errorMessage?: string;
  errorTitle?: string;
  loadingMessage?: string;
  fallbackFilename?: string;
};

/** Aiškesnis serverio JSON klaidos tekstas (ypač fillFileBulk: results[].error). */
function formatErrorBodyFromJson(j: unknown): string {
  if (j === null || j === undefined) return "";
  if (typeof j === "string") return j;
  if (typeof j !== "object") return String(j);
  const o = j as Record<string, unknown>;
  if (typeof o.error === "string" && o.error.trim()) {
    return o.error;
  }
  if (typeof o.message === "string" && o.message.trim()) {
    return o.message;
  }
  if (typeof o.detail === "string" && o.detail.trim()) {
    return o.detail;
  }
  const results = o.results;
  if (Array.isArray(results)) {
    const lines: string[] = [];
    for (const r of results) {
      if (r && typeof r === "object") {
        const row = r as Record<string, unknown>;
        if (row.status === "FAIL" && row.error != null) {
          lines.push(`${String(row.template ?? "?")}: ${String(row.error)}`);
        }
      }
    }
    if (lines.length) return lines.join("\n");
  }
  try {
    return JSON.stringify(j);
  } catch {
    return "Nežinoma klaida";
  }
}

/** Kai Next ar proxy grąžina 404 HTML vietoj JSON – nerodyti viso puslapio klaidos lange. */
function sanitizeHttpErrorDetails(details: string, status: number): string {
  const t = details.trim();
  if (t.startsWith("<!DOCTYPE") || t.toLowerCase().startsWith("<html")) {
    return `Serveris grąžino HTML (dažniausiai neteisingas API kelias arba 404), HTTP ${status}.`;
  }
  if (t.length > 4000) {
    return `${t.slice(0, 1200)}…`;
  }
  return details;
}

function filenameFromDisposition(
  disposition: string | null,
  fallback: string
): string {
  if (!disposition) return fallback;

  const star = disposition.match(/filename\*\s*=\s*UTF-8''([^;]+)/i);
  if (star?.[1]) {
    return decodeURIComponent(star[1].replace(/["']/g, ""));
  }

  const normal = disposition.match(/filename\s*=\s*("?)([^";]+)\1/i);
  if (normal?.[2]) {
    return normal[2];
  }

  return fallback;
}

async function request<T>(config: JsonRequestConfig): Promise<T>;
async function request(config: BlobRequestConfig): Promise<DownloadResult>;

async function request<T>({
  method,
  path,
  body,
  responseType = "json",
  errorMessage,
  loadingMessage,
  errorTitle,
  fallbackFilename = "download",
}: RequestConfig): Promise<T | DownloadResult> {
  const headers: HeadersInit = {};
  let finalBody: BodyInit | undefined;

  if (body instanceof FormData) {
    finalBody = body;
  } else if (body instanceof File || body instanceof Blob) {
    const form = new FormData();
    form.append("file", body);
    finalBody = form;
  } else if (body !== undefined) {
    headers["Content-Type"] = "application/json";
    finalBody = JSON.stringify(body);
  }
  const skipAuth = path === "/api/login" && method === "POST";
  if (!skipAuth && typeof window !== "undefined") {
    const token = localStorage.getItem("token");
    if (token) {
      (headers as Record<string, string>)["Authorization"] = `Bearer ${token}`;
    }
  }

  useLoadingStore.getState().setLoading(true, loadingMessage ?? "Kraunama...");

  try {
    const res = await fetch(resolveApiUrl(path), {
      method,
      headers,
      body: finalBody,
      credentials: "include",
    });

    if (!res.ok) {
      const contentType = res.headers.get("content-type") ?? "";
      let details = "";

      try {
        if (contentType.includes("application/json")) {
          const j = await res.json();
          details =
            typeof j === "string" ? j : formatErrorBodyFromJson(j) || JSON.stringify(j);
        } else {
          details = await res.text();
        }
      } catch {
        details = res.statusText;
      }

      details = sanitizeHttpErrorDetails(details, res.status);

      const redirectToLogin =
        (res.status === 401 || res.status === 403) &&
        typeof window !== "undefined" &&
        path !== "/api/login";

      if (redirectToLogin) {
        localStorage.removeItem("token");
        window.location.href = "/prisijungimas";
      } else {
        MessageStore.push({
          title: errorTitle || "Klaida",
          message: errorMessage || details || `HTTP ${res.status} || Įvyko klaida`,
          backgroundColor: "#e53e3e",
        });
      }

      throw new Error(details || `HTTP ${res.status}`);
    }

    if (res.status === 204) {
      return undefined as T;
    }

    if (responseType === "blob") {
      const disposition = res.headers.get("content-disposition");
      const filename = filenameFromDisposition(disposition, fallbackFilename);

      return {
        blob: await res.blob(),
        filename,
      };
    }

    return (await res.json()) as T;
  } catch (e) {
    if (e instanceof TypeError && typeof window !== "undefined") {
      MessageStore.push({
        title: "Ryšio klaida",
        message:
          "Nepavyko pasiekti serverio. Jei naudojate tiesioginį API URL, patikrinkite CORS ir ar backend veikia. Vietiniam dev rekomenduojama .env palikti NEXT_PUBLIC_BACKEND_API_URL tuščią ir naudoti Next.js proxy (žr. next.config).",
        backgroundColor: "#e53e3e",
      });
    }
    throw e;
  } finally {
    useLoadingStore.getState().setLoading(false);
  }
}

export const api = {
  get: <T>(path: string, options?: RequestOptions) =>
    request<T>({
      method: "GET",
      path,
      responseType: "json",
      ...options,
    }),

  getBlob: (path: string, options?: RequestOptions) =>
    request({
      method: "GET",
      path,
      responseType: "blob",
      ...options,
    }),

  post: <T>(path: string, body?: Json | File | FormData, options?: RequestOptions) =>
    request<T>({
      method: "POST",
      path,
      body,
      responseType: "json",
      ...options,
    }),

  postBlob: (
    path: string,
    body?: Json | File | FormData,
    options?: RequestOptions
  ) =>
    request({
      method: "POST",
      path,
      body,
      responseType: "blob",
      ...options,
    }),

  put: <T>(path: string, body?: Json, options?: RequestOptions) =>
    request<T>({
      method: "PUT",
      path,
      body,
      responseType: "json",
      ...options,
    }),
};