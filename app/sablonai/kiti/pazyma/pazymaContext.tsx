"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { HealthCertificateRiskFactorsApi, HealthCertificateWorkerRisksApi } from "@/lib/api/healthCertificate";
import { WorkersApi } from "@/lib/api/workers";
import type { Worker } from "@/lib/types/Worker";
import type { HealthCertificateRiskFactor, HealthCertificateWorkerRisk } from "@/lib/types/healthCertificate";

type PazymaContextType = {
  riskFactors: HealthCertificateRiskFactor[];
  setRiskFactors: React.Dispatch<React.SetStateAction<HealthCertificateRiskFactor[]>>;
  workerRisks: HealthCertificateWorkerRisk[];
  setWorkerRisks: React.Dispatch<React.SetStateAction<HealthCertificateWorkerRisk[]>>;
  workers: Worker[];
  setWorkers: React.Dispatch<React.SetStateAction<Worker[]>>;
  selectedWorkerId: number | null;
  setSelectedWorkerId: React.Dispatch<React.SetStateAction<number | null>>;
  loading: boolean;
  setLoading: React.Dispatch<React.SetStateAction<boolean>>;
  reset: () => void;
  refresh: () => Promise<void>;
  refreshWorkerRisks: (workerId?: number | null) => Promise<void>;
};

const PazymaContext = createContext<PazymaContextType | null>(null);

type PazymaProviderProps = {
  children: ReactNode;
};

export function PazymaProvider({ children }: PazymaProviderProps) {
  const [riskFactors, setRiskFactors] = useState<HealthCertificateRiskFactor[]>([]);
  const [workerRisks, setWorkerRisks] = useState<HealthCertificateWorkerRisk[]>([]);
  const [workers, setWorkers] = useState<Worker[]>([]);
  const [selectedWorkerId, setSelectedWorkerId] = useState<number | null>(null);
  const [loading, setLoading] = useState<boolean>(false);

  const reset = useCallback(() => {
    setRiskFactors([]);
    setWorkerRisks([]);
    setWorkers([]);
    setSelectedWorkerId(null);
    setLoading(false);
  }, []);

  const refreshWorkerRisks = useCallback(async (workerId?: number | null) => {
    if (workerId === null || workerId === undefined) {
      setWorkerRisks(await HealthCertificateWorkerRisksApi.getAll());
      return;
    }

    setWorkerRisks(await HealthCertificateWorkerRisksApi.getByWorker(workerId));
  }, []);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const [nextRiskFactors, nextWorkers] = await Promise.all([
        HealthCertificateRiskFactorsApi.getAll(),
        WorkersApi.getAll(),
      ]);

      setRiskFactors(nextRiskFactors);
      setWorkers(nextWorkers);

      const resolvedWorkerId =
        nextWorkers.length === 0
          ? null
          : selectedWorkerId !== null && nextWorkers.some((worker) => worker.id === selectedWorkerId)
            ? selectedWorkerId
            : nextWorkers[0].id;

      setSelectedWorkerId(resolvedWorkerId);

      if (resolvedWorkerId === null) {
        setWorkerRisks(await HealthCertificateWorkerRisksApi.getAll());
      } else {
        setWorkerRisks(await HealthCertificateWorkerRisksApi.getByWorker(resolvedWorkerId));
      }
    } finally {
      setLoading(false);
    }
  }, [selectedWorkerId]);

  useEffect(() => {
    refresh();
  }, [refresh]);

  useEffect(() => {
    if (selectedWorkerId === null) {
      return;
    }

    refreshWorkerRisks(selectedWorkerId);
  }, [selectedWorkerId, refreshWorkerRisks]);

  const value = useMemo<PazymaContextType>(
    () => ({
      riskFactors,
      setRiskFactors,
      workerRisks,
      setWorkerRisks,
      workers,
      setWorkers,
      selectedWorkerId,
      setSelectedWorkerId,
      loading,
      setLoading,
      reset,
      refresh,
      refreshWorkerRisks,
    }),
    [
      riskFactors,
      workerRisks,
      workers,
      selectedWorkerId,
      loading,
      reset,
      refresh,
      refreshWorkerRisks,
    ]
  );

  return <PazymaContext.Provider value={value}>{children}</PazymaContext.Provider>;
}

export function usePazyma() {
  const context = useContext(PazymaContext);

  if (!context) {
    throw new Error("usePazyma must be used inside PazymaProvider");
  }

  return context;
}
