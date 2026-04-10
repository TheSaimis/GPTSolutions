"use client";

import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import { CompanyApi } from "@/lib/api/companies";
import { EquipmentApi } from "@/lib/api/equipment";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import type { Company } from "@/lib/types/Company";
import { useEffect, useMemo, useState } from "react";
import styles from "../../page.module.scss";
import { equipmentUnitLabel } from "../equipmentController/equipmentUnits";

export default function EquipmentTable() {
    const [companies, setCompanies] = useState<Company[]>([]);
    const [selectedCompanyId, setSelectedCompanyId] = useState<string>("");
    const [creating, setCreating] = useState(false);
    const [wantSarasas, setWantSarasas] = useState(true);
    const [wantKorteles, setWantKorteles] = useState(false);
    const [preview, setPreview] = useState<{
        company: { companyName?: string | null; code?: string | null; address?: string | null };
        workers: Array<{
            workerId: number;
            workerName: string;
            equipment: Array<{
                id: number;
                name: string;
                expirationDate: string;
                unitOfMeasurement?: string;
            }>;
        }>;
        groups?: Array<{
            groupId: number;
            groupName: string;
            workers: Array<{ workerId: number; workerName: string }>;
            equipment: Array<{
                id: number;
                name: string;
                expirationDate: string;
                unitOfMeasurement?: string;
            }>;
        }>;
    } | null>(null);

    useEffect(() => {
        CompanyApi.getAll().then(setCompanies).catch(() => setCompanies([]));
    }, []);

    useEffect(() => {
        const id = Number(selectedCompanyId);
        if (!id) {
            setPreview(null);
            return;
        }
        EquipmentApi.getCompanyData(id).then(setPreview).catch(() => setPreview(null));
    }, [selectedCompanyId]);

    const companyOptions = useMemo(
        () =>
            companies
                .filter((company) => company.id)
                .map((company) => ({
                    value: String(company.id),
                    label: `${company.companyType ?? ""} ${company.companyName ?? ""}`.trim(),
                })),
        [companies],
    );

    const selectedCompanyLabel =
        companyOptions.find((option) => option.value === selectedCompanyId)?.label ?? "";

    async function createDocument() {
        const companyId = Number(selectedCompanyId);
        if (!companyId) return;
        const outputs: ("sarasas" | "korteles")[] = [];
        if (wantSarasas) outputs.push("sarasas");
        if (wantKorteles) outputs.push("korteles");
        if (outputs.length === 0) return;
        setCreating(true);
        try {
            const result = await EquipmentApi.createTemplateDocument(companyId, outputs);
            downloadBlob(result);
        } finally {
            setCreating(false);
        }
    }

    return (
        <div className={styles.card}>
            <div className={styles.row}>
                <InputFieldSelect
                    label="Įmonė"
                    options={companyOptions}
                    selected={selectedCompanyLabel}
                    placeholder="Pasirinkite įmonę"
                    onChange={setSelectedCompanyId}
                    search
                />
                <div className={styles.checkboxRow} role="group" aria-label="Kokie dokumentai generuojami">
                    <label className={styles.checkboxLabel}>
                        <input
                            type="checkbox"
                            checked={wantSarasas}
                            onChange={(e) => setWantSarasas(e.target.checked)}
                        />
                        AAP sąrašas (pareigybė, priemonė, terminas)
                    </label>
                    <label className={styles.checkboxLabel}>
                        <input
                            type="checkbox"
                            checked={wantKorteles}
                            onChange={(e) => setWantKorteles(e.target.checked)}
                        />
                        AAP Kortelės+Žiniaraščiai
                    </label>
                </div>
                <button
                    type="button"
                    className={styles.button}
                    onClick={createDocument}
                    disabled={!selectedCompanyId || creating || (!wantSarasas && !wantKorteles)}
                >
                    {creating ? "Kuriama..." : "Generuoti pasirinktus dokumentus"}
                </button>
            </div>

            {preview ? (
                <div className={styles.list}>
                    <p className={styles.itemText}>
                        Įmonė: {preview.company.companyName ?? "-"} | Kodas: {preview.company.code ?? "-"}
                    </p>
                    {preview.groups && preview.groups.length > 0 ? (
                        <>
                            <p className={styles.mutedSmall} style={{ margin: "8px 0" }}>
                                Dokumente naudojamos <strong>grupės</strong> — po vieną lentelės eilutę kiekvienai grupei.
                            </p>
                            {preview.groups.map((g) => (
                                <div key={g.groupId} className={styles.previewWorker}>
                                    <p className={styles.itemText}>
                                        {g.groupName} — {g.workers.length} tip., {g.equipment.length} priem.
                                    </p>
                                    <p className={styles.previewEqList} style={{ margin: "4px 0", fontSize: 13 }}>
                                        <strong>Darbuotojų tipai:</strong>{" "}
                                        {g.workers.map((w) => w.workerName).join(", ") || "—"}
                                    </p>
                                    <ul className={styles.previewEqList}>
                                        {g.equipment.map((eq) => (
                                            <li key={eq.id}>
                                                {eq.name} — {equipmentUnitLabel(eq.unitOfMeasurement)} (iki {eq.expirationDate || "—"})
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </>
                    ) : (
                        preview.workers.map((worker) => (
                            <div key={worker.workerId} className={styles.previewWorker}>
                                <p className={styles.itemText}>
                                    {worker.workerName} ({worker.equipment.length} priem.)
                                </p>
                                <ul className={styles.previewEqList}>
                                    {worker.equipment.map((eq) => (
                                        <li key={eq.id}>
                                            {eq.name} — {equipmentUnitLabel(eq.unitOfMeasurement)}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))
                    )}
                </div>
            ) : (
                <p className={styles.muted}>Pasirinkite įmonę, kad matytumėte dokumento duomenų peržiūrą.</p>
            )}
        </div>
    );
}