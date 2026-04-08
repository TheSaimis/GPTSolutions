"use client";

import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import { CompanyApi } from "@/lib/api/companies";
import { CompanyWorkersApi } from "@/lib/api/companyWorkers";
import { HealthCertificateApi, HealthCertificateWorkerRisksApi } from "@/lib/api/healthCertificate";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import type { Company, CompanyWorker } from "@/lib/types/Company";
import type { HealthCertificateWorkerRisk } from "@/lib/types/healthCertificate";
import { WorkersApi } from "@/lib/api/workers";
import type { Worker } from "@/lib/types/Worker";
import { useEffect, useMemo, useState } from "react";
import styles from "../controllers.module.scss";

type WorkerCertificateRow = {
  workerId: number;
  workerName: string;
  risks: { id: number; name: string; cipher: string }[];
};

export default function DocumentController() {
  const [companies, setCompanies] = useState<Company[]>([]);
  const [workers, setWorkers] = useState<Worker[]>([]);
  const [workerRisks, setWorkerRisks] = useState<HealthCertificateWorkerRisk[]>([]);
  const [companyWorkers, setCompanyWorkers] = useState<CompanyWorker[]>([]);
  const [selectedCompanyId, setSelectedCompanyId] = useState<number | null>(null);
  const [selectedWorkerIdsToAdd, setSelectedWorkerIdsToAdd] = useState<number[]>(
    []
  );
  const [checkPeriods, setCheckPeriods] = useState<Record<number, string>>({});
  const [addingWorker, setAddingWorker] = useState(false);
  const [creatingDocument, setCreatingDocument] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    async function loadData() {
      setLoading(true);
      setError(null);
      try {
        const [nextCompanies, nextWorkers, nextWorkerRisks] = await Promise.all([
          CompanyApi.getAll(),
          WorkersApi.getAll(),
          HealthCertificateWorkerRisksApi.getAll(),
        ]);
        setCompanies(nextCompanies);
        setWorkers(nextWorkers);
        setWorkerRisks(nextWorkerRisks);
      } catch {
        setError("Nepavyko užkrauti dokumento kūrimo duomenų.");
      } finally {
        setLoading(false);
      }
    }

    loadData();
  }, []);

  useEffect(() => {
    async function loadCompanyWorkers() {
      if (!selectedCompanyId) {
        setCompanyWorkers([]);
        return;
      }

      try {
        const data = await CompanyWorkersApi.getByCompanyId(selectedCompanyId);
        setCompanyWorkers(data);
      } catch {
        setCompanyWorkers([]);
      }
    }

    loadCompanyWorkers();
  }, [selectedCompanyId]);

  useEffect(() => {
    setSelectedWorkerIdsToAdd([]);
  }, [selectedCompanyId]);

  const companyOptions = useMemo(
    () =>
      companies
        .filter((company) => company.id)
        .map((company) => ({
          value: String(company.id),
          label: `${company.companyType ?? ""} ${company.companyName ?? ""}`.trim(),
        })),
    [companies]
  );

  const selectedCompanyLabel =
    selectedCompanyId === null
      ? ""
      : companyOptions.find((option) => Number(option.value) === selectedCompanyId)?.label ?? "";

  const assignedWorkerIds = useMemo(
    () => new Set(companyWorkers.map((companyWorker) => companyWorker.worker?.id).filter((id): id is number => !!id)),
    [companyWorkers]
  );

  const workerOptions = useMemo(
    () =>
      workers
        .filter((worker) => worker.id && !assignedWorkerIds.has(worker.id))
        .map((worker) => ({
          value: String(worker.id),
          label: worker.name,
        })),
    [workers, assignedWorkerIds]
  );

  const rows = useMemo<WorkerCertificateRow[]>(() => {
    const map = new Map<number, WorkerCertificateRow>(
      companyWorkers
        .filter((item) => item.worker?.id && item.worker?.name)
        .map((item) => [
          item.worker!.id,
          {
            workerId: item.worker!.id,
            workerName: item.worker!.name,
            risks: [],
          },
        ])
    );

    workerRisks.forEach((workerRisk) => {
      const workerId = workerRisk.worker?.id;
      if (!workerId) return;
      if (!map.has(workerId)) return;

      const row = map.get(workerId);
      const risk = workerRisk.riskFactor;
      if (!row || !risk?.id) return;
      if (row.risks.some((existing) => existing.id === risk.id)) return;

      row.risks.push({
        id: risk.id,
        name: risk.name ?? "",
        cipher: risk.cipher ?? "",
      });
    });

    return Array.from(map.values()).sort((a, b) => a.workerName.localeCompare(b.workerName, "lt"));
  }, [companyWorkers, workerRisks]);

  useEffect(() => {
    setCheckPeriods((prev) => {
      const next: Record<number, string> = {};
      rows.forEach((row) => {
        next[row.workerId] = prev[row.workerId] ?? "";
      });
      return next;
    });
  }, [rows]);

  const allCheckPeriodsFilled =
    rows.length > 0 && rows.every((row) => (checkPeriods[row.workerId] ?? "").trim() !== "");

  function toggleWorkerSelectForAdd(workerId: number) {
    setSelectedWorkerIdsToAdd((prev) =>
      prev.includes(workerId)
        ? prev.filter((id) => id !== workerId)
        : [...prev, workerId]
    );
  }

  async function addWorkersToCompany() {
    if (!selectedCompanyId || selectedWorkerIdsToAdd.length === 0) return;

    setAddingWorker(true);
    setError(null);
    try {
      for (const workerId of selectedWorkerIdsToAdd) {
        await CompanyWorkersApi.create({
          companyId: selectedCompanyId,
          workerId,
        });
      }
      const refreshed = await CompanyWorkersApi.getByCompanyId(selectedCompanyId);
      setCompanyWorkers(refreshed);
      setSelectedWorkerIdsToAdd([]);
    } catch {
      setError("Nepavyko pridėti darbuotojo tipo į įmonę.");
    } finally {
      setAddingWorker(false);
    }
  }

  async function createHealthRiskFactorCertificate() {
    if (!selectedCompanyId || !allCheckPeriodsFilled) return;

    setCreatingDocument(true);
    setError(null);
    try {
      const normalizedPeriods = rows.reduce<Record<number, string>>((acc, row) => {
        acc[row.workerId] = checkPeriods[row.workerId] ?? "";
        return acc;
      }, {});

      const { blob, filename } = await HealthCertificateApi.createDocument({
        companyId: selectedCompanyId,
        template: "otherTemplates/pazyma/pazyma.docx",
        checkPeriods: normalizedPeriods,
        rows: rows.map((row) => ({
          workerId: row.workerId,
          checkPeriod: normalizedPeriods[row.workerId],
        })),
      });

      downloadBlob({ blob, filename });
    } catch {
      setError("Nepavyko sukurti pažymos dokumento.");
    } finally {
      setCreatingDocument(false);
    }
  }

  return (
    <div className={styles.controller}>
      <h3 className={styles.title}>Dokumentų kūrimas</h3>
      <div className={`${styles.panel} ${styles.formRow}`}>
        <InputFieldSelect
          label="Įmonė"
          options={companyOptions}
          selected={selectedCompanyLabel}
          placeholder="Pasirinkite įmonę"
          emptyMessage="Šiuo metu nėra įmonių"
          onChange={(value) => setSelectedCompanyId(Number(value) || null)}
        />
        <button
          type="button"
          className={`${styles.button} ${styles.buttonPrimary}`}
          onClick={createHealthRiskFactorCertificate}
          disabled={creatingDocument || selectedCompanyId === null || !allCheckPeriodsFilled}
        >
          {creatingDocument ? "Kuriama..." : "Generuoti pažymą"}
        </button>
      </div>
      <div className={`${styles.panel} ${styles.workerAddPanel}`}>
        <p className={styles.workerAddTitle}>
          Pridėti darbuotojų tipus į įmonę
          <span className={styles.workerAddHint}>
            (galite pažymėti kelis vienu metu)
          </span>
        </p>
        {selectedCompanyId === null ? (
          <p className={styles.subtitle}>Pirmiausia pasirinkite įmonę.</p>
        ) : workerOptions.length === 0 ? (
          <p className={styles.subtitle}>
            Nėra laisvų darbuotojų tipų pridėjimui (visi jau priskirti).
          </p>
        ) : (
          <>
            <div className={styles.workerCheckboxList} role="group" aria-label="Darbuotojų tipai pridėjimui">
              {workerOptions.map((option) => {
                const id = Number(option.value);
                const checked = selectedWorkerIdsToAdd.includes(id);
                return (
                  <label key={option.value} className={styles.workerCheckboxRow}>
                    <input
                      type="checkbox"
                      checked={checked}
                      onChange={() => toggleWorkerSelectForAdd(id)}
                    />
                    <span>{option.label}</span>
                  </label>
                );
              })}
            </div>
            <div className={styles.workerAddActions}>
              <button
                type="button"
                className={`${styles.button} ${styles.buttonGhost}`}
                onClick={() => setSelectedWorkerIdsToAdd([])}
                disabled={
                  selectedWorkerIdsToAdd.length === 0 || addingWorker
                }
              >
                Nuimti pasirinkimą
              </button>
              <button
                type="button"
                className={`${styles.button} ${styles.buttonSecondary}`}
                onClick={addWorkersToCompany}
                disabled={
                  selectedCompanyId === null ||
                  selectedWorkerIdsToAdd.length === 0 ||
                  addingWorker
                }
              >
                {addingWorker
                  ? "Pridedama..."
                  : `Pridėti pažymėtus (${selectedWorkerIdsToAdd.length})`}
              </button>
            </div>
          </>
        )}
      </div>

      {loading ? <p className={styles.subtitle}>Kraunama...</p> : null}
      {error ? <p className={styles.error}>{error}</p> : null}

      {!loading && selectedCompanyId === null ? (
        <p className={styles.subtitle}>Pasirinkite įmonę, kad matytumėte jos darbuotojų tipus.</p>
      ) : null}

      {!loading && selectedCompanyId !== null && rows.length === 0 ? (
        <p className={styles.subtitle}>Šiai įmonei nepriskirta darbuotojų tipų.</p>
      ) : null}

      {!loading && selectedCompanyId !== null && rows.length > 0 ? (
        <div className={styles.documentRows}>
          <div className={`${styles.documentRow} ${styles.documentHeaderRow}`}>
            <div className={styles.documentHeaderCell}>Darbuotojas</div>
            <div className={styles.documentHeaderCell}>Rizikos</div>
            <div className={styles.documentHeaderCell}>Šifrai</div>
            <div className={styles.documentHeaderCell}>Tikrinimo periodiškumas</div>
          </div>
          {rows.map((row) => (
            <div key={row.workerId} className={styles.documentRow}>
              <div className={styles.documentCell}>{row.workerName}</div>
              <div className={styles.documentCell}>
                {row.risks.length > 0 ? (
                  <div className={styles.documentListCell}>
                    {row.risks.map((risk) => (
                      <div key={risk.id}>{risk.name}</div>
                    ))}
                  </div>
                ) : (
                  "-"
                )}
              </div>
              <div className={styles.documentCell}>
                {row.risks.length > 0 ? (
                  <div className={styles.documentListCell}>
                    {row.risks.map((risk) => (
                      <div key={`${risk.id}-cipher`}>{risk.cipher}</div>
                    ))}
                  </div>
                ) : (
                  "-"
                )}
              </div>
              <div className={styles.documentPeriodCell}>
                <label className={styles.periodLabel} htmlFor={`period-${row.workerId}`}>
                  Tikrinimo periodiškumas
                </label>
                <textarea
                  id={`period-${row.workerId}`}
                  className={styles.periodTextarea}
                  value={checkPeriods[row.workerId] ?? ""}
                  onChange={(event) =>
                    setCheckPeriods((prev) => ({
                      ...prev,
                      [row.workerId]: event.target.value,
                    }))
                  }
                  placeholder={"Pvz.:\n12 men\n16men\n\n4 men"}
                />
              </div>
            </div>
          ))}
        </div>
      ) : null}
    </div>
  );
}
