"use client";

import { useEffect, useState, useCallback } from "react";
import { TemplateApi, type BulkTemplateItem } from "@/lib/api/templates";
import { CompanyApi } from "@/lib/api/companies";
import { FilesApi } from "@/lib/api/files";
import { extractUnknownVariablesFromOfficeFile } from "@/lib/functions/wordVariableParser";
import {
    templateCustomVariableKindsFromMetadata,
    type TemplateCustomVariableKind,
} from "@/lib/functions/templateCustomVariableNamesFromMetadata";
import type { CustomVariable, Company } from "@/lib/types/Company";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import InputFieldText from "@/components/inputFields/inputFieldText";
import { FileText, Download } from "lucide-react";
import { useDirectoryStore } from "@/lib/globalVariables/directoriesToSend";
import PageBackBar from "@/components/navigation/PageBackBar";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import styles from "../kurtiDokumenta/[...template]/page.module.scss";

export default function TemplatePage() {
    const selectedDirectories = useDirectoryStore((state) => state.selected);
    const [companies, setCompanies] = useState<Company[]>([]);
    const [company, setCompany] = useState("");
    const [variablesByPath, setVariablesByPath] = useState<Record<string, string[]>>({});
    const [variableKindsByPath, setVariableKindsByPath] = useState<Record<string, Record<string, TemplateCustomVariableKind>>>({});
    const [customByPath, setCustomByPath] = useState<Record<string, CustomVariable>>({});
    const [arrayRowsByPath, setArrayRowsByPath] = useState<Record<string, number>>({});
    const [loadingFields, setLoadingFields] = useState(false);

    useEffect(() => {
        void CompanyApi.getAll().then((data) => {
            setCompanies(data);
        });
        document.title = "Sukurti dokumentus";
    }, []);

    useEffect(() => {
        let cancelled = false;
        async function loadCustomFieldNames() {
            if (selectedDirectories.length === 0) {
                setVariablesByPath({});
                setLoadingFields(false);
                return;
            }
            setLoadingFields(true);
            const next: Record<string, string[]> = {};
            await Promise.all(
                selectedDirectories.map(async (path) => {
                    try {
                        const doc = await FilesApi.getFileData("templates", path);
                        const fromMeta = templateCustomVariableKindsFromMetadata(
                            doc.metadata?.custom as Record<string, unknown> | undefined,
                        );
                        if (fromMeta !== null) {
                            if (!cancelled) {
                                next[path] = Object.keys(fromMeta);
                                setVariableKindsByPath((prev) => ({ ...prev, [path]: fromMeta }));
                            }
                            return;
                        }
                    } catch {
                        /* fall back */
                    }
                    try {
                        const cacheKey = `templates/${path}`;
                        const { blob } = await FilesApi.downloadFile(cacheKey);
                        const fields = await extractUnknownVariablesFromOfficeFile(blob);
                        if (!cancelled) {
                            next[path] = fields;
                            setVariableKindsByPath((prev) => ({
                                ...prev,
                                [path]: Object.fromEntries(fields.map((name) => [name, "constant" as const])),
                            }));
                        }
                    } catch {
                        if (!cancelled) {
                            next[path] = [];
                            setVariableKindsByPath((prev) => ({ ...prev, [path]: {} }));
                        }
                    }
                }),
            );
            if (!cancelled) {
                setVariablesByPath(next);
                setLoadingFields(false);
            }
        }
        void loadCustomFieldNames();
        return () => {
            cancelled = true;
        };
    }, [selectedDirectories]);

    const updateCustomField = useCallback((path: string, fieldName: string, value: string) => {
        setCustomByPath((prev) => ({
            ...prev,
            [path]: {
                ...(prev[path] ?? {}),
                [fieldName]: value,
            },
        }));
    }, []);

    const updateArrayFieldCell = useCallback((path: string, fieldName: string, rowIndex: number, value: string) => {
        setCustomByPath((prev) => {
            const pathValues = prev[path] ?? {};
            const current = pathValues[fieldName];
            const rows = Array.isArray(current) ? [...current] : [];
            while (rows.length <= rowIndex) {
                rows.push("");
            }
            rows[rowIndex] = value;
            return {
                ...prev,
                [path]: {
                    ...pathValues,
                    [fieldName]: rows,
                },
            };
        });
    }, []);

    async function createDocument() {
        const companyId =
            company.trim() !== "" && Number.isFinite(Number(company)) && Number(company) > 0
                ? Number(company)
                : undefined;

        const templates: BulkTemplateItem[] = selectedDirectories.map((path) => {
            const raw = customByPath[path] ?? {};
            const cleaned = Object.fromEntries(
                Object.entries(raw).filter(([, value]) => {
                    if (typeof value === "string") {
                        return value.trim() !== "";
                    }
                    if (Array.isArray(value)) {
                        return value.some((item) => item.trim() !== "");
                    }
                    return false;
                }),
            );
            return Object.keys(cleaned).length > 0 ? { path, custom: cleaned } : path;
        });

        const { blob, filename } = await TemplateApi.createDocument(companyId, templates);
        downloadBlob({ blob, filename });
    }

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <PageBackBar />
            </div>

            <div className={styles.card}>
                {selectedDirectories.length === 0 ? (
                    <p className={styles.subtitle}>Nepasirinkta šablonų — grįžkite į katalogą ir pažymėkite failus.</p>
                ) : (
                    selectedDirectories.map((d) => (
                        <div key={d}>
                            <div className={styles.cardHeader}>
                                <div className={styles.fileIcon}>
                                    <FileText size={24} />
                                </div>
                                <div>
                                    <h1 className={styles.title}>{d.split("/").pop()}</h1>
                                    <p className={styles.subtitle}>{d}</p>
                                </div>
                            </div>
                            {loadingFields ? (
                                <p className={styles.subtitle}>Kraunami papildomi laukai…</p>
                            ) : (
                                (variablesByPath[d]?.length ?? 0) > 0 && (
                                    <div className={styles.customFields}>
                                        <h1>Papildomi laukai (tik šiam šablonui)</h1>
                                        {variablesByPath[d]!
                                            .filter((field) => variableKindsByPath[d]?.[field] !== "array")
                                            .map((field) => (
                                            <div key={field} className={styles.field}>
                                                <InputFieldText
                                                    placeholder={field}
                                                    value={typeof customByPath[d]?.[field] === "string" ? customByPath[d]?.[field] ?? "" : ""}
                                                    onChange={(value) => updateCustomField(d, field, value)}
                                                />
                                            </div>
                                        ))}
                                        {variablesByPath[d]!.some((field) => variableKindsByPath[d]?.[field] === "array") && (
                                            <div className={styles.arraySection}>
                                                <p className={styles.arrayTitle}>Eilučių laukai</p>
                                                <div className={styles.arrayGrid}>
                                                    <div className={styles.arrayHeaderRow}>
                                                        {variablesByPath[d]!
                                                            .filter((field) => variableKindsByPath[d]?.[field] === "array")
                                                            .map((field) => (
                                                                <span key={`head-${d}-${field}`} className={styles.arrayHeaderCell}>
                                                                    {field}
                                                                </span>
                                                            ))}
                                                    </div>
                                                    {Array.from({ length: arrayRowsByPath[d] ?? 1 }).map((_, rowIndex) => (
                                                        <div key={`row-${d}-${rowIndex}`} className={styles.arrayValueRow}>
                                                            {variablesByPath[d]!
                                                                .filter((field) => variableKindsByPath[d]?.[field] === "array")
                                                                .map((field) => (
                                                                    <input
                                                                        key={`${d}-${field}-${rowIndex}`}
                                                                        className={styles.arrayInput}
                                                                        value={Array.isArray(customByPath[d]?.[field]) ? customByPath[d]?.[field]?.[rowIndex] ?? "" : ""}
                                                                        onChange={(e) =>
                                                                            updateArrayFieldCell(d, field, rowIndex, e.target.value)
                                                                        }
                                                                        placeholder={`${field} ${rowIndex + 1}`}
                                                                    />
                                                                ))}
                                                        </div>
                                                    ))}
                                                </div>
                                                <button
                                                    type="button"
                                                    className={styles.addArrayRow}
                                                    onClick={() =>
                                                        setArrayRowsByPath((prev) => ({
                                                            ...prev,
                                                            [d]: (prev[d] ?? 1) + 1,
                                                        }))
                                                    }
                                                >
                                                    +
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                )
                            )}
                            <div className={styles.divider} />
                        </div>
                    ))
                )}

                <div className={styles.form}>
                    <InputFieldSelect
                        placeholder={"Įmonė (neprivaloma)"}
                        selected={company}
                        search={true}
                        onChange={setCompany}
                        options={[
                            { value: "", label: "Be įmonės" },
                            ...companies.map((c) => ({
                                value: String(c.id),
                                label: `${c.companyType} ${c.companyName}`,
                            })),
                        ]}
                    />
                </div>

                <button
                    type="button"
                    className={styles.submitButton}
                    onClick={() => void createDocument()}
                    disabled={selectedDirectories.length === 0}
                >
                    <Download size={18} />
                    Sukurti dokumentą
                </button>
            </div>
        </div>
    );
}
