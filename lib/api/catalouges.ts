import { api } from "./api";
import type { ApiStatus } from "../types/Api";

export const CatalougeApi = {

    catalougeCreate: (directory: string, folderName: string, errorMessage?: string, errorTitle?: string) => api.post<ApiStatus>("/api/catalogue/template/create", { directory, folderName }),
    catalogueRename: (oldDirectory: string, newDirectory: string, errorMessage?: string, errorTitle?: string) => api.post<ApiStatus>("/api/catalogue/template/update", { oldDirectory, newDirectory }),
    // /api/catalogue/template/update
};