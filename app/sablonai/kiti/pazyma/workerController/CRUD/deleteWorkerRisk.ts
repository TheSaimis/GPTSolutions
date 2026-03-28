"use client";

import { HealthCertificateWorkerRisksApi } from "@/lib/api/healthCertificate";
import type { HealthCertificateWorkerRisk } from "@/lib/types/healthCertificate";
import type { Dispatch, SetStateAction } from "react";

type DeleteWorkerRiskArgs = {
  workerRiskId: number;
  setWorkerRisks: Dispatch<SetStateAction<HealthCertificateWorkerRisk[]>>;
};

export async function deleteWorkerRisk({ workerRiskId, setWorkerRisks }: DeleteWorkerRiskArgs) {
  await HealthCertificateWorkerRisksApi.delete(workerRiskId);
  setWorkerRisks((prev) => prev.filter((workerRisk) => workerRisk.id !== workerRiskId));
}
