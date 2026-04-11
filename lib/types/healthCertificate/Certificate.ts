export interface HealthCertificateWorkerPeriodRow {
  workerId: number;
  checkPeriod: string;
}

/** Rows for the pažyma risk table; used in metadata and optional replay (company still from API DB). */
export interface HealthCertificateWorkerSnapshotRow {
  workerName: string;
  riskNames: string;
  riskCodes: string;
  riskWithCodes: string;
  checkPeriod: string;
  workerId?: number | null;
}

/**
 * Stored in OOXML `documentData` — only values used to fill template placeholders (replay).
 * `templateType` and `templatePath` live in separate custom properties; `companyId` comes from the API.
 */
export interface HealthCertificateDocumentDataFillPayload {
  checkPeriodsByWorkerId?: Record<number, string>;
  userContext?: {
    id?: number | null;
    firstName?: string | null;
    lastName?: string | null;
  };
  name?: string | null;
  customReplacements?: Record<string, string>;
  /** When non-empty, table text is taken from here instead of WorkerRisk DB rows. */
  workerRows?: HealthCertificateWorkerSnapshotRow[];
}

/** Same as {@link HealthCertificateDocumentDataFillPayload} (kept for existing imports). */
export type HealthCertificateDocumentDataPayload = HealthCertificateDocumentDataFillPayload;

/** Older Word files may still embed these inside `documentData` JSON; backend accepts them for replay. */
export type HealthCertificateLegacyDocumentDataExtras = {
  documentType?: string;
  companyId?: number;
  templatePath?: string;
};

export interface HealthCertificateCreateInput {
  companyId: number;
  /** Same as `templatePath`; preferred for new callers. */
  templatePath?: string;
  /**
   * Overrides default `otherTemplates/pazyma/pazyma.docx` when set. Prefer top-level `templatePath`
   * or Word custom property `templatePath` — not inside `documentData`.
   */
  template?: string;
  checkPeriods?: Record<number, string>;
  rows?: HealthCertificateWorkerPeriodRow[];
  replacements?: Record<string, string>;
  /**
   * From Word custom property `documentData` (fill-only JSON). Non-empty `workerRows` uses that table
   * text instead of WorkerRisk DB rows; company letterhead loads by request `companyId`.
   */
  documentData?: HealthCertificateDocumentDataFillPayload | string;
}
