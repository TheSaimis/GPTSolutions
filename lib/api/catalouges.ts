import { api } from "./api";
import type { Company } from "../types/Company";

export const CatalougeApi = {

    catalougeCreate: (directory: string, folderName: string, errorMessage?: string, errorTitle?: string) => api.post<string>("/api/catalogue/template/create", { directory, folderName }),
    catalogueRename: (oldDirectory: string, newDirectory: string, errorMessage?: string, errorTitle?: string) => api.post<string>("/api/catalogue/template/update", { oldDirectory, newDirectory }),
    // /api/catalogue/template/update
};