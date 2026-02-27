// api.ts
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
};

type ResponseType = "json" | "blob";

type RequestConfig = {
  method: string;
  path: string;
  body?: Json | File | FormData;
  responseType?: ResponseType;
  fallbackFilename?: string;
};

async function getClientToken(): Promise<string | null> {
  return localStorage.getItem("token");
}

function filenameFromDisposition(
  disposition: string | null,
  fallback: string
) {
  if (!disposition) return fallback;

  // filename*=UTF-8''...
  const star = disposition.match(/filename\*\s*=\s*UTF-8''([^;]+)/i);

  if (star?.[1])
    return decodeURIComponent(star[1].replace(/["']/g, ""));

  // filename="..."
  const normal = disposition.match(/filename\s*=\s*("?)([^";]+)\1/i);

  if (normal?.[2])
    return normal[2];

  return fallback;
}

async function request<T>({
  method,
  path,
  body,
  responseType = "json",
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

    throw new Error(await res.text());

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

  getBlob: (path: string) =>
    request({
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