// api.ts
import { MessageStore } from "@/lib/globalVariables/messages";
import { TemplateList } from "../types/TemplateList";

const BASE = process.env.NEXT_PUBLIC_BACKEND_API_URL!;

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

type ResponseType = "json" | "blob";

type RequestConfig = {
  method: string;
  path: string;
  body?: Json | File | FormData;
  errorMessage?: string;
  errorTitle?: string;
  responseType?: ResponseType;
  fallbackFilename?: string;
};

async function getClientToken(): Promise<string | null> {
  return localStorage.getItem("token");
}

function filenameFromDisposition(disposition: string | null, fallback: string) {
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

async function request<T>({
  method,
  path,
  body,
  responseType = "json",
  errorMessage,
  errorTitle,
  fallbackFilename = "document.docx",
}: RequestConfig): Promise<T | DownloadResult> {

  const token = await getClientToken();
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
  if (token) headers.Authorization = `Bearer ${token}`;

  const res = await fetch(`${BASE}${path}`, {
    method,
    headers,
    body: finalBody,
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

    if (res.statusText === "Unauthorized" || res.status === 401 || res.status === 403) {
      MessageStore.push({
        title: "Klaida",
        message: `Jūs nesate prisijunge prie sistemos arba jusų prisijungimo sesija baigėsi. Prisijunkite prie sistemos norėdami testi`,
        backgroundColor: "#e53e3e",
      })
      window.location.href = "/login";
      return undefined as T;
    }

    MessageStore.push({
      title: errorTitle || "Klaida",
      message: errorMessage || details || `HTTP ${res.status} || Įvyko klaida`,
      backgroundColor: "#e53e3e",
    });

    // Throw so caller can stop (prevents "downloading" JSON)
    throw new Error(details || `HTTP ${res.status}`);
  }

  if (res.status === 204) return undefined as T;

  if (responseType === "blob") {
    const disposition = res.headers.get("content-disposition");
    const filename = filenameFromDisposition(
      disposition,
      fallbackFilename
    );

    return {
      blob: await res.blob(),
      filename,
    };

  }

  return await res.json();
}

export const api = {

  get: <T>(path: string) =>
    request<T>({
      method: "GET",
      path,
      responseType: "json",
    }),

  getBlob: (path: string): Promise<DownloadResult> =>
    request<never>({
      method: "GET",
      path,
      responseType: "blob",
    }),

  post: <T>(path: string, body?: Json | FormData) =>
    request<T>({
      method: "POST",
      path,
      body,
      responseType: "json",
    }),

  postBlob: (path: string, body?: Json | File | FormData) =>
    request({
      method: "POST",
      path,
      body,
      responseType: "blob",
    }),
};