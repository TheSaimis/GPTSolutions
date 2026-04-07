import { api } from "./api";
import { CompanyStore, useCompanyStore } from "../globalVariables/companies";
import type { Company, CompanyCategory, CompanyTypeRow } from "../types/Company";
import type { ApiStatus } from "@/lib/types/Api";
import {
    normalizeCompanyFromApi,
    withCompanyWriteAliases,
} from "@/lib/api/companyApiNormalize";

export const CompanyApi = {

    getAll: async () => {
        if (useCompanyStore.getState().wasSet) return useCompanyStore.getState().companies;
        const res = await api.get<Company[]>("/api/company/all", { loadingMessage: "Kraunamos įmonės...", });
        const list = Array.isArray(res) ? res.map((c) => normalizeCompanyFromApi(c)) : [];
        CompanyStore.set(list);
        return list;
    },

    getAllDeleted: async () => {
        const res = await api.get<Company[]>("/api/company/all/deleted", { loadingMessage: "Kraunamos ištrintos įmonės...", });
        return Array.isArray(res) ? res.map((c) => normalizeCompanyFromApi(c)) : [];
    },

    getById: async (id: number) => {
        const existing = useCompanyStore.getState().companies.find((cmp) => cmp.id === id);
        if (existing) return normalizeCompanyFromApi(existing);
        const res = await api.get<Company>(`/api/company/${id}`)
        const normalized = normalizeCompanyFromApi(res);
        CompanyStore.push(normalized);
        return normalized;
    },

    companyCreate: async (company: Company, errorMessage?: string, errorTitle?: string) => {
        const body = withCompanyWriteAliases({ ...company } as Record<string, unknown>);
        const res = await api.post<Company>("/api/company/create", body);
        const normalized = normalizeCompanyFromApi(res);
        CompanyStore.push(normalized);
        return normalized;
    },

    companyUpdate: async (id: number, company: Partial<Company>) => {
        const body = withCompanyWriteAliases({ ...company } as Record<string, unknown>);
        const res = await api.post<ApiStatus<Company>>(`/api/company/update/${id}`, body);
        if (res.status === "SUCCESS" && res.data) {
            CompanyStore.update(id, normalizeCompanyFromApi(res.data));
        }
        return res;
    },

    companyRestore: async (id: number) => {
        const res = await api.post<ApiStatus<Company>>(`/api/restore/${id}/company`);
        if (res.status === "SUCCESS") {
            CompanyStore.update(id, {
                deleted: false,
                deletedDate: undefined,
            });
        }
        return res;
    },

    companyDelete: async (id: number) => {
        const res = await api.post<ApiStatus<Company>>(`/api/delete/${id}/company`);
        if (res.status === "SUCCESS") {
            CompanyStore.update(id, {
                deleted: true,
                deletedDate: new Date().toISOString(),
            });
        }
        return res;
    },

    getCategories: async () => {
        return api.get<CompanyCategory[]>("/api/categories");
    },

    getCompanyTypes: async () => {
        return api.get<CompanyTypeRow[]>("/api/company-types");
    },

    createCategory: async (name: string) => {
        return api.post<{ status: string; data: CompanyCategory }>("/api/categories", { name });
    },
};