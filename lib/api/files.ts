import { api } from "./api";
import { CreateFileResponse } from "../types/TemplateList";

export const FilesApi = {
    downloadFile: (path: string) => api.getBlob(`/api/files/download/${path}`),
    getPDF: (root: string, path: string) => api.getBlob(`/api/files/pdf/${root}/${path}`),

    createFile: (file: File, directory: string, root: string) => {
        const form = new FormData();
        form.append("template", file);
        form.append("directory", directory);
        form.append("root", root);
        return api.post<CreateFileResponse>("/api/files/create", form);
      },

    renameFile: (directory: string, name: string, root: string) => api.post<{ status: string }>("/api/files/rename", { directory, name, root }),
    deleteFile: (directory: string, root: string) => api.post<{ status: string }>("/api/files/delete", { directory, root }),
}