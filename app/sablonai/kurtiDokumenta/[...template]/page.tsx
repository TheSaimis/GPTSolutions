"use client";

// rushed code for features but it works so far

import { useEffect, useState } from "react";
import { TemplateApi } from "@/lib/api/templates";
import { CompanyApi } from "@/lib/api/companies";
import { FilesApi } from "@/lib/api/files";
import { extractUnknownVariablesFromOfficeFile } from "@/lib/functions/wordVariableParser";
import {
    templateCustomVariableKindsFromMetadata,
    type TemplateCustomVariableKind,
} from "@/lib/functions/templateCustomVariableNamesFromMetadata";
import type { CustomVariable, Company } from "@/lib/types/Company";
import { setPDFToView } from "@/lib/globalVariables/pdfToView";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import InputFieldText from "@/components/inputFields/inputFieldText";
import { FileText, Download, Eye } from "lucide-react";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import styles from "./page.module.scss";
import { useParams } from "next/navigation";
import PageBackBar from "@/components/navigation/PageBackBar";
import CompanyCard from "@/components/companyCard/companyCard";

export default function TemplatePage() {
    const { template } = useParams();
    const templatePath = Array.isArray(template) ? template.join("/") : template;
    const fileName = Array.isArray(template) ? template.at(-1) : template;
    const directory = decodeURIComponent(templatePath || "");
    const documentName = decodeURIComponent(fileName || "");
    const [customFields, setCustomFields] = useState<string[]>([]);
    const [customVariables, setCustomVariables] = useState<CustomVariable>({});
    const [customFieldKinds, setCustomFieldKinds] = useState<Record<string, TemplateCustomVariableKind>>({});
    const [arrayRowCount, setArrayRowCount] = useState(1);
    const [companies, setCompanies] = useState<Company[]>([]);
    const [company, setCompany] = useState("");

    useEffect(() => {
        void CompanyApi.getAll().then((data) => {
            setCompanies(data);
        });
        document.title = documentName;
    }, [documentName]);

    useEffect(() => {
        let cancelled = false;
        async function getTemplateWord() {
            try {
                const doc = await FilesApi.getFileData("templates", directory);
                const fromMeta = templateCustomVariableKindsFromMetadata(
                    doc.metadata?.custom as Record<string, unknown> | undefined,
                );
                if (!cancelled && fromMeta !== null) {
                    const names = Object.keys(fromMeta);
                    setCustomFields(names);
                    setCustomFieldKinds(fromMeta);
                    return;
                }
            } catch {
                /* fall back to blob scan */
            }
            if (cancelled) {
                return;
            }
            try {
                const cacheKey = `templates/${directory}`;
                const { blob } = await FilesApi.downloadFile(cacheKey);
                const result = await extractUnknownVariablesFromOfficeFile(blob);
                if (!cancelled) {
                    setCustomFields(result);
                    setCustomFieldKinds(
                        Object.fromEntries(result.map((name) => [name, "constant" as const])),
                    );
                }
            } catch {
                if (!cancelled) {
                    setCustomFields([]);
                    setCustomFieldKinds({});
                }
            }
        }

        void getTemplateWord();
        return () => {
            cancelled = true;
        };
    }, [directory]);
    function updateCustomField(fieldName: string, value: string) {
        setCustomVariables((prev) => ({
            ...prev,
            [fieldName]: value,
        }));
    }

    function updateArrayFieldCell(fieldName: string, rowIndex: number, value: string) {
        setCustomVariables((prev) => {
            const current = prev[fieldName];
            const rows = Array.isArray(current) ? [...current] : [];
            while (rows.length <= rowIndex) {
                rows.push("");
            }
            rows[rowIndex] = value;
            return {
                ...prev,
                [fieldName]: rows,
            };
        });
    }

    async function viewPDF() {
        FilesApi.getPDF("templates", directory).then((res) => {
            setPDFToView(res);
        });
    }

    async function createDocument() {
        const cleanedCustomVariables = Object.fromEntries(
            Object.entries(customVariables).filter(([, value]) => {
                if (typeof value === "string") {
                    return value.trim() !== "";
                }
                if (Array.isArray(value)) {
                    return value.some((item) => item.trim() !== "");
                }
                return false;
            })
        );
        const companyId =
            company.trim() !== "" && Number.isFinite(Number(company)) && Number(company) > 0
                ? Number(company)
                : undefined;
        const { blob, filename } = await TemplateApi.createDocument(
            companyId,
            [directory],
            cleanedCustomVariables
        );
        downloadBlob({ blob, filename });
    }

    const constantFields = customFields.filter((field) => customFieldKinds[field] !== "array");
    const arrayFields = customFields.filter((field) => customFieldKinds[field] === "array");

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <PageBackBar />
            </div>
            <div className={styles.content}>
                <div className={styles.card}>

                    <div className={styles.cardHeader}>
                        <div className={styles.fileIconWrap}>
                            <div className={styles.fileIcon}>
                                <FileText size={24} />
                            </div>
                            <div>
                                <h1 className={styles.title}>{documentName}</h1>
                                <p className={styles.subtitle}>
                                    Įmonė neprivaloma — be jos dokumentai saugomi kataloge „Be įmonės dokumentai“
                                </p>
                            </div>
                        </div>
                        <button onClick={viewPDF} className="buttons">
                            <Eye size={18} />
                        </button>
                    </div>

                    <div className={styles.divider} />

                    <div className={styles.form}>
                        <InputFieldSelect
                            placeholder="Įmonė (neprivaloma)"
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


                    {customFields.length > 0 &&
                        <>
                            <div className={styles.divider} />
                            <div className={styles.customFields}>
                                <h1>Papildomi laukai</h1>
                                {constantFields.map((field, index) => (
                                    <div key={`${field}-${index}`} className={styles.field}>
                                        <InputFieldText
                                            placeholder={field}
                                            value={typeof customVariables[field] === "string" ? customVariables[field] : ""}
                                            onChange={(value) => updateCustomField(field, value)}
                                        />
                                    </div>
                                ))}

                                {arrayFields.length > 0 && (
                                    <div className={styles.arraySection}>
                                        <p className={styles.arrayTitle}>Eilučių laukai</p>
                                        <div className={styles.arrayGrid}>
                                            <div className={styles.arrayHeaderRow}>
                                                {arrayFields.map((field) => (
                                                    <span key={`head-${field}`} className={styles.arrayHeaderCell}>
                                                        {field}
                                                    </span>
                                                ))}
                                            </div>
                                            {Array.from({ length: arrayRowCount }).map((_, rowIndex) => (
                                                <div key={`row-${rowIndex}`} className={styles.arrayValueRow}>
                                                    {arrayFields.map((field) => (
                                                        <input
                                                            key={`${field}-${rowIndex}`}
                                                            className={styles.arrayInput}
                                                            value={
                                                                Array.isArray(customVariables[field])
                                                                    ? customVariables[field][rowIndex] ?? ""
                                                                    : ""
                                                            }
                                                            onChange={(e) => updateArrayFieldCell(field, rowIndex, e.target.value)}
                                                            placeholder={`${field} ${rowIndex + 1}`}
                                                        />
                                                    ))}
                                                </div>
                                            ))}
                                        </div>
                                        <button
                                            type="button"
                                            className={styles.addArrayRow}
                                            onClick={() => setArrayRowCount((prev) => prev + 1)}
                                        >
                                            +
                                        </button>
                                    </div>
                                )}
                            </div>
                        </>
                    }

                    <button className={styles.submitButton} onClick={createDocument}>
                        <Download size={18} />
                        Sukurti dokumentą
                    </button>
                </div>
                {company &&
                    <CompanyCard id={Number(company)} />
                }
            </div>
        </div>
    );
}