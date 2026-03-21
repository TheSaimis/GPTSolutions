// api.ts
import { MessageStore } from "@/lib/globalVariables/messages";
import { useLoadingStore } from "../globalVariables/isLoading";

const BASE = process.env.NEXT_PUBLIC_BACKEND_API_URL!;
if (!BASE) {
  throw new Error("NEXT_PUBLIC_BACKEND_API_URL is not defined");
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

  useLoadingStore.getState().setLoading(true, loadingMessage ?? "Kraunama...");

  try {
    const res = await fetch(`${BASE}${path}`, {
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
          details = typeof j === "string" ? j : JSON.stringify(j);
        } else {
          details = await res.text();
        }
      } catch {
        details = res.statusText;
      }

      if (res.status === 401 || res.status === 403) {
        // window.location.href = "/prisijungimas";
      }

      MessageStore.push({
        title: errorTitle || "Klaida",
        message: errorMessage || details || `HTTP ${res.status} || Įvyko klaida`,
        backgroundColor: "#e53e3e",
      });

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