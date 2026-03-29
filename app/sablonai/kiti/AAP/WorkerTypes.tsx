"use client";

import { useEffect, useMemo, useState } from "react";
import { useAAPTable } from "./AAPTableContext";
import { CompanyApi } from "@/lib/api/companies";
import { TemplateApi } from "@/lib/api/templates";
import { MessageStore } from "@/lib/globalVariables/messages";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import type { Company, CompanyWorker } from "@/lib/types/Company";
import type { Worker } from "@/lib/types/Worker";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import InputFieldText from "@/components/inputFields/inputFieldText";
import InputFieldFile from "@/components/inputFields/inputFieldFile";
import DropZone from "@/components/inputFields/dropZone";
import { WorkersApi } from "@/lib/api/workers";
import { CompanyWorkersApi } from "@/lib/api/companyWorkers";
import styles from "./page.module.scss";

export default function WorkerTypes() {
  const {
    workers,
    loading,
    selectedWorkerId,
    setSelectedWorkerId,
    setWorkers,
    setRisks,
    refresh,
    pendingRiskUpdates,
  } = useAAPTable();
  const [companies, setCompanies] = useState<Company[]>([]);
  const [selectedCompanyId, setSelectedCompanyId] = useState<number | null>(null);
  const [creatingDocument, setCreatingDocument] = useState(false);
  const [newWorkerName, setNewWorkerName] = useState("");
  const [creatingWorker, setCreatingWorker] = useState(false);
  const [importFile, setImportFile] = useState<File | null>(null);
  const [importingAap, setImportingAap] = useState(false);
  const [documentOpen, setDocumentOpen] = useState(true);
  const [workersOpen, setWorkersOpen] = useState(true);
  const [companyWorkers, setCompanyWorkers] = useState<CompanyWorker[]>([]);
  const [pendingWorkerIds, setPendingWorkerIds] = useState<Set<number>>(new Set());
  const [isAdmin, setIsAdmin] = useState(false);
  const [deletingWorkerIds, setDeletingWorkerIds] = useState<Set<number>>(new Set());

  useEffect(() => {
    setIsAdmin(
      typeof window !== "undefined" && localStorage.getItem("role") === "ROLE_ADMIN"
    );
  }, []);

  useEffect(() => {
    CompanyApi.getAll().then(setCompanies).catch(() => undefined);
  }, []);

  const companyOptions = useMemo(
    () =>
      companies
        .filter((company) => company.id != null && company.id !== 0)
        .map((company) => ({
          value: String(company.id),
          label: `${company.companyType ?? ""} ${company.companyName ?? ""}`.trim(),
        })),
    [companies]
  );

  useEffect(() => {
    if (selectedCompanyId === null) {
      setCompanyWorkers([]);
      return;
    }

    CompanyWorkersApi.getByCompanyId(selectedCompanyId)
      .then(setCompanyWorkers)
      .catch(() => setCompanyWorkers([]));
  }, [selectedCompanyId]);

  useEffect(() => {
    if (!importFile) return;

    async function runImport() {
      setImportingAap(true);
      try {
        await TemplateApi.importAapXlsToDb(importFile);
        await refresh();
        MessageStore.push({
          title: "Sėkmingai",
          message: "AAP Excel duomenys įkelti į duomenų bazę.",
          backgroundColor: "#22C55E",
        });
      } finally {
        setImportingAap(false);
        setImportFile(null);
      }
    }

    runImport().catch(() => undefined);
  }, [importFile, refresh]);

  const companyWorkerByWorkerId = useMemo(() => {
    const map = new Map<number, CompanyWorker>();
    companyWorkers.forEach((item) => {
      const workerId = item.worker?.id;
      if (workerId) {
        map.set(workerId, item);
      }
    });
    return map;
  }, [companyWorkers]);

  async function createAAPDocument() {
    if (!selectedCompanyId) return;
    if (pendingRiskUpdates > 0) {
      MessageStore.push({
        title: "Palaukite",
        message: "Dar išsaugomi rizikų pakeitimai. Palaukite kelias sekundes ir bandykite dar kartą.",
        backgroundColor: "#F59E0B",
      });
      return;
    }
    setCreatingDocument(true);
    try {
      await refresh();
      const { blob, filename } = await TemplateApi.createAPPDocument(selectedCompanyId);
      downloadBlob({ blob, filename });
      MessageStore.push({
        title: "Sėkmingai",
        message: "AAP dokumentas sugeneruotas",
        backgroundColor: "#22C55E",
      });
    } finally {
      setCreatingDocument(false);
    }
  }

  async function createWorkerType() {
    const name = newWorkerName.trim();
    if (name === "") return;

    setCreatingWorker(true);
    try {
      const created = await WorkersApi.create({ name });
      setWorkers((prev) => [...prev, created]);
      setSelectedWorkerId(created.id);
      setNewWorkerName("");
      MessageStore.push({
        title: "Sėkmingai",
        message: "Darbuotojo tipas sukurtas",
        backgroundColor: "#22C55E",
      });
    } finally {
      setCreatingWorker(false);
    }
  }

  async function toggleWorkerInCompany(workerId: number) {
    if (selectedCompanyId === null) return;

    const existing = companyWorkerByWorkerId.get(workerId);
    setPendingWorkerIds((prev) => new Set(prev).add(workerId));
    try {
      if (existing?.id) {
        await CompanyWorkersApi.delete(existing.id);
        setCompanyWorkers((prev) => prev.filter((item) => item.id !== existing.id));
      } else {
        const created = await CompanyWorkersApi.create({ companyId: selectedCompanyId, workerId });
        setCompanyWorkers((prev) => [...prev, created]);
      }
    } finally {
      setPendingWorkerIds((prev) => {
        const next = new Set(prev);
        next.delete(workerId);
        return next;
      });
    }
  }

  async function deleteWorkerType(workerId: number, workerName: string) {
    if (!isAdmin) return;

    const ok = window.confirm(
      `Ar tikrai pašalinti pareigas „${workerName}“? Bus pašalinti susiję AAP įrašai ir įmonės priskyrimai.`
    );
    if (!ok) return;

    setDeletingWorkerIds((prev) => new Set(prev).add(workerId));
    try {
      await WorkersApi.delete(workerId);
      let nextWorkers: Worker[] = [];
      setWorkers((prev) => {
        nextWorkers = prev.filter((w) => w.id !== workerId);
        return nextWorkers;
      });
      setSelectedWorkerId((cur) =>
        cur === workerId ? nextWorkers[0]?.id ?? null : cur
      );
      setCompanyWorkers((prev) => prev.filter((cw) => cw.worker?.id !== workerId));
      setRisks((prev) => prev.filter((r) => r.worker?.id !== workerId));
      MessageStore.push({
        title: "Pašalinta",
        message: `Pareigos „${workerName}“ pašalintos.`,
        backgroundColor: "#22C55E",
      });
    } catch {
      MessageStore.push({
        title: "Klaida",
        message:
          "Nepavyko pašalinti pareigų. Tik administratorius gali tai daryti arba įvyko serverio klaida.",
        backgroundColor: "#DC2626",
      });
    } finally {
      setDeletingWorkerIds((prev) => {
        const next = new Set(prev);
        next.delete(workerId);
        return next;
      });
    }
  }

  const selectedCompanyLabel =
    selectedCompanyId !== null
      ? companyOptions.find((o) => Number(o.value) === selectedCompanyId)?.label ?? ""
      : "";

  return (
    <div className={styles.workerPanel} role="region" aria-label="AAP dokumentas ir darbuotojai">
      {selectedCompanyId !== null && selectedCompanyLabel ? (
        <div className={styles.selectionSummary}>
          <span className={styles.selectionSummaryLabel}>Pasirinkta įmonė</span>
          <span className={styles.selectionSummaryValue}>{selectedCompanyLabel}</span>
        </div>
      ) : (
        <p className={styles.selectionHint}>
          Pasirinkite įmonę žemiau – tada galėsite generuoti Excel ir valdyti darbuotojų tipus.
        </p>
      )}

      <div className={styles.menuGrid}>
        <section className={styles.menuSection}>
          <button
            type="button"
            className={styles.workerDropdownToggle}
            onClick={() => setDocumentOpen((prev) => !prev)}
          >
            <span>AAP dokumentas</span>
            <span className={styles.workerDropdownArrow}>
              {documentOpen ? "▴" : "▾"}
            </span>
          </button>
          <div
            className={`${styles.workerDropdownContent} ${documentOpen ? styles.workerDropdownOpen : ""}`}
          >
            <div className={styles.documentSection}>
              <div className={styles.companySelect}>
                <InputFieldSelect
                  label="Įmonė"
                  options={companyOptions}
                  selected={selectedCompanyLabel}
                  placeholder="Pasirinkite įmonę"
                  emptyMessage="Šiuo metu nėra įmonių"
                  onChange={(value) => setSelectedCompanyId(Number(value) || null)}
                />
              </div>
              <button
                type="button"
                className={styles.createDocumentButton}
                disabled={
                  creatingDocument ||
                  selectedCompanyId === null ||
                  pendingRiskUpdates > 0
                }
                onClick={createAAPDocument}
              >
                {creatingDocument ? "Kuriama..." : "Generuoti AAP Excel"}
              </button>
            </div>

            <div className={styles.importSection}>
              <h4 className={styles.importTitle}>Importuoti AAP Excel į DB</h4>
              <DropZone
                onFile={setImportFile}
                accept={[".xls", ".xlsx"]}
                className={styles.importDropZone}
                disabled={importingAap}
              >
                <div className={styles.importDropZoneInner}>
                  <p className={styles.importHint}>
                    Nutempkite `.xls` / `.xlsx` failą čia
                  </p>
                  <InputFieldFile
                    value={importFile}
                    onChange={setImportFile}
                    accept={[".xls", ".xlsx"]}
                    placeholder="Arba pasirinkite failą"
                  />
                </div>
              </DropZone>
              {importingAap ? (
                <p className={styles.workerMuted}>Importuojama...</p>
              ) : null}
            </div>
          </div>
        </section>

        <section className={styles.menuSection}>
          <button
            type="button"
            className={styles.workerDropdownToggle}
            onClick={() => setWorkersOpen((prev) => !prev)}
          >
            <span>Darbuotojų tipai</span>
            <span className={styles.workerDropdownArrow}>
              {workersOpen ? "▴" : "▾"}
            </span>
          </button>

          <div
            className={`${styles.workerDropdownContent} ${workersOpen ? styles.workerDropdownOpen : ""}`}
          >
            {selectedCompanyId === null ? (
              <p className={styles.workerMuted}>
                Pasirinkite įmonę kairėje skiltyje, kad galėtumėte priskirti
                darbuotojų tipus.
              </p>
            ) : null}

            <div className={styles.workerCreateSection}>
              <InputFieldText
                value={newWorkerName}
                onChange={setNewWorkerName}
                placeholder="Naujas darbuotojo tipas"
              />
              <button
                type="button"
                className={styles.createWorkerButton}
                onClick={createWorkerType}
                disabled={creatingWorker || newWorkerName.trim() === ""}
              >
                {creatingWorker ? "Kuriama..." : "Pridėti tipą"}
              </button>
            </div>

            {loading ? (
              <p className={styles.workerMuted}>Kraunama...</p>
            ) : workers.length === 0 ? (
              <p className={styles.workerMuted}>Nėra darbuotojų tipų.</p>
            ) : (
              <div className={styles.workerListScroll}>
                <div className={styles.workerList}>
                  {workers.map((worker) => (
                    <div
                      key={worker.id}
                      className={`${styles.workerItem} ${isAdmin ? styles.workerItemAdmin : ""}`}
                    >
                      <button
                        type="button"
                        className={`${styles.workerButton} ${
                          selectedWorkerId === worker.id
                            ? styles.workerButtonActive
                            : ""
                        }`}
                        onClick={() => setSelectedWorkerId(worker.id)}
                      >
                        {worker.name}
                      </button>
                      <button
                        type="button"
                        className={`${styles.workerAssignButton} ${
                          companyWorkerByWorkerId.has(worker.id)
                            ? styles.workerAssignButtonRemove
                            : styles.workerAssignButtonAdd
                        }`}
                        disabled={
                          selectedCompanyId === null ||
                          pendingWorkerIds.has(worker.id)
                        }
                        onClick={() => toggleWorkerInCompany(worker.id)}
                      >
                        {pendingWorkerIds.has(worker.id)
                          ? "..."
                          : companyWorkerByWorkerId.has(worker.id)
                            ? "Šalinti"
                            : "Pridėti"}
                      </button>
                      {isAdmin ? (
                        <button
                          type="button"
                          className={styles.workerDeleteTypeButton}
                          disabled={deletingWorkerIds.has(worker.id)}
                          title="Pašalinti pareigas iš sistemos (tik administratoriui)"
                          onClick={() => deleteWorkerType(worker.id, worker.name)}
                        >
                          {deletingWorkerIds.has(worker.id) ? "..." : "Ištrinti"}
                        </button>
                      ) : null}
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        </section>
      </div>
    </div>
  );
}
