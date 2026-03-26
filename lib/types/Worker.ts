import type { RiskList } from "./AAP/Risk";
import type { CompanyWorker } from "./Company";

export interface Worker {
  id: number;
  name: string;
  riskLists?: RiskList[];
  companyWorkers?: CompanyWorker[];
}