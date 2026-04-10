import { api, DownloadResult } from "./api";
import {
    CreateFileResponse,
    CreateFilesResponse,
    CreateFromZipResponse,
    TemplateList,
} from "../types/TemplateList";
import { getEquipmentCache, addEquipmentToCache, removeEquipmentFromCache, clearEquipmentCache, setEquipmentCache } from "../cache/equipmentCache";
import { Equipment } from "../types/equipment/equipment";
import { WorkerItem } from "../types/entities";
import { setCachedWordFile, getCachedWordFile, clearWordFileCache } from "../cache/wordFileCache";

export const EquipmentApi = {

    getAll: () => api.get<Equipment[]>("/api/equipment"),
    getWorkerItems: () => api.get<WorkerItem[]>("/api/worker-items"),
    createEquipment: (input: Pick<Equipment, "name" | "expirationDate">) =>
        api.post<Equipment>("/api/equipment", input),
    updateEquipment: (
        id: number,
        input: Partial<Pick<Equipment, "name" | "expirationDate">>,
    ) => api.put<Equipment>(`/api/equipment/${id}`, input),
    deleteEquipment: (id: number) =>
        api.delete<{ message: string }>(`/api/equipment/${id}`),

    createWorkerItem: (input: { workerId: number; equipmentId: number }) =>
        api.post<WorkerItem>("/api/worker-items", input),
    deleteWorkerItem: (id: number) =>
        api.delete<{ message: string }>(`/api/worker-items/${id}`),

    createTemplateDocument: (companyId: number) =>
        api.postBlob("/api/equipment-template/createTemplate", { companyId }, {
            loadingMessage: "Kuriamas AAP dokumentas...",
            fallbackFilename: "aap-sarasas.docx",
        }),
    getCompanyData: (companyId: number) =>
        api.get<{
            company: {
                id: number;
                companyName?: string | null;
                code?: string | null;
                address?: string | null;
                cityOrDistrict?: string | null;
            };
            workers: Array<{
                workerId: number;
                workerName: string;
                equipment: Array<{ id: number; name: string; expirationDate: string }>;
            }>;
        }>(`/api/equipment-template/company/${companyId}/data`),

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

    createFromZip: async (file: File, directory: string, root: string): Promise<CreateFromZipResponse> => {
        const form = new FormData();
        form.append("archive", file);
        form.append("directory", directory);
        form.append("root", root);
        return api.post<CreateFromZipResponse>("/api/files/create-from-zip", form);
    },

    createFile: async (file: File, directory: string, root: string): Promise<CreateFileResponse> => {
        const res = await EquipmentApi.createFiles([file], directory, root);
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