"use client"

import type { HealthCertificateRiskFactor } from "@/lib/types/healthCertificate"
import InputFieldText from "@/components/inputFields/inputFieldText"
import { useState } from "react"
import { Trash2 } from "lucide-react";
import styles from "../../controllers.module.scss";

type RiskProps = {
    risk: HealthCertificateRiskFactor;
    onUpdate: (riskId: number, input: { name: string; cipher: string }) => Promise<void>;
    onDelete: (riskId: number) => Promise<void>;
    busy: boolean;
}

export default function Risk({ risk, onUpdate, onDelete, busy }: RiskProps) {
    const [isEditing, setIsEditing] = useState(false);
    const [name, setName] = useState(risk.name);
    const [cipher, setCipher] = useState(risk.cipher ?? "");

    async function handleSave() {
        await onUpdate(risk.id, { name, cipher });
        setIsEditing(false);
    }

    function handleCancel() {
        setName(risk.name);
        setCipher(risk.cipher ?? "");
        setIsEditing(false);
    }

    return (
        <div className={styles.rowCard}>
            <button
                type="button"
                className={styles.iconDangerButton}
                onClick={() => onDelete(risk.id)}
                disabled={busy}
                aria-label="Trinti rizikos faktorių"
                title="Trinti rizikos faktorių"
            >
                <Trash2 size={16} />
            </button>
            {isEditing ? (
                <div className={styles.formRow}>
                    <InputFieldText value={name} onChange={setName} placeholder="Pavadinimas" disabled={busy} onKeyDown={{ Enter: handleSave }} />
                    <InputFieldText value={cipher} onChange={setCipher} placeholder="Kodas" disabled={busy} onKeyDown={{ Enter: handleSave }} />
                </div>
            ) : (
                <>
                    <div className={styles.rowHeader}>{risk.name}</div>
                    <div className={styles.metaText}>Šifras: {risk.cipher || "-"}</div>
                </>
            )}

            <div className={styles.actions}>
                {!isEditing ? (
                    <button
                        type="button"
                        className={`${styles.button} ${styles.buttonSecondary}`}
                        onClick={() => setIsEditing(true)}
                        disabled={busy}
                    >
                        Redaguoti
                    </button>
                ) : (
                    <>
                        <button
                            type="button"
                            className={`${styles.button} ${styles.buttonPrimary}`}
                            onClick={handleSave}
                            disabled={busy || name.trim() === ""}
                        >
                            Išsaugoti
                        </button>
                        <button
                            type="button"
                            className={`${styles.button} ${styles.buttonSecondary}`}
                            onClick={() => setIsEditing(false)}
                            disabled={busy}
                        >
                            Atšaukti
                        </button>

                    </>
                )}
            </div>
        </div>
    )
}