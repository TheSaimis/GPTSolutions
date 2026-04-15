"use client";

import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import InputFieldText from "@/components/inputFields/inputFieldText";
import { CompanyApi } from "@/lib/api/companies";
import { EquipmentApi, type AapTemplateLocale } from "@/lib/api/equipment";
import { FilesApi } from "@/lib/api/files";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import {
    templateCustomVariableKindsFromMetadata,
    type TemplateCustomVariableMap,
} from "@/lib/functions/templateCustomVariableNamesFromMetadata";
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
    /** Numatyta „kortelės“: dažnai įkeliamas tik šis šablonas; sąrašui — atskiro „AAP sąrašas“ šablono. */
    const [wantSarasas, setWantSarasas] = useState(false);
    const [wantKorteles, setWantKorteles] = useState(true);
    const [documentPagrindas, setDocumentPagrindas] = useState("");
    const [documentLanguage, setDocumentLanguage] = useState<AapTemplateLocale>("lt");
    const [customFieldKinds, setCustomFieldKinds] = useState<TemplateCustomVariableMap>({});
    const [customValues, setCustomValues] = useState<Record<string, string | string[]>>({});
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

    useEffect(() => {
        const kindsMerged: TemplateCustomVariableMap = {};
        const paths: string[] = [];
        const suffix = documentLanguage === "lt" ? "" : ` ${documentLanguage.toUpperCase()}`;
        if (wantSarasas) {
            paths.push(`AAP/AAP sąrašas${suffix}.docx`, "AAP/AAP sąrašas.docx");
        }
        if (wantKorteles) {
            paths.push(`AAP/AAP kortelės + žiniaraščiai${suffix}.docx`, "AAP/AAP kortelės + žiniaraščiai.docx");
        }
        const uniquePaths = Array.from(new Set(paths));
        if (uniquePaths.length === 0) {
            setCustomFieldKinds({});
            setCustomValues({});
            return;
        }

        Promise.allSettled(uniquePaths.map((path) => FilesApi.getFileData("templates", path))).then((results) => {
            results.forEach((r) => {
                if (r.status !== "fulfilled") return;
                const kinds = templateCustomVariableKindsFromMetadata(r.value.metadata?.custom);
                if (!kinds) return;
                Object.entries(kinds).forEach(([name, kind]) => {
                    if (!(name in kindsMerged)) {
                        kindsMerged[name] = kind;
                    }
                });
            });
            setCustomFieldKinds(kindsMerged);
            setCustomValues((prev) => {
                const next: Record<string, string | string[]> = {};
                Object.entries(kindsMerged).forEach(([name, kind]) => {
                    const current = prev[name];
                    if (kind === "array") {
                        next[name] = Array.isArray(current) ? current : [];
                    } else {
                        next[name] = typeof current === "string" ? current : "";
                    }
                });
                return next;
            });
        });
    }, [documentLanguage, wantKorteles, wantSarasas]);

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
                custom: customValues,
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
                        AAP sąrašas
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
                    label="Kalba"
                    options={DOC_LANG_OPTIONS}
                    selected={docLangSelectLabel}
                    onChange={(v) => setDocumentLanguage(v as AapTemplateLocale)}
                    search={false}
                />
                {wantKorteles ? (
                    <div className={styles.pagrindasField}>
                        <label className={styles.pagrindasLabel} htmlFor="aap-doc-pagrindas">
                            Pagrindas (kortelėms)
                        </label>
                        <textarea
                            id="aap-doc-pagrindas"
                            className={styles.pagrindasTextarea}
                            value={documentPagrindas}
                            onChange={(e) => setDocumentPagrindas(e.target.value)}
                            placeholder=""
                            spellCheck
                            disabled={!selectedCompanyId}
                        />
                    </div>
                ) : null}
                {Object.keys(customFieldKinds).length > 0 ? (
                    <div className={styles.customFieldsWrap}>
                        {Object.entries(customFieldKinds).map(([name, kind]) =>
                            kind === "array" ? (
                                <div key={name} className={styles.customFieldBlock}>
                                    <label className={styles.customFieldLabel} htmlFor={`aap-word-custom-${name}`}>
                                        {name}
                                    </label>
                                    <textarea
                                        id={`aap-word-custom-${name}`}
                                        className={styles.customFieldTextarea}
                                        value={Array.isArray(customValues[name]) ? customValues[name].join("\n") : ""}
                                        onChange={(e) =>
                                            setCustomValues((prev) => ({
                                                ...prev,
                                                [name]: e.target.value
                                                    .split(/\r?\n/)
                                                    .map((v) => v.trim())
                                                    .filter((v) => v !== ""),
                                            }))
                                        }
                                        placeholder="Kiekviena reikšmė naujoje eilutėje"
                                    />
                                </div>
                            ) : (
                                <InputFieldText
                                    key={name}
                                    value={typeof customValues[name] === "string" ? customValues[name] : ""}
                                    onChange={(value) =>
                                        setCustomValues((prev) => ({
                                            ...prev,
                                            [name]: value,
                                        }))
                                    }
                                    placeholder={name}
                                />
                            )
                        )}
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
                            Pagrindas: {preview.company.pagrindas}
                        </p>
                    )}
                    {preview.groups && preview.groups.length > 0 ? (
                        <>
                            {preview.groups.map((g) => (
                                <div key={g.groupId} className={styles.previewWorker}>
                                    <p className={styles.itemText}>
                                        {g.groupName} — {g.workers.length} tip., {g.equipment.length} priem.
                                    </p>
                                    {g.workers.length > 0 ? (
                                        <p className={styles.previewEqList} style={{ margin: "4px 0", fontSize: 13 }}>
                                            {g.workers.map((w) => w.workerName).join(", ")}
                                        </p>
                                    ) : null}
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
            ) : null}
        </div>
    );
}