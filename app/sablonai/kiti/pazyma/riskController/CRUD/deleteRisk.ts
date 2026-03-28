"use client";

import { HealthCertificateRiskFactorsApi } from "@/lib/api/healthCertificate";
import type { HealthCertificateRiskFactor } from "@/lib/types/healthCertificate";
import type { Dispatch, SetStateAction } from "react";

type DeleteRiskArgs = {
  riskId: number;
  setRiskFactors: Dispatch<SetStateAction<HealthCertificateRiskFactor[]>>;
};

export async function deleteRisk({ riskId, setRiskFactors }: DeleteRiskArgs) {
  await HealthCertificateRiskFactorsApi.delete(riskId);
  setRiskFactors((prev) => prev.filter((risk) => risk.id !== riskId));
}
