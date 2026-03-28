export interface HealthCertificateWorkerRiskWorker {
  id: number;
  name: string;
}

export interface HealthCertificateWorkerRiskFactor {
  id: number;
  name: string;
  code: string;
  cipher: string;
  lineNumber: number;
}

export interface HealthCertificateWorkerRisk {
  id: number;
  worker: HealthCertificateWorkerRiskWorker | null;
  riskFactor: HealthCertificateWorkerRiskFactor | null;
}

export interface HealthCertificateWorkerRiskCreateInput {
  workerId: number;
  riskFactorId: number;
}

export interface HealthCertificateWorkerRiskUpdateInput {
  workerId?: number;
  riskFactorId?: number;
}
