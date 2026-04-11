import { api, DownloadResult, type Json } from "./api";
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

export type AapTemplateLocale = "lt" | "en" | "ru";

export type AapEquipmentTemplateStatusRow = {
    kind: AapEquipmentTemplateKind;
    locale: AapTemplateLocale;
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
        quantity?: number;
    }) => api.post<CompanyWorkerEquipmentRow>("/api/company-worker-equipment", input),
    patchCompanyWorkerEquipment: (id: number, input: { quantity: number }) =>
        api.patch<CompanyWorkerEquipmentRow>(`/api/company-worker-equipment/${id}`, input),
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
    addEquipmentToAapEquipmentGroup: (
        groupId: number,
        equipmentId: number,
        opts?: { quantity?: number },
    ) =>
        api.post<AapEquipmentGroupRow>(`/api/aap-equipment-groups/${groupId}/equipment`, {
            equipmentId,
            ...(opts?.quantity != null ? { quantity: opts.quantity } : {}),
        }),
    patchAapEquipmentGroupEquipment: (groupId: number, equipmentId: number, input: { quantity: number }) =>
        api.patch<AapEquipmentGroupRow>(
            `/api/aap-equipment-groups/${groupId}/equipment/${equipmentId}`,
            input,
        ),
    removeEquipmentFromAapEquipmentGroup: (groupId: number, equipmentId: number) =>
        api.delete<AapEquipmentGroupRow>(`/api/aap-equipment-groups/${groupId}/equipment/${equipmentId}`),

    createEquipment: (
        input: Pick<Equipment, "unitOfMeasurement"> & {
            name?: string;
            expirationDate?: string;
            nameEn?: string;
            expirationDateEn?: string;
            nameRu?: string;
            expirationDateRu?: string;
        },
    ) => api.post<Equipment>("/api/equipment", input),
    updateEquipment: (
        id: number,
        input: Partial<
            Pick<
                Equipment,
                | "name"
                | "expirationDate"
                | "unitOfMeasurement"
                | "nameEn"
                | "nameRu"
                | "expirationDateEn"
                | "expirationDateRu"
            >
        >,
    ) => api.put<Equipment>(`/api/equipment/${id}`, input),
    deleteEquipment: (id: number) =>
        api.delete<{ message: string }>(`/api/equipment/${id}`),

    createWorkerItem: (input: { workerId: number; equipmentId: number; quantity?: number }) =>
        api.post<WorkerItem>("/api/worker-items", input),
    patchWorkerItem: (id: number, input: { quantity?: number }) =>
        api.patch<WorkerItem>(`/api/worker-items/${id}`, input),
    deleteWorkerItem: (id: number) =>
        api.delete<{ message: string }>(`/api/worker-items/${id}`),

    createTemplateDocument: (
        companyId: number,
        outputs: ("sarasas" | "korteles")[],
        options?: { pagrindas?: string | null; language?: AapTemplateLocale },
    ) => {
        const p = options?.pagrindas;
        const lang = options?.language;
        const body: Json = {
            companyId,
            outputs,
            ...(typeof p === "string" && p.trim() !== "" ? { pagrindas: p.trim() } : {}),
            ...(lang === "en" || lang === "ru" || lang === "lt" ? { language: lang } : {}),
        };
        return api.postBlob("/api/equipment-template/createTemplate", body, {
            loadingMessage: "Kuriamas AAP dokumentas...",
            fallbackFilename:
                outputs.length > 1
                    ? "aap-dokumentai.zip"
                    : outputs[0] === "korteles"
                      ? "aap-korteles-ziniarasciai.docx"
                      : "aap-sarasas.docx",
        });
    },
    getAapTemplateStatus: () =>
        api.get<{ templates: AapEquipmentTemplateStatusRow[] }>(
            "/api/equipment-template/aap-template/status",
        ),

    uploadAapTemplate: (kind: AapEquipmentTemplateKind, file: File, locale: AapTemplateLocale = "lt") => {
        const form = new FormData();
        form.append("kind", kind);
        form.append("locale", locale);
        form.append("file", file);
        return api.post<{
            ok: boolean;
            kind: string;
            locale: string;
            originalFilename: string;
            updatedAt: string;
        }>("/api/equipment-template/aap-template", form, {
            loadingMessage: "Įkeliamas šablonas...",
        });
    },

    deleteAapTemplate: (kind: AapEquipmentTemplateKind, locale: AapTemplateLocale = "lt") =>
        api.delete<{ ok: boolean }>(
            `/api/equipment-template/aap-template/${kind}?locale=${encodeURIComponent(locale)}`,
        ),

    getAapTemplatePdf: (kind: AapEquipmentTemplateKind, locale: AapTemplateLocale = "lt") =>
        api.getBlob(`/api/equipment-template/aap-template/${kind}/pdf?locale=${encodeURIComponent(locale)}`, {
            loadingMessage: "Ruošiamas šablono PDF peržiūrai...",
            fallbackFilename:
                kind === "korteles"
                    ? `AAP_korteles_sablonas_${locale.toUpperCase()}.pdf`
                    : `AAP_sarasas_sablonas_${locale.toUpperCase()}.pdf`,
        }),

    getCompanyData: (companyId: number) =>
        api.get<{
            company: {
                id: number;
                companyName?: string | null;
                code?: string | null;
                address?: string | null;
                cityOrDistrict?: string | null;
                pagrindas?: string;
                aapKortelesPagrindas?: string | null;
            };
            workers: Array<{
                workerId: number;
                workerName: string;
                equipment: Array<{
                    id: number;
                    name: string;
                    expirationDate: string;
                    unitOfMeasurement?: string;
                    nameEn?: string | null;
                    nameRu?: string | null;
                    expirationDateEn?: string | null;
                    expirationDateRu?: string | null;
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
                    nameEn?: string | null;
                    nameRu?: string | null;
                    expirationDateEn?: string | null;
                    expirationDateRu?: string | null;
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
            return { status: "FAIL", error: "Serveris negrąžino rezultato" };
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