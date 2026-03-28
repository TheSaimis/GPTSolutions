"use client"

import { useState } from "react";
import { usePazyma } from "../pazymaContext"
import { createRisk } from "./CRUD/createRisk";
import { deleteRisk } from "./CRUD/deleteRisk";
import { updateRisk } from "./CRUD/updateRisk";
import InputFieldText from "@/components/inputFields/inputFieldText";
import Risk from "./components/risk";
import styles from "../controllers.module.scss";

export default function RiskController() {
    const { riskFactors, setRiskFactors } = usePazyma();
    const [name, setName] = useState("");
    const [cipher, setCipher] = useState("");
    const [isCreating, setIsCreating] = useState(false);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    async function handleCreate() {
        if (name.trim() === "") return;

        setIsCreating(true);
        setError(null);
        try {
            await createRisk({
                name,
                cipher,
                setRiskFactors,
            });
            setName("");
            setCipher("");
        } catch {
            setError("Nepavyko sukurti rizikos faktoriaus.");
        } finally {
            setIsCreating(false);
        }
    }

    async function handleUpdate(riskId: number, input: { name: string; cipher: string }) {
        setBusyId(riskId);
        setError(null);
        try {
            await updateRisk({
                riskId,
                name: input.name,
                cipher: input.cipher,
                setRiskFactors,
            });
        } catch {
            setError("Nepavyko atnaujinti rizikos faktoriaus.");
        } finally {
            setBusyId(null);
        }
    }

    async function handleDelete(riskId: number) {
        setBusyId(riskId);
        setError(null);
        try {
            await deleteRisk({ riskId, setRiskFactors });
        } catch {
            setError("Nepavyko ištrinti rizikos faktoriaus.");
        } finally {
            setBusyId(null);
        }
    }

    return (
        <div className={styles.controller}>
            <h3 className={styles.title}>Rizikos faktoriai</h3>
            <div className={`${styles.panel} ${styles.formRow}`}>
                <InputFieldText value={name} onChange={setName} placeholder="Pavadinimas" disabled={isCreating} onKeyDown={{ Enter: handleCreate }} />
                <InputFieldText value={cipher} onChange={setCipher} placeholder="Šifras" disabled={isCreating} onKeyDown={{ Enter: handleCreate }} />
                <button
                    type="button"
                    className={`${styles.button} ${styles.buttonPrimary}`}
                    onClick={handleCreate}
                    disabled={isCreating || name.trim() === ""}
                >
                    Pridėti
                </button>
            </div>
            {error ? <p className={styles.error}>{error}</p> : null}

            {riskFactors.map((riskFactor) => (
                <Risk
                    key={riskFactor.id}
                    risk={riskFactor}
                    onUpdate={handleUpdate}
                    onDelete={handleDelete}
                    busy={isCreating || busyId === riskFactor.id}
                />
            ))}
        </div>
    )
}