import { api, DownloadResult } from "./api";
import { CreateFileResponse } from "../types/TemplateList";
import { setCachedWordFile, getCachedWordFile } from "../cache/wordFileCache";

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

  createFile: (file: File, directory: string, root: string) => {
    const form = new FormData();
    form.append("template", file);
    form.append("directory", directory);
    form.append("root", root);
    setCachedWordFile( root + "/" + directory, file);
    return api.post<CreateFileResponse>("/api/files/create", form);
  },

  renameFile: (directory: string, name: string, root: string) => api.post<{ status: string }>("/api/files/rename", { directory, name, root }),
  deleteFile: (directory: string, root: string) => api.post<{ status: string }>("/api/files/delete", { directory, root }),
}