"use client";

import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import { CompanyApi } from "@/lib/api/companies";
import { EquipmentApi, type AapTemplateLocale } from "@/lib/api/equipment";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import type { Company } from "@/lib/types/Company";
import { useEffect, useMemo, useState } from "react";
import styles from "../../page.module.scss";
import { equipmentUnitLabel, type EquipmentDocLang } from "../equipmentController/equipmentUnits";

type PreviewEq = {
    id: number;
    name: string;
    expirationDate: string;
    unitOfMeasurement?: string;
    nameEn?: string | null;
    nameRu?: string | null;
    expirationDateEn?: string | null;
    expirationDateRu?: string | null;
};

function docLangFromLocale(loc: AapTemplateLocale): EquipmentDocLang {
    if (loc === "en") return "EN";
    if (loc === "ru") return "RU";
    return "LT";
}

function previewEquipmentName(eq: PreviewEq, loc: AapTemplateLocale): string {
    if (loc === "en") {
        const t = eq.nameEn?.trim();
        return t !== "" && t != null ? t : eq.name;
    }
    if (loc === "ru") {
        const t = eq.nameRu?.trim();
        return t !== "" && t != null ? t : eq.name;
    }
    return eq.name;
}

function previewEquipmentExpiration(eq: PreviewEq, loc: AapTemplateLocale): string {
    if (loc === "en") {
        const t = eq.expirationDateEn?.trim();
        return t !== "" && t != null ? t : eq.expirationDate;
    }
    if (loc === "ru") {
        const t = eq.expirationDateRu?.trim();
        return t !== "" && t != null ? t : eq.expirationDate;
    }
    return eq.expirationDate;
}

const DOC_LANG_OPTIONS: { value: AapTemplateLocale; label: string }[] = [
    { value: "lt", label: "Dokumentas LT" },
    { value: "en", label: "Dokumentas EN" },
    { value: "ru", label: "Dokumentas RU" },
];

export default function EquipmentTable() {
    const [companies, setCompanies] = useState<Company[]>([]);
    const [selectedCompanyId, setSelectedCompanyId] = useState<string>("");
    const [creating, setCreating] = useState(false);
    const [wantSarasas, setWantSarasas] = useState(true);
    const [wantKorteles, setWantKorteles] = useState(false);
    /** Vienkartinis ${pagrindas} tekstas generuojant korteles (tuščia = įmonės / numatytasis). */
    const [documentPagrindas, setDocumentPagrindas] = useState("");
    const [documentLanguage, setDocumentLanguage] = useState<AapTemplateLocale>("lt");
    const [preview, setPreview] = useState<{
        company: {
            companyName?: string | null;
            code?: string | null;
            address?: string | null;
            pagrindas?: string;
        };
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

    useEffect(() => {
        if (!preview) {
            setDocumentPagrindas("");
            return;
        }
        setDocumentPagrindas(preview.company.pagrindas ?? "");
    }, [preview]);

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

    const docLang = docLangFromLocale(documentLanguage);
    const docLangSelectLabel =
        DOC_LANG_OPTIONS.find((o) => o.value === documentLanguage)?.label ?? "Dokumentas LT";

    async function createDocument() {
        const companyId = Number(selectedCompanyId);
        if (!companyId) return;
        const outputs: ("sarasas" | "korteles")[] = [];
        if (wantSarasas) outputs.push("sarasas");
        if (wantKorteles) outputs.push("korteles");
        if (outputs.length === 0) return;
        setCreating(true);
        try {
            const pagrindasOpt =
                wantKorteles && documentPagrindas.trim() !== ""
                    ? { pagrindas: documentPagrindas.trim() }
                    : undefined;
            const result = await EquipmentApi.createTemplateDocument(companyId, outputs, {
                ...(pagrindasOpt ?? {}),
                language: documentLanguage,
            });
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
                <InputFieldSelect
                    label="Dokumento kalba (šablonas + priemonių tekstai)"
                    options={DOC_LANG_OPTIONS}
                    selected={docLangSelectLabel}
                    onChange={(v) => setDocumentLanguage(v as AapTemplateLocale)}
                    search={false}
                />
                {wantKorteles ? (
                    <div className={styles.pagrindasField}>
                        <label className={styles.pagrindasLabel} htmlFor="aap-doc-pagrindas">
                            „Pagrindas išduoti“ kortelėse (<code>{"${pagrindas}"}</code>) — šiam eksportui
                        </label>
                        <textarea
                            id="aap-doc-pagrindas"
                            className={styles.pagrindasTextarea}
                            value={documentPagrindas}
                            onChange={(e) => setDocumentPagrindas(e.target.value)}
                            placeholder="Palikite tuščią arba kaip įmonės kortelėje — naudos įmonės arba numatytąjį tekstą. Įrašykite čia, jei šiam kartui norite kito teksto."
                            spellCheck
                            disabled={!selectedCompanyId}
                        />
                    </div>
                ) : null}
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
                    {preview.company.pagrindas != null && preview.company.pagrindas !== "" && (
                        <p className={styles.mutedSmall} style={{ margin: "6px 0 8px" }}>
                            <strong>{"${pagrindas}"}</strong> (kortelės): {preview.company.pagrindas}
                        </p>
                    )}
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
                                                {previewEquipmentName(eq, documentLanguage)} —{" "}
                                                {equipmentUnitLabel(eq.unitOfMeasurement, docLang)} (iki{" "}
                                                {previewEquipmentExpiration(eq, documentLanguage) || "—"})
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
                                            {previewEquipmentName(eq, documentLanguage)} —{" "}
                                            {equipmentUnitLabel(eq.unitOfMeasurement, docLang)} (iki{" "}
                                            {previewEquipmentExpiration(eq, documentLanguage) || "—"})
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