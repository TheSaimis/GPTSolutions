export interface HealthCertificateRiskFactor {
  id: number;
  name: string;
  code: string;
  cipher: string;
  lineNumber: number;
}

export interface HealthCertificateRiskFactorCreateInput {
  name: string;
  code?: string;
  cipher?: string;
  lineNumber?: number;
}

export interface HealthCertificateRiskFactorUpdateInput {
  name?: string;
  code?: string;
  cipher?: string;
  lineNumber?: number;
}
