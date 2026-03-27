import { api } from "./api";
import type { CompanyWorker } from "@/lib/types/Company";

export const CompanyWorkersApi = {
  getByCompanyId(companyId: number): Promise<CompanyWorker[]> {
    return api.get<CompanyWorker[]>(`/api/company-workers/company/${companyId}`);
  },

  create(input: { companyId: number; workerId: number }): Promise<CompanyWorker> {
    return api.post<CompanyWorker>("/api/company-workers", input);
  },

  delete(id: number): Promise<{ message: string }> {
    return api.delete<{ message: string }>(`/api/company-workers/${id}`);
  },
};
