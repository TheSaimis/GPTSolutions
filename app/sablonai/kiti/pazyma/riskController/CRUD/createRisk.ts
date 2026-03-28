"use client";

import { HealthCertificateRiskFactorsApi } from "@/lib/api/healthCertificate";
import type { HealthCertificateRiskFactor } from "@/lib/types/healthCertificate";
import type { Dispatch, SetStateAction } from "react";

type CreateRiskArgs = {
  name: string;
  cipher: string;
  setRiskFactors: Dispatch<SetStateAction<HealthCertificateRiskFactor[]>>;
};

export async function createRisk({ name, cipher, setRiskFactors }: CreateRiskArgs) {
  const createdRisk = await HealthCertificateRiskFactorsApi.create({
    name: name.trim(),
    cipher: cipher.trim(),
  });

  setRiskFactors((prev) => [...prev, createdRisk]);
  return createdRisk;
}
