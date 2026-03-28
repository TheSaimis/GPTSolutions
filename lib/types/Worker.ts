import type { RiskList } from "./AAP/Risk";
import type { CompanyWorker } from "./Company";
import type { WorkerItem } from "./entities/WorkerItem";
import type { WorkerRisk } from "./entities/WorkerRisk";

export interface Worker {
  id: number;
  name: string;
  riskLists?: RiskList[];
  companyWorkers?: CompanyWorker[];
  workerRisks?: WorkerRisk[];
  workerItems?: WorkerItem[];
}