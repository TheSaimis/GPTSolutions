import { api } from "./api";
import { CompanyStore, useCompanyStore } from "../globalVariables/companies";
import type { Company } from "../types/Company";
import type { ApiStatus } from "@/lib/types/Api";

export const CompanyApi = {

    getAll: async () => {
        if (useCompanyStore.getState().wasSet) return useCompanyStore.getState().companies;
        const res = await api.get<Company[]>("/api/company/all", { loadingMessage: "Kraunamos įmonės...", });
        CompanyStore.set(res);
        return res;
    },

    /** Visada iš API — sąrašo podėlis pasensta po redagavimo. */
    getById: async (id: number) => {
        const res = await api.get<Company>(`/api/company/${id}`, {
            loadingMessage: "Kraunama įmonė...",
        });
        const inList = useCompanyStore.getState().companies.some((c) => c.id === id);
        if (inList) {
            CompanyStore.update(id, res);
        } else {
            CompanyStore.push(res);
        }
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
    }
};