import type { Worker } from "../Worker";
import type { HealthRiskFactor } from "./HealthRiskFactor";

export interface WorkerRisk {
  id: number;
  worker: Worker | null;
  riskFactor: HealthRiskFactor | null;
}
