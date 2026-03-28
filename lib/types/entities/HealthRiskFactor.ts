import type { WorkerRisk } from "./WorkerRisk";

export interface HealthRiskFactor {
  id: number;
  name: string;
  code: string;
  lineNumber: number;
  workerRisks?: WorkerRisk[];
}
