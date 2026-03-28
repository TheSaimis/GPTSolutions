import { api } from "../api";
import type { HealthCertificateCreateInput } from "@/lib/types/healthCertificate";

export const HealthCertificateApi = {
  createDocument(input: HealthCertificateCreateInput) {
    return api.postBlob("/api/workplace-factors-certificate/create", input, {
      loadingMessage: "Kuriama pažyma...",
      fallbackFilename: "sveikatos-tikrinimo-pazyma.docx",
    });
  },
};
