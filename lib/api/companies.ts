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

    getById: async (id: number) => {
        if (useCompanyStore.getState().companies.find((cmp) => cmp.id === id)) return useCompanyStore.getState().companies.find((cmp) => cmp.id === id);
        const res = await api.get<Company>(`/api/company/${id}`)
        CompanyStore.push(res);
        return res;
    },

    companyCreate: async (company: Company, errorMessage?: string, errorTitle?: string) => {
        const res = await api.post<ApiStatus<Company>>("/api/company/create", company);
        if (res.status === "SUCCESS" && res.data) {
            CompanyStore.push(res.data);
        }
        return res;
    }
};