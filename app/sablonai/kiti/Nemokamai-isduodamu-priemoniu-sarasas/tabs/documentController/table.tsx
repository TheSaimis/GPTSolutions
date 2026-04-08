"use client";

import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import { CompanyApi } from "@/lib/api/companies";
import { EquipmentApi } from "@/lib/api/equipment";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import type { Company } from "@/lib/types/Company";
import { useEffect, useMemo, useState } from "react";
import styles from "../../page.module.scss";

export default function EquipmentTable() {
    const [companies, setCompanies] = useState<Company[]>([]);
    const [selectedCompanyId, setSelectedCompanyId] = useState<string>("");
    const [creating, setCreating] = useState(false);
    const [preview, setPreview] = useState<{
        company: { companyName?: string | null; code?: string | null; address?: string | null };
        workers: Array<{ workerId: number; workerName: string; equipment: Array<{ id: number; name: string; expirationDate: string }> }>;
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
        setCreating(true);
        try {
            const result = await EquipmentApi.createTemplateDocument(companyId);
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
                <button
                    type="button"
                    className={styles.button}
                    onClick={createDocument}
                    disabled={!selectedCompanyId || creating}
                >
                    {creating ? "Kuriama..." : "Generuoti dokumentą"}
                </button>
            </div>

            {preview ? (
                <div className={styles.list}>
                    <p className={styles.itemText}>
                        Įmonė: {preview.company.companyName ?? "-"} | Kodas: {preview.company.code ?? "-"}
                    </p>
                    {preview.workers.map((worker) => (
                        <div key={worker.workerId} className={styles.item}>
                            <p className={styles.itemText}>
                                {worker.workerName} ({worker.equipment.length} priem.)
                            </p>
                        </div>
                    ))}
                </div>
            ) : (
                <p className={styles.muted}>Pasirinkite įmonę, kad matytumėte dokumento duomenų peržiūrą.</p>
            )}
        </div>
    );
}