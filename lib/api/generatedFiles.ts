import { api } from "./api";

export const GeneratedFilesApi = {
    getAll: () => api.getBlob("/api/generated/all/zip",),
}