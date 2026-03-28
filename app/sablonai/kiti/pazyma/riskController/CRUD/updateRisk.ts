"use client";

import { HealthCertificateRiskFactorsApi } from "@/lib/api/healthCertificate";
import type { HealthCertificateRiskFactor } from "@/lib/types/healthCertificate";
import type { Dispatch, SetStateAction } from "react";

type UpdateRiskArgs = {
  riskId: number;
  name: string;
  cipher: string;
  setRiskFactors: Dispatch<SetStateAction<HealthCertificateRiskFactor[]>>;
};

export async function updateRisk({ riskId, name, cipher, setRiskFactors }: UpdateRiskArgs) {
  const updatedRisk = await HealthCertificateRiskFactorsApi.update(riskId, {
    name: name.trim(),
    cipher: cipher.trim(),
  });

  setRiskFactors((prev) => prev.map((risk) => (risk.id === riskId ? updatedRisk : risk)));
  return updatedRisk;
}
