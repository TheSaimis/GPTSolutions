"use client";

import { useEffect, useState, useCallback } from "react";
import { TemplateApi, type BulkTemplateItem } from "@/lib/api/templates";
import { CompanyApi } from "@/lib/api/companies";
import { FilesApi } from "@/lib/api/files";
import { extractUnknownVariablesFromOfficeFile } from "@/lib/functions/wordVariableParser";
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
    const [customByPath, setCustomByPath] = useState<Record<string, CustomVariable>>({});
    const [loadingFields, setLoadingFields] = useState(false);

    useEffect(() => {
        getCompanies();
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
                        const cacheKey = `templates/${path}`;
                        const { blob } = await FilesApi.downloadFile(cacheKey);
                        const fields = await extractUnknownVariablesFromOfficeFile(blob);
                        if (!cancelled) {
                            next[path] = fields;
                        }
                    } catch {
                        if (!cancelled) {
                            next[path] = [];
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
    }, [selectedDirectories.join("\u0000")]);

    const updateCustomField = useCallback((path: string, fieldName: string, value: string) => {
        setCustomByPath((prev) => ({
            ...prev,
            [path]: {
                ...(prev[path] ?? {}),
                [fieldName]: value,
            },
        }));
    }, []);

    async function getCompanies() {
        const data = await CompanyApi.getAll();
        setCompanies(data);
    }

    async function createDocument() {
        const companyId =
            company.trim() !== "" && Number.isFinite(Number(company)) && Number(company) > 0
                ? Number(company)
                : undefined;

        const templates: BulkTemplateItem[] = selectedDirectories.map((path) => {
            const raw = customByPath[path] ?? {};
            const cleaned = Object.fromEntries(
                Object.entries(raw).filter(([, value]) => value.trim() !== ""),
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
                                        {variablesByPath[d]!.map((field) => (
                                            <div key={field} className={styles.field}>
                                                <InputFieldText
                                                    placeholder={field}
                                                    value={customByPath[d]?.[field] ?? ""}
                                                    onChange={(value) => updateCustomField(d, field, value)}
                                                />
                                            </div>
                                        ))}
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
