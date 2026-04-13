import { api, type Json } from "../api";
import type {
  HealthCertificateCreateInput,
  HealthCertificateDocumentDataFillPayload,
} from "@/lib/types/healthCertificate";

/** Must match backend `WorkplaceFactorsCertificateController` įkelto šablono vardas. */
export const HEALTH_CERTIFICATE_TEMPLATE_BASENAME =
  "Sveikatos tikrinimo pazyma + knyga.docx";

export const HEALTH_CERTIFICATE_TEMPLATE_PATH = `AAP/${HEALTH_CERTIFICATE_TEMPLATE_BASENAME}`;

function encodeTemplatesRelPathForApi(relPath: string): string {
  return relPath.split("/").map((s) => encodeURIComponent(s)).join("/");
}

export const HealthCertificateApi = {
  /**
   * POST `/api/workplace-factors-certificate/create`.
   * Pass optional `documentData` (metadata JSON string or object) to replay `workerRows` without WorkerRisk DB reads.
   */
  createDocument(input: HealthCertificateCreateInput) {
    return api.postBlob("/api/workplace-factors-certificate/create", input as unknown as Json, {
      loadingMessage: "Kuriama pažyma...",
      fallbackFilename: "Sveikatos tikrinimo pazyma + knyga.docx",
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
    return api.getBlob(
      `/api/files/pdf/templates/${encodeTemplatesRelPathForApi(HEALTH_CERTIFICATE_TEMPLATE_PATH)}`,
      {
        loadingMessage: "Ruošiama šablono PDF peržiūra...",
        fallbackFilename: "Sveikatos tikrinimo pazyma + knyga.pdf",
      }
    );
  },

  downloadTemplate() {
    return api.getBlob(
      `/api/files/download/templates/${encodeTemplatesRelPathForApi(HEALTH_CERTIFICATE_TEMPLATE_PATH)}`,
      {
        loadingMessage: "Atsiunčiamas šablonas...",
        fallbackFilename: HEALTH_CERTIFICATE_TEMPLATE_BASENAME,
      }
    );
  },
};
