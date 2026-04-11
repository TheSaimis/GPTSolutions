import type { Equipment } from "../equipment/equipment";
import type { Worker } from "../Worker";

export interface WorkerItem {
  id: number;
  quantity?: number;
  worker: Worker | null;
  equipment: Equipment | null;
}
