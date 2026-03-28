"use client";

import { useEffect, useMemo, useState } from "react";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import InputFieldText from "@/components/inputFields/inputFieldText";
import { CompanyApi } from "@/lib/api/companies";
import { CompanyWorkersApi } from "@/lib/api/companyWorkers";
import { WorkersApi } from "@/lib/api/workers";
import {
  HealthCertificateApi,
  HealthCertificateRiskFactorsApi,
  HealthCertificateWorkerRisksApi,
} from "@/lib/api/healthCertificate";
import type { Company, CompanyWorker } from "@/lib/types/Company";
import type { Worker } from "@/lib/types/Worker";
import type {
  HealthCertificateRiskFactor,
  HealthCertificateWorkerRisk,
} from "@/lib/types/healthCertificate";
import { MessageStore } from "@/lib/globalVariables/messages";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import styles from "./page.module.scss";

export default function Page() {
  const [loading, setLoading] = useState(true);

  const [companies, setCompanies] = useState<Company[]>([]);
  const [workers, setWorkers] = useState<Worker[]>([]);
  const [riskFactors, setRiskFactors] = useState<HealthCertificateRiskFactor[]>([]);
  const [workerRisks, setWorkerRisks] = useState<HealthCertificateWorkerRisk[]>([]);

  const [selectedCompanyId, setSelectedCompanyId] = useState<number | null>(null);
  const [companyWorkers, setCompanyWorkers] = useState<CompanyWorker[]>([]);
  const [checkPeriodsByWorkerId, setCheckPeriodsByWorkerId] = useState<Record<number, string>>({});

  const [templatePath, setTemplatePath] = useState("");
  const [creatingDocument, setCreatingDocument] = useState(false);

  const [newFactorName, setNewFactorName] = useState("");
  const [newFactorCode, setNewFactorCode] = useState("");
  const [newFactorLineNumber, setNewFactorLineNumber] = useState("0");
  const [savingFactor, setSavingFactor] = useState(false);

  const [newWorkerRiskWorkerId, setNewWorkerRiskWorkerId] = useState<number | null>(null);
  const [newWorkerRiskFactorId, setNewWorkerRiskFactorId] = useState<number | null>(null);
  const [savingWorkerRisk, setSavingWorkerRisk] = useState(false);

  const [factorDrafts, setFactorDrafts] = useState<
    Record<number, { name: string; code: string; lineNumber: string }>
  >({});
  const [workerRiskDrafts, setWorkerRiskDrafts] = useState<
    Record<number, { workerId: number | null; riskFactorId: number | null }>
  >({});

  useEffect(() => {
    document.title = "Sveikatos tikrinimo pažymos";
    void loadInitial();
  }, []);

  async function loadInitial() {
    setLoading(true);
    try {
      const [companiesData, workersData, factorsData, workerRisksData] = await Promise.all([
        CompanyApi.getAll(),
        WorkersApi.getAll(),
        HealthCertificateRiskFactorsApi.getAll(),
        HealthCertificateWorkerRisksApi.getAll(),
      ]);

      setCompanies(companiesData);
      setWorkers(workersData);
      setRiskFactors(factorsData);
      setWorkerRisks(workerRisksData);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (selectedCompanyId === null) {
      setCompanyWorkers([]);
      setCheckPeriodsByWorkerId({});
      return;
    }

    CompanyWorkersApi.getByCompanyId(selectedCompanyId)
      .then((data) => {
        setCompanyWorkers(data);
        setCheckPeriodsByWorkerId((prev) => {
          const next: Record<number, string> = {};
          data.forEach((item) => {
            const workerId = item.worker?.id;
            if (workerId) {
              next[workerId] = prev[workerId] ?? "";
            }
          });
          return next;
        });
      })
      .catch(() => {
        setCompanyWorkers([]);
        setCheckPeriodsByWorkerId({});
      });
  }, [selectedCompanyId]);

  const companyOptions = useMemo(
    () =>
      companies.map((company) => ({
        value: String(company.id ?? ""),
        label: `${company.companyType ?? ""} ${company.companyName ?? ""}`.trim(),
      })),
    [companies]
  );

  const workerOptions = useMemo(
    () =>
      workers.map((worker) => ({
        value: String(worker.id),
        label: worker.name,
      })),
    [workers]
  );

  const riskFactorOptions = useMemo(
    () =>
      riskFactors.map((factor) => ({
        value: String(factor.id),
        label: `${factor.name} (${factor.code})`,
      })),
    [riskFactors]
  );

  const companyWorkerTypes = useMemo(() => {
    const unique = new Map<number, Worker>();
    companyWorkers.forEach((item) => {
      const worker = item.worker;
      if (worker && worker.id) {
        unique.set(worker.id, worker);
      }
    });
    return Array.from(unique.values()).sort((a, b) => a.name.localeCompare(b.name));
  }, [companyWorkers]);

  function ensureFactorDraft(item: HealthCertificateRiskFactor) {
    setFactorDrafts((prev) => {
      if (prev[item.id]) return prev;
      return {
        ...prev,
        [item.id]: {
          name: item.name,
          code: item.code,
          lineNumber: String(item.lineNumber ?? 0),
        },
      };
    });
  }

  function ensureWorkerRiskDraft(item: HealthCertificateWorkerRisk) {
    setWorkerRiskDrafts((prev) => {
      if (prev[item.id]) return prev;
      return {
        ...prev,
        [item.id]: {
          workerId: item.worker?.id ?? null,
          riskFactorId: item.riskFactor?.id ?? null,
        },
      };
    });
  }

  async function reloadRiskFactors() {
    const data = await HealthCertificateRiskFactorsApi.getAll();
    setRiskFactors(data);
    setFactorDrafts({});
  }

  async function reloadWorkerRisks() {
    const data = await HealthCertificateWorkerRisksApi.getAll();
    setWorkerRisks(data);
    setWorkerRiskDrafts({});
  }

  async function createRiskFactor() {
    const name = newFactorName.trim();
    const code = newFactorCode.trim();
    if (name === "" || code === "") return;

    setSavingFactor(true);
    try {
      await HealthCertificateRiskFactorsApi.create({
        name,
        code,
        lineNumber: Number(newFactorLineNumber || 0),
      });
      setNewFactorName("");
      setNewFactorCode("");
      setNewFactorLineNumber("0");
      await reloadRiskFactors();
      MessageStore.push({
        title: "Sėkmingai",
        message: "Rizikos veiksnys sukurtas",
        backgroundColor: "#22C55E",
      });
    } finally {
      setSavingFactor(false);
    }
  }

  async function updateRiskFactor(id: number) {
    const draft = factorDrafts[id];
    if (!draft) return;

    await HealthCertificateRiskFactorsApi.update(id, {
      name: draft.name.trim(),
      code: draft.code.trim(),
      lineNumber: Number(draft.lineNumber || 0),
    });
    await reloadRiskFactors();
    MessageStore.push({
      title: "Sėkmingai",
      message: "Rizikos veiksnys atnaujintas",
      backgroundColor: "#22C55E",
    });
  }

  async function deleteRiskFactor(id: number) {
    await HealthCertificateRiskFactorsApi.delete(id);
    await reloadRiskFactors();
    await reloadWorkerRisks();
    MessageStore.push({
      title: "Sėkmingai",
      message: "Rizikos veiksnys pašalintas",
      backgroundColor: "#22C55E",
    });
  }

  async function createWorkerRisk() {
    if (!newWorkerRiskWorkerId || !newWorkerRiskFactorId) return;
    setSavingWorkerRisk(true);
    try {
      await HealthCertificateWorkerRisksApi.create({
        workerId: newWorkerRiskWorkerId,
        riskFactorId: newWorkerRiskFactorId,
      });
      await reloadWorkerRisks();
      MessageStore.push({
        title: "Sėkmingai",
        message: "Ryšys priskirtas",
        backgroundColor: "#22C55E",
      });
    } finally {
      setSavingWorkerRisk(false);
    }
  }

  async function updateWorkerRisk(id: number) {
    const draft = workerRiskDrafts[id];
    if (!draft?.workerId || !draft?.riskFactorId) return;

    await HealthCertificateWorkerRisksApi.update(id, {
      workerId: draft.workerId,
      riskFactorId: draft.riskFactorId,
    });
    await reloadWorkerRisks();
    MessageStore.push({
      title: "Sėkmingai",
      message: "Ryšys atnaujintas",
      backgroundColor: "#22C55E",
    });
  }

  async function deleteWorkerRisk(id: number) {
    await HealthCertificateWorkerRisksApi.delete(id);
    await reloadWorkerRisks();
    MessageStore.push({
      title: "Sėkmingai",
      message: "Ryšys pašalintas",
      backgroundColor: "#22C55E",
    });
  }

  async function createCertificateDocument() {
    if (!selectedCompanyId) {
      MessageStore.push({
        title: "Klaida",
        message: "Pasirinkite įmonę",
        backgroundColor: "#e53e3e",
      });
      return;
    }
    if (templatePath.trim() === "") {
      MessageStore.push({
        title: "Klaida",
        message: "Įveskite šablono kelią",
        backgroundColor: "#e53e3e",
      });
      return;
    }

    if (companyWorkerTypes.length === 0) {
      MessageStore.push({
        title: "Klaida",
        message: "Pasirinktai įmonei nėra priskirtų darbuotojų tipų",
        backgroundColor: "#e53e3e",
      });
      return;
    }

    const missing = companyWorkerTypes.filter((worker) => {
      const period = (checkPeriodsByWorkerId[worker.id] ?? "").trim();
      return period === "";
    });
    if (missing.length > 0) {
      MessageStore.push({
        title: "Klaida",
        message: `Trūksta tikrinimo termino: ${missing.map((m) => m.name).join(", ")}`,
        backgroundColor: "#e53e3e",
      });
      return;
    }

    const checkPeriods: Record<number, string> = {};
    companyWorkerTypes.forEach((worker) => {
      checkPeriods[worker.id] = (checkPeriodsByWorkerId[worker.id] ?? "").trim();
    });

    setCreatingDocument(true);
    try {
      const { blob, filename } = await HealthCertificateApi.createDocument({
        companyId: selectedCompanyId,
        template: templatePath.trim(),
        checkPeriods,
      });
      downloadBlob({ blob, filename });
      MessageStore.push({
        title: "Sėkmingai",
        message: "Pažyma sugeneruota",
        backgroundColor: "#22C55E",
      });
    } finally {
      setCreatingDocument(false);
    }
  }

  return (
    <div className={styles.page}>
      <section className={styles.stack}>
        <div className={styles.panel}>
          <h2 className={styles.title}>Sveikatos rizikos veiksniai</h2>
          <div className={styles.formRow}>
            <InputFieldText
              value={newFactorName}
              onChange={setNewFactorName}
              placeholder="Pavadinimas"
            />
            <InputFieldText value={newFactorCode} onChange={setNewFactorCode} placeholder="Šifras" />
          </div>
          <div className={styles.formRow}>
            <InputFieldText
              value={newFactorLineNumber}
              onChange={setNewFactorLineNumber}
              placeholder="Eil. nr."
              regex={/^\d*$/}
            />
            <button
              type="button"
              className={`${styles.button} ${styles.buttonSuccess}`}
              onClick={createRiskFactor}
              disabled={savingFactor || newFactorName.trim() === "" || newFactorCode.trim() === ""}
            >
              {savingFactor ? "Kuriama..." : "Pridėti veiksnį"}
            </button>
          </div>

          <div className={styles.divider} />

          {loading ? (
            <p className={styles.muted}>Kraunama...</p>
          ) : riskFactors.length === 0 ? (
            <p className={styles.muted}>Nėra rizikos veiksnių.</p>
          ) : (
            <div className={styles.list}>
              {riskFactors.map((factor) => {
                const draft = factorDrafts[factor.id] ?? {
                  name: factor.name,
                  code: factor.code,
                  lineNumber: String(factor.lineNumber ?? 0),
                };
                return (
                  <div key={factor.id} className={styles.listRow}>
                    <div className={styles.formRow}>
                      <InputFieldText
                        value={draft.name}
                        onFocus={() => ensureFactorDraft(factor)}
                        onChange={(value) =>
                          setFactorDrafts((prev) => ({
                            ...prev,
                            [factor.id]: { ...draft, name: value },
                          }))
                        }
                        placeholder="Pavadinimas"
                      />
                      <InputFieldText
                        value={draft.code}
                        onFocus={() => ensureFactorDraft(factor)}
                        onChange={(value) =>
                          setFactorDrafts((prev) => ({
                            ...prev,
                            [factor.id]: { ...draft, code: value },
                          }))
                        }
                        placeholder="Šifras"
                      />
                    </div>
                    <div className={styles.formRow}>
                      <InputFieldText
                        value={draft.lineNumber}
                        onFocus={() => ensureFactorDraft(factor)}
                        onChange={(value) =>
                          setFactorDrafts((prev) => ({
                            ...prev,
                            [factor.id]: { ...draft, lineNumber: value },
                          }))
                        }
                        placeholder="Eil. nr."
                        regex={/^\d*$/}
                      />
                      <div className={styles.actions}>
                        <button
                          type="button"
                          className={`${styles.button} ${styles.buttonPrimary}`}
                          onClick={() => updateRiskFactor(factor.id)}
                        >
                          Išsaugoti
                        </button>
                        <button
                          type="button"
                          className={`${styles.button} ${styles.buttonDanger}`}
                          onClick={() => deleteRiskFactor(factor.id)}
                        >
                          Šalinti
                        </button>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        <div className={styles.panel}>
          <h2 className={styles.title}>Darbuotojų ir veiksnių ryšiai</h2>
          <div className={styles.formRow}>
            <InputFieldSelect
              options={workerOptions}
              selected={
                newWorkerRiskWorkerId
                  ? workerOptions.find((item) => Number(item.value) === newWorkerRiskWorkerId)?.label
                  : ""
              }
              placeholder="Darbuotojo tipas"
              onChange={(value) => setNewWorkerRiskWorkerId(Number(value))}
            />
            <InputFieldSelect
              options={riskFactorOptions}
              selected={
                newWorkerRiskFactorId
                  ? riskFactorOptions.find((item) => Number(item.value) === newWorkerRiskFactorId)?.label
                  : ""
              }
              placeholder="Rizikos veiksnys"
              onChange={(value) => setNewWorkerRiskFactorId(Number(value))}
            />
          </div>
          <button
            type="button"
            className={`${styles.button} ${styles.buttonSuccess}`}
            onClick={createWorkerRisk}
            disabled={savingWorkerRisk || !newWorkerRiskWorkerId || !newWorkerRiskFactorId}
          >
            {savingWorkerRisk ? "Priskiriama..." : "Priskirti veiksnį darbuotojui"}
          </button>

          <div className={styles.divider} />

          {loading ? (
            <p className={styles.muted}>Kraunama...</p>
          ) : workerRisks.length === 0 ? (
            <p className={styles.muted}>Nėra ryšių.</p>
          ) : (
            <div className={styles.list}>
              {workerRisks.map((item) => {
                const draft = workerRiskDrafts[item.id] ?? {
                  workerId: item.worker?.id ?? null,
                  riskFactorId: item.riskFactor?.id ?? null,
                };
                return (
                  <div key={item.id} className={styles.listRow}>
                    <div className={styles.formRow}>
                      <InputFieldSelect
                        key={`wr-worker-${item.id}-${draft.workerId ?? "none"}`}
                        options={workerOptions}
                        selected={
                          draft.workerId
                            ? workerOptions.find((w) => Number(w.value) === draft.workerId)?.label
                            : ""
                        }
                        placeholder="Darbuotojo tipas"
                        onChange={(value) => {
                          ensureWorkerRiskDraft(item);
                          setWorkerRiskDrafts((prev) => ({
                            ...prev,
                            [item.id]: { ...draft, workerId: Number(value) },
                          }));
                        }}
                      />
                      <InputFieldSelect
                        key={`wr-factor-${item.id}-${draft.riskFactorId ?? "none"}`}
                        options={riskFactorOptions}
                        selected={
                          draft.riskFactorId
                            ? riskFactorOptions.find((f) => Number(f.value) === draft.riskFactorId)?.label
                            : ""
                        }
                        placeholder="Rizikos veiksnys"
                        onChange={(value) => {
                          ensureWorkerRiskDraft(item);
                          setWorkerRiskDrafts((prev) => ({
                            ...prev,
                            [item.id]: { ...draft, riskFactorId: Number(value) },
                          }));
                        }}
                      />
                    </div>
                    <div className={styles.actions}>
                      <button
                        type="button"
                        className={`${styles.button} ${styles.buttonPrimary}`}
                        onClick={() => updateWorkerRisk(item.id)}
                        disabled={!draft.workerId || !draft.riskFactorId}
                      >
                        Išsaugoti
                      </button>
                      <button
                        type="button"
                        className={`${styles.button} ${styles.buttonDanger}`}
                        onClick={() => deleteWorkerRisk(item.id)}
                      >
                        Šalinti
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </section>

      <aside className={styles.panel}>
        <h2 className={styles.title}>Pažymos generavimas</h2>
        <div className={styles.stack}>
          <InputFieldSelect
            options={companyOptions}
            selected={
              selectedCompanyId
                ? companyOptions.find((option) => Number(option.value) === selectedCompanyId)?.label
                : ""
            }
            placeholder="Įmonė"
            onChange={(value) => setSelectedCompanyId(Number(value))}
          />
          <InputFieldText
            value={templatePath}
            onChange={setTemplatePath}
            placeholder="Šablono kelias (pvz. 1 Sveikatos tikrinimo pazyma + knyga.docx)"
          />
        </div>

        <div className={styles.divider} />
        <h3 className={styles.subTitle}>Darbuotojų tikrinimo terminai</h3>
        {selectedCompanyId === null ? (
          <p className={styles.muted}>Pasirinkite įmonę.</p>
        ) : companyWorkerTypes.length === 0 ? (
          <p className={styles.muted}>Šiai įmonei dar nepriskirti darbuotojų tipai.</p>
        ) : (
          <div className={styles.workerPeriodList}>
            {companyWorkerTypes.map((worker) => (
              <div key={worker.id} className={styles.workerPeriodItem}>
                <p className={styles.workerName}>{worker.name}</p>
                <InputFieldText
                  value={checkPeriodsByWorkerId[worker.id] ?? ""}
                  onChange={(value) =>
                    setCheckPeriodsByWorkerId((prev) => ({ ...prev, [worker.id]: value }))
                  }
                  placeholder="Tikrinimo periodas (pvz. 1 metai)"
                />
              </div>
            ))}
          </div>
        )}

        <div className={styles.divider} />
        <button
          type="button"
          className={`${styles.button} ${styles.buttonSuccess}`}
          onClick={createCertificateDocument}
          disabled={creatingDocument || selectedCompanyId === null}
        >
          {creatingDocument ? "Kuriama..." : "Generuoti pažymą"}
        </button>
        <p className={styles.muted}>
          Sistemoje aktyvūs ryšiai: {workerRisks.length}. Veiksnių sąraše: {riskFactors.length}.
        </p>
      </aside>
    </div>
  );
}