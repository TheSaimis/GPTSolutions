import { api, DownloadResult } from "./api";
import {
  CreateFileResponse,
  CreateFilesResponse,
  CreateFromZipResponse,
  TemplateList,
} from "../types/TemplateList";
import { setCachedWordFile, getCachedWordFile, clearWordFileCache } from "../cache/wordFileCache";

/** Matches POST /api/files/change-directory (move file within the same baseDir). */
export type FilesChangeDirectoryResult = {
  status: "SUCCESS" | "FAIL";
  oldPath?: string;
  newPath?: string;
  error?: string;
};

export const FilesApi = {



  downloadFile: async (path: string): Promise<DownloadResult> => {
    const cachedBlob = getCachedWordFile(path);
    if (cachedBlob) {
      return {
        blob: cachedBlob,
        filename: path.split("/").pop() ?? "download",
      };
    }
    const res = await api.getBlob(`/api/files/download/${path}`);
    setCachedWordFile(path, res.blob);
    return res;
  },

  getPDF: (root: string, path: string) => api.getBlob(`/api/files/pdf/${root}/${path}`),

  /**
   * Upload one or more files to the same directory in a single request.
   * Backend field: `templates[]` (or legacy `template` for a single file via {@link createFile}).
   * Clears in-memory cache for those paths first so replacements with the same filename are not stale.
   */
  createFiles: async (files: File[], directory: string, root: string): Promise<CreateFilesResponse> => {
    const form = new FormData();
    for (const file of files) {
      form.append("templates[]", file);
    }
    form.append("directory", directory);
    form.append("root", root);
    const dirPrefix = directory ? `${directory.replace(/\/+$/, "")}/` : "";
    for (const file of files) {
      clearWordFileCache(`${root}/${dirPrefix}${file.name}`);
    }
    const res = await api.post<CreateFilesResponse>("/api/files/create", form);
    for (let i = 0; i < files.length; i++) {
      const file = files[i];
      const key = `${root}/${dirPrefix}${file.name}`;
      const item = res.results[i];
      if (item?.status === "SUCCESS") {
        setCachedWordFile(key, file);
      }
    }
    return res;
  },

  /**
   * Įkelia vieną .zip archyvą: serveris palieka tik .doc/.docx/.xls/.xlsx ir užpildo OOXML metaduomenis.
   */
  createFromZip: async (file: File, directory: string, root: string): Promise<CreateFromZipResponse> => {
    const form = new FormData();
    form.append("archive", file);
    form.append("directory", directory);
    form.append("root", root);
    return api.post<CreateFromZipResponse>("/api/files/create-from-zip", form);
  },

  /** Single-file upload; uses the same endpoint as {@link createFiles} with one file. */
  createFile: async (file: File, directory: string, root: string): Promise<CreateFileResponse> => {
    const res = await FilesApi.createFiles([file], directory, root);
    const first = res.results[0];
    if (!first) {
      return { status: "FAIL", error: "No result from server" };
    }
    return {
      status: first.status,
      file: first.file,
      error: first.error,
    };
  },

  getFileData: (root: string, path: string) => api.get<TemplateList>(`/api/files/document-data/${root}/${path}`),

  /**
   * Move a file to another folder under the same root (templates | generated | archive | deleted).
   * @param baseDir - Same as backend: templates, generated, archive, deleted
   * @param directory - Relative path to the file (e.g. category/UAB/MyDoc.docx)
   * @param newDirectory - Target folder relative to baseDir (filename unchanged)
   */
  changeDirectory: async (baseDir: string, directory: string, newDirectory: string) => {
    const res = await api.post<FilesChangeDirectoryResult>("/api/files/change-directory", {
      baseDir,
      directory,
      newDirectory,
    });
    if (res.status === "SUCCESS") {
      clearWordFileCache(`${baseDir}/${directory}`);
    }
    return res;
  },

  renameFile: async (directory: string, name: string, root: string) => {
    const res = await api.post<{ status: string }>("/api/files/rename", { directory, name, root });
    if (res.status === "SUCCESS") {
      clearWordFileCache(`${root}/${directory}`);
    }
    return res;
  },
  deleteFile: async (directory: string, root: string) => {
    const res = await api.post<{ status: string }>("/api/files/delete", { directory, root });
    if (res.status === "SUCCESS") {
      clearWordFileCache(`${root}/${directory}`);
    }
    return res;
  },
  restoreFile: (directory: string) => api.post<{ status: string }>("/api/files/restore", { directory }),
}