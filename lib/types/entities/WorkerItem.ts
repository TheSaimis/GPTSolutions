import type { Equipment } from "./Equipment";
import type { Worker } from "../Worker";

export interface WorkerItem {
  id: number;
  worker: Worker | null;
  equipment: Equipment | null;
}
