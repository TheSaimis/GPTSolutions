import { api, DownloadResult } from "./api";
import {
    CreateFileResponse,
    CreateFilesResponse,
    CreateFromZipResponse,
    TemplateList,
} from "../types/TemplateList";
import { getEquipmentCache, addEquipmentToCache, removeEquipmentFromCache, clearEquipmentCache, setEquipmentCache } from "../cache/equipmentCache";
import { Equipment } from "../types/equipment/equipment";
import type { CompanyWorkerEquipmentRow } from "../types/companyWorkerEquipment";
import type { AapEquipmentGroupRow } from "../types/aapEquipmentGroup";
import { WorkerItem } from "../types/entities";
import { setCachedWordFile, getCachedWordFile, clearWordFileCache } from "../cache/wordFileCache";

export type AapEquipmentTemplateKind = "sarasas" | "korteles";

export type AapEquipmentTemplateStatusRow = {
    kind: AapEquipmentTemplateKind;
    source: "database" | "filesystem" | "none";
    originalFilename: string | null;
    updatedAt: string | null;
};

export const EquipmentApi = {

    getAll: () => api.get<Equipment[]>("/api/equipment"),
    getWorkerItems: () => api.get<WorkerItem[]>("/api/worker-items"),

    getCompanyWorkerEquipment: (companyId: number) =>
        api.get<CompanyWorkerEquipmentRow[]>(
            `/api/company-worker-equipment?companyId=${companyId}`,
        ),
    createCompanyWorkerEquipment: (input: {
        companyId: number;
        workerId: number;
        equipmentId: number;
    }) => api.post<CompanyWorkerEquipmentRow>("/api/company-worker-equipment", input),
    deleteCompanyWorkerEquipment: (id: number) =>
        api.delete<{ message: string }>(`/api/company-worker-equipment/${id}`),

    getAapEquipmentGroups: (companyId: number) =>
        api.get<AapEquipmentGroupRow[]>(`/api/aap-equipment-groups?companyId=${companyId}`),
    createAapEquipmentGroup: (input: { companyId: number; name: string; sortOrder?: number }) =>
        api.post<AapEquipmentGroupRow>("/api/aap-equipment-groups", input),
    updateAapEquipmentGroup: (id: number, input: { name?: string; sortOrder?: number }) =>
        api.patch<AapEquipmentGroupRow>(`/api/aap-equipment-groups/${id}`, input),
    deleteAapEquipmentGroup: (id: number) =>
        api.delete<{ message: string }>(`/api/aap-equipment-groups/${id}`),
    addWorkerToAapEquipmentGroup: (groupId: number, workerId: number) =>
        api.post<AapEquipmentGroupRow>(`/api/aap-equipment-groups/${groupId}/workers`, { workerId }),
    removeWorkerFromAapEquipmentGroup: (groupId: number, workerId: number) =>
        api.delete<AapEquipmentGroupRow>(`/api/aap-equipment-groups/${groupId}/workers/${workerId}`),
    addEquipmentToAapEquipmentGroup: (groupId: number, equipmentId: number) =>
        api.post<AapEquipmentGroupRow>(`/api/aap-equipment-groups/${groupId}/equipment`, { equipmentId }),
    removeEquipmentFromAapEquipmentGroup: (groupId: number, equipmentId: number) =>
        api.delete<AapEquipmentGroupRow>(`/api/aap-equipment-groups/${groupId}/equipment/${equipmentId}`),

    createEquipment: (input: Pick<Equipment, "name" | "expirationDate" | "unitOfMeasurement">) =>
        api.post<Equipment>("/api/equipment", input),
    updateEquipment: (
        id: number,
        input: Partial<Pick<Equipment, "name" | "expirationDate" | "unitOfMeasurement">>,
    ) => api.put<Equipment>(`/api/equipment/${id}`, input),
    deleteEquipment: (id: number) =>
        api.delete<{ message: string }>(`/api/equipment/${id}`),

    createWorkerItem: (input: { workerId: number; equipmentId: number }) =>
        api.post<WorkerItem>("/api/worker-items", input),
    deleteWorkerItem: (id: number) =>
        api.delete<{ message: string }>(`/api/worker-items/${id}`),

    createTemplateDocument: (
        companyId: number,
        outputs: ("sarasas" | "korteles")[],
    ) =>
        api.postBlob(
            "/api/equipment-template/createTemplate",
            { companyId, outputs },
            {
                loadingMessage: "Kuriamas AAP dokumentas...",
                fallbackFilename:
                    outputs.length > 1
                        ? "aap-dokumentai.zip"
                        : outputs[0] === "korteles"
                          ? "aap-korteles-ziniarasciai.docx"
                          : "aap-sarasas.docx",
            },
        ),
    getAapTemplateStatus: () =>
        api.get<{ templates: AapEquipmentTemplateStatusRow[] }>(
            "/api/equipment-template/aap-template/status",
        ),

    uploadAapTemplate: (kind: AapEquipmentTemplateKind, file: File) => {
        const form = new FormData();
        form.append("kind", kind);
        form.append("file", file);
        return api.post<{
            ok: boolean;
            kind: string;
            originalFilename: string;
            updatedAt: string;
        }>("/api/equipment-template/aap-template", form, {
            loadingMessage: "Įkeliamas šablonas...",
        });
    },

    deleteAapTemplate: (kind: AapEquipmentTemplateKind) =>
        api.delete<{ ok: boolean }>(`/api/equipment-template/aap-template/${kind}`),

    getAapTemplatePdf: (kind: AapEquipmentTemplateKind) =>
        api.getBlob(`/api/equipment-template/aap-template/${kind}/pdf`, {
            loadingMessage: "Ruošiamas šablono PDF peržiūrai...",
            fallbackFilename: kind === "korteles" ? "AAP_korteles_sablonas.pdf" : "AAP_sarasas_sablonas.pdf",
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
                equipment: Array<{
                    id: number;
                    name: string;
                    expirationDate: string;
                    unitOfMeasurement?: string;
                }>;
            }>;
            groups?: Array<{
                groupId: number;
                groupName: string;
                workers: Array<{ workerId: number; workerName: string }>;
                equipment: Array<{
                    id: number;
                    name: string;
                    expirationDate: string;
                    unitOfMeasurement?: string;
                }>;
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