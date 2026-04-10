import type { WorkerItem } from "./WorkerItem";

export interface Equipment {
  id: number;
  name: string;
  expirationDate: string;
  unitOfMeasurement?: string;
  workerItems?: WorkerItem[];
}