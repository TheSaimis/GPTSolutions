import { api } from "./api";

export const GeneratedFilesApi = {
    getAllZip: () => api.getBlob("/api/generated/all/zip",),
    getAll: () => api.get("/api/generated",),
    getGeneratedPDF: (path: string) =>
        api.getBlob(`/api/generated/pdf/${path}`, { loadingMessage: "Kraunamas PDF..." }),
}