import { api } from "./api";
import type { Company } from "../types/Company";

export const CompanyApi = {

    getAll: () => api.get<Company[]>("/api/company/all"),
    companyCreate: (company: Company, errorMessage?: string, errorTitle?: string) => api.post<Company>("/api/company/create", company),

};