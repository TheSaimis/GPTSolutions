import { api } from "./api";
import type { CompanyTypeRow } from "../types/Company";
import { normalizeCompanyTypeRows } from "@/lib/api/companyApiNormalize";

export const CompanyTypeApi = {
    getAll: async (options?: { loadingMessage?: string }) => {
        const res = await api.get<CompanyTypeRow[]>("/api/company-types", {
            loadingMessage: options?.loadingMessage ?? "Kraunami įmonių tipai...",
        });
        return normalizeCompanyTypeRows(res);
    },

    getById: async (id: number) => {
        const res = await api.get<CompanyTypeRow>(`/api/company-types/${id}`);
        const row = normalizeCompanyTypeRows([res])[0];
        if (!row) throw new Error("Įmonės tipas negautas");
        return row;
    },

    update: async (
        id: number,
        body: {
            typeShort: string;
            type: string;
            typeShortEn?: string | null;
            typeShortRu?: string | null;
            typeEn?: string | null;
            typeRu?: string | null;
        }
    ) => {
        const res = await api.put<CompanyTypeRow>(`/api/company-types/${id}`, body);
        return normalizeCompanyTypeRows([res])[0] ?? (res as CompanyTypeRow);
    },

    create: async (body: {
        typeShort: string;
        type: string;
        typeShortEn?: string | null;
        typeShortRu?: string | null;
        typeEn?: string | null;
        typeRu?: string | null;
    }) => {
        const res = await api.post<CompanyTypeRow>("/api/company-types", body);
        return normalizeCompanyTypeRows([res])[0] ?? (res as CompanyTypeRow);
    },
};
