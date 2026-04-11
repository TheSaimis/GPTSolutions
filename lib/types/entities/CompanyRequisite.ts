import type { CompanyWorker } from "../Company";

export interface CompanyRequisite {
  id: number;
  companyType: string | null;
  companyName: string | null;
  code: string | null;
  modifiedAt: string | null;
  category: string | null;
  categoryId?: number | null;
  categoryName?: string | null;
  address: string | null;
  cityOrDistrict: string | null;
  managerType: string | null;
  managerGender: string | null;
  managerFirstName: string | null;
  managerFirstNameEn: string | null;
  managerFirstNameRu: string | null;
  managerLastName: string | null;
  documentDate: string | null;
  aapKortelesPagrindas?: string | null;
  role: string | null;
  roleEn: string | null;
  roleRu: string | null;
  directory: string | null;
  createdAt: string | null;
  deleted: boolean;
  deletedDate: string | null;
  companyWorkers?: CompanyWorker[];
}
