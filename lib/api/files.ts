import { api, DownloadResult } from "./api";
import { CreateFileResponse, CreateFilesResponse, TemplateList } from "../types/TemplateList";
import { setCachedWordFile, getCachedWordFile } from "../cache/wordFileCache";

/** Matches POST /api/files/change-directory (move file within the same baseDir). */
export type FilesChangeDirectoryResult = {
  status: "SUCCESS" | "FAIL";
  oldPath?: string;
  newPath?: string;
  error?: string;
};

export type CreateLinkResponse = {
  status: "SUCCESS" | "FAIL";
  file?: TemplateList;
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
   */
  createFiles: (files: File[], directory: string, root: string) => {
    const form = new FormData();
    for (const file of files) {
      form.append("templates[]", file);
    }
    form.append("directory", directory);
    form.append("root", root);
    const dirPrefix = directory ? `${directory.replace(/\/+$/, "")}/` : "";
    for (const file of files) {
      setCachedWordFile(`${root}/${dirPrefix}${file.name}`, file);
    }
    return api.post<CreateFilesResponse>("/api/files/create", form);
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

  createLink: (name: string, url: string, directory: string, root: string) =>
    api.post<CreateLinkResponse>("/api/files/create-link", {
      name,
      url,
      directory,
      root,
    }),

  getFileData: (root: string, path: string) => api.get<TemplateList>(`/api/files/document-data/${root}/${path}`),

  /**
   * Move a file to another folder under the same root (templates | generated | archive | deleted).
   * @param baseDir - Same as backend: templates, generated, archive, deleted
   * @param directory - Relative path to the file (e.g. category/UAB/MyDoc.docx)
   * @param newDirectory - Target folder relative to baseDir (filename unchanged)
   */
  changeDirectory: (baseDir: string, directory: string, newDirectory: string) =>
    api.post<FilesChangeDirectoryResult>("/api/files/change-directory", {
      baseDir,
      directory,
      newDirectory,
    }),

  renameFile: (directory: string, name: string, root: string) => api.post<{ status: string }>("/api/files/rename", { directory, name, root }),
  deleteFile: (directory: string, root: string) => api.post<{ status: string }>("/api/files/delete", { directory, root }),
  restoreFile: (directory: string) => api.post<{ status: string }>("/api/files/restore", { directory }),
}