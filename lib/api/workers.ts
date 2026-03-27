import { api } from "./api";
import type { Worker } from "@/lib/types/Worker";
import {
  clearWorkerCache,
  getWorkerByIdFromCache,
  getWorkerCache,
  removeWorkerFromCache,
  setWorkerCache,
  upsertWorkerInCache,
  workerCacheKeys,
} from "@/lib/cache/workerCache";

export const WorkersApi = {
  async getAll(): Promise<Worker[]> {
    const cached = getWorkerCache<Worker[]>(workerCacheKeys.all);
    if (cached) {
      return cached;
    }

    const data = await api.get<Worker[]>("/api/workers");
    setWorkerCache(workerCacheKeys.all, data);
    return data;
  },

  async getById(id: number): Promise<Worker> {
    const cached = getWorkerByIdFromCache(id);
    if (cached) {
      return cached;
    }

    const data = await api.get<Worker>(`/api/workers/${id}`);
    upsertWorkerInCache(data);
    return data;
  },

  async create(input: Pick<Worker, "name">): Promise<Worker> {
    const data = await api.post<Worker>("/api/workers", input);
    upsertWorkerInCache(data);
    return data;
  },

  async update(id: number, input: Partial<Pick<Worker, "name">>): Promise<Worker> {
    const data = await api.put<Worker>(`/api/workers/${id}`, input);
    upsertWorkerInCache(data);
    return data;
  },

  async delete(id: number): Promise<{ message: string }> {
    const data = await api.delete<{ message: string }>(`/api/workers/${id}`);
    removeWorkerFromCache(id);
    return data;
  },

  clearCache(key?: string): void {
    clearWorkerCache(key);
  },
};

