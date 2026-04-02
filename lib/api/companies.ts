import { api } from "./api";
import { CompanyStore, useCompanyStore } from "../globalVariables/companies";
import type { Company, CompanyCategory, CompanyTypeRow } from "../types/Company";
import type { ApiStatus } from "@/lib/types/Api";

export const CompanyApi = {

    getAll: async () => {
        if (useCompanyStore.getState().wasSet) return useCompanyStore.getState().companies;
        const res = await api.get<Company[]>("/api/company/all", { loadingMessage: "Kraunamos įmonės...", });
        CompanyStore.set(res);
        return res;
    },

    getAllDeleted: async () => {
        const res = await api.get<Company[]>("/api/company/all/deleted", { loadingMessage: "Kraunamos ištrintos įmonės...", });
        return res;
    },

    getById: async (id: number) => {
        const existing = useCompanyStore.getState().companies.find((cmp) => cmp.id === id);
        if (existing) return existing;
        const res = await api.get<Company>(`/api/company/${id}`)
        CompanyStore.push(res);
        return res;
    },

    companyCreate: async (company: Company, errorMessage?: string, errorTitle?: string) => {
        const res = await api.post<Company>("/api/company/create", company);
        CompanyStore.push(res);
        return res;
    },

    companyUpdate: async (id: number, company: Partial<Company>) => {
        const res = await api.post<ApiStatus<Company>>(`/api/company/update/${id}`, company);
        if (res.status === "SUCCESS" && res.data) {
            CompanyStore.update(id, res.data);
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