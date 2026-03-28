"use client"

import { useMemo, useState } from "react";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import InputFieldText from "@/components/inputFields/inputFieldText";
import { usePazyma } from "../pazymaContext";
import { updateRisk } from "../riskController/CRUD/updateRisk";
import { createWorkerRisk } from "./CRUD/createWorkerRisk";
import { deleteWorkerRisk } from "./CRUD/deleteWorkerRisk";
import { updateWorkerRisk } from "./CRUD/updateWorkerRisk";
import WorkerRisk from "./components/workerRisk";
import styles from "../controllers.module.scss";

export default function WorkerController() {
    const {
        workers,
        riskFactors,
        workerRisks,
        selectedWorkerId,
        setSelectedWorkerId,
        setRiskFactors,
        setWorkerRisks,
    } = usePazyma();
    const [riskFactorId, setRiskFactorId] = useState<number>(0);
    const [isCreating, setIsCreating] = useState(false);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [workerSearch, setWorkerSearch] = useState("");
    const selectedRiskFactor = riskFactors.find((riskFactor) => riskFactor.id === riskFactorId);
    const selectedWorker = workers.find((worker) => worker.id === selectedWorkerId) ?? null;
    const filteredWorkers = useMemo(() => {
        const query = workerSearch.trim().toLowerCase();
        if (query === "") return workers;
        return workers.filter((worker) => worker.name.toLowerCase().includes(query));
    }, [workers, workerSearch]);

    async function handleCreate() {
        if (!selectedWorkerId || !riskFactorId) return;

        setIsCreating(true);
        setError(null);
        try {
            await createWorkerRisk({
                workerId: selectedWorkerId,
                riskFactorId,
                setWorkerRisks,
            });
            setRiskFactorId(0);
        } catch {
            setError("Nepavyko priskirti rizikos faktoriaus darbuotojui.");
        } finally {
            setIsCreating(false);
        }
    }

    async function handleUpdate(workerRiskId: number, nextRiskFactorId: number) {
        if (!selectedWorkerId) return;

        setBusyId(workerRiskId);
        setError(null);
        try {
            await updateWorkerRisk({
                workerRiskId,
                workerId: selectedWorkerId,
                riskFactorId: nextRiskFactorId,
                setWorkerRisks,
            });
        } catch {
            setError("Nepavyko atnaujinti darbuotojo rizikos faktoriaus.");
        } finally {
            setBusyId(null);
        }
    }

    async function handleDelete(workerRiskId: number) {
        setBusyId(workerRiskId);
        setError(null);
        try {
            await deleteWorkerRisk({
                workerRiskId,
                setWorkerRisks,
            });
        } catch {
            setError("Nepavyko ištrinti darbuotojo rizikos faktoriaus.");
        } finally {
            setBusyId(null);
        }
    }

    async function handleRiskUpdate(riskId: number, input: { name: string; cipher: string }) {
        setBusyId(riskId);
        setError(null);
        try {
            await updateRisk({
                riskId,
                name: input.name,
                cipher: input.cipher,
                setRiskFactors,
            });
            setWorkerRisks((prev) =>
                prev.map((workerRisk) =>
                    workerRisk.riskFactor?.id === riskId
                        ? {
                            ...workerRisk,
                            riskFactor: workerRisk.riskFactor
                                ? {
                                    ...workerRisk.riskFactor,
                                    name: input.name.trim(),
                                    cipher: input.cipher.trim(),
                                }
                                : null,
                        }
                        : workerRisk
                )
            );
        } catch {
            setError("Nepavyko atnaujinti rizikos faktoriaus.");
        } finally {
            setBusyId(null);
        }
    }

    return (
        <div className={`${styles.controller} ${styles.workerLayout}`}>
            <aside className={styles.workerMenu}>
                <h3 className={styles.title}>Darbuotojų tipai</h3>
                <InputFieldText
                    value={workerSearch}
                    onChange={setWorkerSearch}
                    placeholder="Ieškoti darbuotojo tipo"
                />

                <div className={styles.workerList}>
                    {filteredWorkers.map((worker) => (
                        <button
                            key={worker.id}
                            type="button"
                            onClick={() => setSelectedWorkerId(worker.id)}
                            className={`${styles.workerListButton} ${selectedWorkerId === worker.id ? styles.workerListButtonActive : ""}`}
                        >
                            {worker.name}
                        </button>
                    ))}
                    {filteredWorkers.length === 0 ? <p className={styles.subtitle}>Nerasta darbuotojų tipų.</p> : null}
                </div>
            </aside>

            <section className={styles.controller}>
                <h3 className={styles.title}>Darbuotojo rizikos faktoriai</h3>
                <p className={styles.subtitle}>
                    {selectedWorker ? `Pasirinktas darbuotojo tipas: ${selectedWorker.name}` : "Pasirinkite darbuotojo tipą."}
                </p>
                <div className={`${styles.panel} ${styles.formRow}`}>
                    <InputFieldSelect
                        options={riskFactors.map((riskFactor) => ({
                            value: String(riskFactor.id),
                            label: `${riskFactor.name} (${riskFactor.cipher})`,
                        }))}
                        selected={selectedRiskFactor ? `${selectedRiskFactor.name} (${selectedRiskFactor.cipher})` : ""}
                        placeholder="Pasirinkite rizikos faktorių"
                        onChange={(value) => setRiskFactorId(Number(value) || 0)}
                    />

                    <button
                        type="button"
                        className={`${styles.button} ${styles.buttonPrimary}`}
                        onClick={handleCreate}
                        disabled={isCreating || !selectedWorkerId || !riskFactorId}
                    >
                        Pridėti
                    </button>
                </div>

                {error ? <p className={styles.error}>{error}</p> : null}

                {workerRisks.map((workerRisk) => (
                    <WorkerRisk
                        key={workerRisk.id}
                        workerRisk={workerRisk}
                        riskFactors={riskFactors}
                        selectedWorkerId={selectedWorkerId ?? 0}
                        onUpdate={handleUpdate}
                        onDelete={handleDelete}
                        onRiskUpdate={handleRiskUpdate}
                        busy={
                            isCreating ||
                            busyId === workerRisk.id ||
                            busyId === workerRisk.riskFactor?.id
                        }
                    />
                ))}
            </section>
        </div>
    )
}