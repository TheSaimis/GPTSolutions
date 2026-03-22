import { api } from "./api";
import type { ApiStatus } from "@/lib/types/Api";

export const CatalougeApi = {

    catalogueDownload: (root: string, directory: string, errorMessage?: string, errorTitle?: string) => api.getBlob(`/api/catalogue/zip/${root}/${directory}`),
    catalougeCreate: (root: string, directory: string, folderName: string, errorMessage?: string, errorTitle?: string) => api.post<ApiStatus>("/api/catalogue/create", { root, directory, folderName }),
    catalogueRename: (root: string, oldDirectory: string, newDirectory: string, errorMessage?: string, errorTitle?: string) => api.post<ApiStatus>("/api/catalogue/update", { root, oldDirectory, newDirectory }),
    catalogueDelete: (root: string, directory: string, errorMessage?: string, errorTitle?: string) => api.post<ApiStatus>("/api/catalogue/delete", { root, directory }),
    // /api/catalogue/template/update
};