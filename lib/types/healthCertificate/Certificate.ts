export interface HealthCertificateWorkerPeriodRow {
  workerId: number;
  checkPeriod: string;
}

export interface HealthCertificateCreateInput {
  companyId: number;
  template: string;
  checkPeriods?: Record<number, string>;
  rows?: HealthCertificateWorkerPeriodRow[];
  replacements?: Record<string, string>;
}
