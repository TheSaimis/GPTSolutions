import { api, type Json } from "../api";
import type {
  HealthCertificateCreateInput,
  HealthCertificateDocumentDataFillPayload,
} from "@/lib/types/healthCertificate";

/** Matches backend default; only needed for UI that still sends `template`. */
export const HEALTH_CERTIFICATE_TEMPLATE_PATH = "otherTemplates/pazyma/pazyma.docx";

export const HealthCertificateApi = {
  /**
   * POST `/api/workplace-factors-certificate/create`.
   * Pass optional `documentData` (metadata JSON string or object) to replay `workerRows` without WorkerRisk DB reads.
   */
  createDocument(input: HealthCertificateCreateInput) {
    return api.postBlob("/api/workplace-factors-certificate/create", input as unknown as Json, {
      loadingMessage: "Kuriama pažyma...",
      fallbackFilename: "sveikatos-tikrinimo-pazyma.docx",
    });
  },

  /**
   * Same as `createDocument`, but only `companyId` + `documentData` (e.g. `metadata.custom.documentData` from Word).
   */
  createDocumentFromMetadata(
    companyId: number,
    documentData: HealthCertificateDocumentDataFillPayload | string,
    extras?: Omit<
      HealthCertificateCreateInput,
      "companyId" | "documentData"
    >
  ) {
    return HealthCertificateApi.createDocument({
      companyId,
      documentData,
      ...extras,
    } as HealthCertificateCreateInput);
  },

  uploadTemplate(file: File) {
    const form = new FormData();
    form.append("template", file);
    return api.post<{ status: string; template: string }>(
      "/api/workplace-factors-certificate/template/upload",
      form,
      { loadingMessage: "Įkeliamas pažymos šablonas..." }
    );
  },

  getTemplatePdf() {
    return api.getBlob("/api/files/pdf/templates/otherTemplates/pazyma/pazyma.docx", {
      loadingMessage: "Ruošiama šablono PDF peržiūra...",
      fallbackFilename: "pazyma.pdf",
    });
  },

  downloadTemplate() {
    return api.getBlob("/api/files/download/templates/otherTemplates/pazyma/pazyma.docx", {
      loadingMessage: "Atsiunčiamas šablonas...",
      fallbackFilename: "pazyma.docx",
    });
  },
};
