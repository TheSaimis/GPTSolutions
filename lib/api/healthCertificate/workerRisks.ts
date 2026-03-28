import { api } from "../api";
import type {
  HealthCertificateWorkerRisk,
  HealthCertificateWorkerRiskCreateInput,
  HealthCertificateWorkerRiskUpdateInput,
} from "@/lib/types/healthCertificate";
import {
  clearHealthCertificateCache,
  clearHealthCertificateCacheByPrefix,
  getHealthCertificateCache,
  healthCertificateCacheKeys,
  setHealthCertificateCache,
} from "@/lib/cache/healthCertificate/cache";

const WORKER_RISK_PREFIX = "health-certificate:worker-risks:";

export const HealthCertificateWorkerRisksApi = {
  async getAll(workerId?: number): Promise<HealthCertificateWorkerRisk[]> {
    if (workerId && workerId > 0) {
      const key = healthCertificateCacheKeys.workerRisksByWorker(workerId);
      const cached = getHealthCertificateCache<HealthCertificateWorkerRisk[]>(key);
      if (cached) {
        return cached;
      }

      const data = await api.get<HealthCertificateWorkerRisk[]>(
        `/api/health-certificate/worker-risks?workerId=${workerId}`
      );
      setHealthCertificateCache(key, data);
      return data;
    }

    const key = healthCertificateCacheKeys.workerRisksAll;
    const cached = getHealthCertificateCache<HealthCertificateWorkerRisk[]>(key);
    if (cached) {
      return cached;
    }

    const data = await api.get<HealthCertificateWorkerRisk[]>(
      "/api/health-certificate/worker-risks"
    );
    setHealthCertificateCache(key, data);
    return data;
  },

  async getByWorker(workerId: number): Promise<HealthCertificateWorkerRisk[]> {
    const key = healthCertificateCacheKeys.workerRisksByWorker(workerId);
    const cached = getHealthCertificateCache<HealthCertificateWorkerRisk[]>(key);
    if (cached) {
      return cached;
    }

    const data = await api.get<HealthCertificateWorkerRisk[]>(
      `/api/health-certificate/worker-risks/worker/${workerId}`
    );
    setHealthCertificateCache(key, data);
    return data;
  },

  async getById(id: number): Promise<HealthCertificateWorkerRisk> {
    const key = healthCertificateCacheKeys.workerRiskById(id);
    const cached = getHealthCertificateCache<HealthCertificateWorkerRisk>(key);
    if (cached) {
      return cached;
    }

    const data = await api.get<HealthCertificateWorkerRisk>(
      `/api/health-certificate/worker-risks/${id}`
    );
    setHealthCertificateCache(key, data);
    return data;
  },

  async create(
    input: HealthCertificateWorkerRiskCreateInput
  ): Promise<HealthCertificateWorkerRisk> {
    const data = await api.post<HealthCertificateWorkerRisk>(
      "/api/health-certificate/worker-risks",
      input
    );
    clearHealthCertificateCacheByPrefix(WORKER_RISK_PREFIX);
    return data;
  },

  async update(
    id: number,
    input: HealthCertificateWorkerRiskUpdateInput
  ): Promise<HealthCertificateWorkerRisk> {
    const data = await api.put<HealthCertificateWorkerRisk>(
      `/api/health-certificate/worker-risks/${id}`,
      input
    );
    clearHealthCertificateCacheByPrefix(WORKER_RISK_PREFIX);
    return data;
  },

  async delete(id: number): Promise<{ message: string }> {
    const data = await api.delete<{ message: string }>(
      `/api/health-certificate/worker-risks/${id}`
    );
    clearHealthCertificateCacheByPrefix(WORKER_RISK_PREFIX);
    return data;
  },

  clearCache(key?: string): void {
    clearHealthCertificateCache(key);
  },
};
