import { api } from "../api";
import type {
  HealthCertificateRiskFactor,
  HealthCertificateRiskFactorCreateInput,
  HealthCertificateRiskFactorUpdateInput,
} from "@/lib/types/healthCertificate";
import {
  clearHealthCertificateCache,
  clearHealthCertificateCacheByPrefix,
  getHealthCertificateCache,
  healthCertificateCacheKeys,
  setHealthCertificateCache,
} from "@/lib/cache/healthCertificate/cache";

const RISK_FACTOR_PREFIX = "health-certificate:risk-factors:";

export const HealthCertificateRiskFactorsApi = {
  async getAll(): Promise<HealthCertificateRiskFactor[]> {
    const key = healthCertificateCacheKeys.riskFactorsAll;
    const cached = getHealthCertificateCache<HealthCertificateRiskFactor[]>(key);
    if (cached) {
      return cached;
    }

    const data = await api.get<HealthCertificateRiskFactor[]>(
      "/api/health-certificate/risk-factors"
    );
    setHealthCertificateCache(key, data);
    return data;
  },

  async getById(id: number): Promise<HealthCertificateRiskFactor> {
    const key = healthCertificateCacheKeys.riskFactorById(id);
    const cached = getHealthCertificateCache<HealthCertificateRiskFactor>(key);
    if (cached) {
      return cached;
    }

    const data = await api.get<HealthCertificateRiskFactor>(
      `/api/health-certificate/risk-factors/${id}`
    );
    setHealthCertificateCache(key, data);
    return data;
  },

  async create(
    input: HealthCertificateRiskFactorCreateInput
  ): Promise<HealthCertificateRiskFactor> {
    const data = await api.post<HealthCertificateRiskFactor>(
      "/api/health-certificate/risk-factors",
      input
    );
    clearHealthCertificateCacheByPrefix(RISK_FACTOR_PREFIX);
    return data;
  },

  async update(
    id: number,
    input: HealthCertificateRiskFactorUpdateInput
  ): Promise<HealthCertificateRiskFactor> {
    const data = await api.put<HealthCertificateRiskFactor>(
      `/api/health-certificate/risk-factors/${id}`,
      input
    );
    clearHealthCertificateCacheByPrefix(RISK_FACTOR_PREFIX);
    return data;
  },

  async delete(id: number): Promise<{ message: string }> {
    const data = await api.delete<{ message: string }>(
      `/api/health-certificate/risk-factors/${id}`
    );
    clearHealthCertificateCacheByPrefix(RISK_FACTOR_PREFIX);
    return data;
  },

  clearCache(key?: string): void {
    clearHealthCertificateCache(key);
  },
};
