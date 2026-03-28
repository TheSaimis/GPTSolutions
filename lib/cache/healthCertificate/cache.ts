const healthCertificateCache = new Map<string, unknown>();

export const healthCertificateCacheKeys = {
  riskFactorsAll: "health-certificate:risk-factors:all",
  riskFactorById: (id: number) => `health-certificate:risk-factors:id:${id}`,
  workerRisksAll: "health-certificate:worker-risks:all",
  workerRiskById: (id: number) => `health-certificate:worker-risks:id:${id}`,
  workerRisksByWorker: (workerId: number) =>
    `health-certificate:worker-risks:worker:${workerId}`,
};

export function getHealthCertificateCache<T>(key: string): T | undefined {
  return healthCertificateCache.get(key) as T | undefined;
}

export function setHealthCertificateCache<T>(key: string, value: T): void {
  healthCertificateCache.set(key, value);
}

export function clearHealthCertificateCache(key?: string): void {
  if (key) {
    healthCertificateCache.delete(key);
    return;
  }
  healthCertificateCache.clear();
}

export function clearHealthCertificateCacheByPrefix(prefix: string): void {
  for (const key of healthCertificateCache.keys()) {
    if (key.startsWith(prefix)) {
      healthCertificateCache.delete(key);
    }
  }
}
