"use client";

import { HealthCertificateWorkerRisksApi } from "@/lib/api/healthCertificate";
import type { HealthCertificateWorkerRisk } from "@/lib/types/healthCertificate";
import type { Dispatch, SetStateAction } from "react";

type UpdateWorkerRiskArgs = {
  workerRiskId: number;
  workerId: number;
  riskFactorId: number;
  setWorkerRisks: Dispatch<SetStateAction<HealthCertificateWorkerRisk[]>>;
};

export async function updateWorkerRisk({
  workerRiskId,
  workerId,
  riskFactorId,
  setWorkerRisks,
}: UpdateWorkerRiskArgs) {
  const updatedWorkerRisk = await HealthCertificateWorkerRisksApi.update(workerRiskId, {
    workerId,
    riskFactorId,
  });

  setWorkerRisks((prev) =>
    prev.map((workerRisk) => (workerRisk.id === workerRiskId ? updatedWorkerRisk : workerRisk))
  );
  return updatedWorkerRisk;
}
