"use client";

import { useEffect, useMemo, useState } from "react";
import { useAAPTable } from "./AAPTableContext";
import { CompanyApi } from "@/lib/api/companies";
import { TemplateApi } from "@/lib/api/templates";
import { MessageStore } from "@/lib/globalVariables/messages";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import type { Company, CompanyWorker } from "@/lib/types/Company";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import InputFieldText from "@/components/inputFields/inputFieldText";
import { WorkersApi } from "@/lib/api/workers";
import { CompanyWorkersApi } from "@/lib/api/companyWorkers";
import styles from "./page.module.scss";

export default function WorkerTypes() {
  const { workers, loading, selectedWorkerId, setSelectedWorkerId, setWorkers } = useAAPTable();
  const [companies, setCompanies] = useState<Company[]>([]);
  const [selectedCompanyId, setSelectedCompanyId] = useState<number | null>(null);
  const [creatingDocument, setCreatingDocument] = useState(false);
  const [newWorkerName, setNewWorkerName] = useState("");
  const [creatingWorker, setCreatingWorker] = useState(false);
  const [companyWorkers, setCompanyWorkers] = useState<CompanyWorker[]>([]);
  const [pendingWorkerIds, setPendingWorkerIds] = useState<Set<number>>(new Set());

  useEffect(() => {
    CompanyApi.getAll().then(setCompanies).catch(() => undefined);
  }, []);

  const companyOptions = useMemo(
    () =>
      companies.map((company) => ({
        value: String(company.id ?? ""),
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
    setCreatingDocument(true);
    try {
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

  return (
    <aside className={styles.workerPanel}>
      <h3 className={styles.workerTitle}>AAP dokumentas</h3>
      <div className={styles.documentSection}>
        <div className={styles.companySelect}>
          <InputFieldSelect
            options={companyOptions}
            selected={
              selectedCompanyId !== null
                ? companyOptions.find((option) => Number(option.value) === selectedCompanyId)?.label
                : ""
            }
            placeholder="Pasirinkite įmonę"
            onChange={(value) => setSelectedCompanyId(Number(value))}
          />
        </div>
        <button
          type="button"
          className={styles.createDocumentButton}
          disabled={creatingDocument || selectedCompanyId === null}
          onClick={createAAPDocument}
        >
          {creatingDocument ? "Kuriama..." : "Generuoti AAP Excel"}
        </button>
      </div>

      <div className={styles.workerDivider} />
      <h3 className={styles.workerTitle}>Darbuotojų tipai</h3>
      {selectedCompanyId === null ? (
        <p className={styles.workerMuted}>Pasirinkite įmonę, kad galėtumėte priskirti darbuotojų tipus.</p>
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
        <div className={styles.workerList}>
          {workers.map((worker) => (
            <div key={worker.id} className={styles.workerItem}>
              <button
                type="button"
                className={`${styles.workerButton} ${
                  selectedWorkerId === worker.id ? styles.workerButtonActive : ""
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
                disabled={selectedCompanyId === null || pendingWorkerIds.has(worker.id)}
                onClick={() => toggleWorkerInCompany(worker.id)}
              >
                {pendingWorkerIds.has(worker.id)
                  ? "..."
                  : companyWorkerByWorkerId.has(worker.id)
                  ? "Šalinti"
                  : "Pridėti"}
              </button>
            </div>
          ))}
        </div>
      )}
    </aside>
  );
}
