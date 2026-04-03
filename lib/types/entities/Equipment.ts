import type { WorkerItem } from "./WorkerItem";

export interface Equipment {
  id: number;
  name: string;
  expirationDate: string;
  workerItems?: WorkerItem[];
}