"use client";

import { HealthCertificateWorkerRisksApi } from "@/lib/api/healthCertificate";
import type { HealthCertificateWorkerRisk } from "@/lib/types/healthCertificate";
import type { Dispatch, SetStateAction } from "react";

type CreateWorkerRiskArgs = {
  workerId: number;
  riskFactorId: number;
  setWorkerRisks: Dispatch<SetStateAction<HealthCertificateWorkerRisk[]>>;
};

export async function createWorkerRisk({
  workerId,
  riskFactorId,
  setWorkerRisks,
}: CreateWorkerRiskArgs) {
  const createdWorkerRisk = await HealthCertificateWorkerRisksApi.create({
    workerId,
    riskFactorId,
  });

  setWorkerRisks((prev) => [...prev, createdWorkerRisk]);
  return createdWorkerRisk;
}
