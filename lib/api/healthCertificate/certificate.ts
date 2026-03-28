import { api } from "../api";
import type { HealthCertificateCreateInput } from "@/lib/types/healthCertificate";

export const HealthCertificateApi = {
  createDocument(input: HealthCertificateCreateInput) {
    return api.postBlob("/api/workplace-factors-certificate/create", input, {
      loadingMessage: "Kuriama pažyma...",
      fallbackFilename: "sveikatos-tikrinimo-pazyma.docx",
    });
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
};
