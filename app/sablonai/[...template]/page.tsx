"use client";

// rushed code for features but it works so far

import { useEffect, useState } from "react";
import { TemplateApi } from "@/lib/api/templates";
import { CompanyApi } from "@/lib/api/companies";
import { FilesApi } from "@/lib/api/files";
import { extractUnknownVariablesFromOfficeFile } from "@/lib/functions/wordVariableParser";
import type { CustomVariable, Company } from "@/lib/types/Company";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import InputFieldText from "@/components/inputFields/inputFieldText";
import { FileText, Download } from "lucide-react";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import styles from "./page.module.scss";
import { useParams } from "next/navigation";
import PageBackBar from "@/components/navigation/PageBackBar";
import CompanyCard from "@/components/companyCard/companyCard";

export default function TemplatePage() {
    const { template } = useParams();
    const templatePath = Array.isArray(template) ? template.join("/") : template;
    const fileName = Array.isArray(template) ? template.at(-1) : template;
    const [directory, setDirectory] = useState(decodeURIComponent(templatePath || ""));
    const [documentName, setDocumentName] = useState(fileName);
    const [customFields, setCustomFields] = useState<string[]>([]);
    const [customVariables, setCustomVariables] = useState<CustomVariable>({});
    const [companies, setCompanies] = useState<Company[]>([]);
    const [company, setCompany] = useState("");

    useEffect(() => {
        getCompanies();
        const decoded = decodeURIComponent(templatePath || "");
        const decodedFileName = decodeURIComponent(fileName || "");
        setDocumentName(decodedFileName);
        setDirectory(decoded);
        document.title = decodedFileName;
    }, []);

    useEffect(() => {
        async function getTemplateWord() {
            const cacheKey = `templates/${directory}`;
            const { blob } = await FilesApi.downloadFile(cacheKey);
            const result = await extractUnknownVariablesFromOfficeFile(blob);
            setCustomFields(result);
        }

        getTemplateWord();
    }, [directory]);


    async function getCompanies() {
        const data = await CompanyApi.getAll();
        setCompanies(data);
    }

    function updateCustomField(fieldName: string, value: string) {
        setCustomVariables((prev) => ({
            ...prev,
            [fieldName]: value,
        }));
    }

    async function createDocument() {
        const cleanedCustomVariables = Object.fromEntries(
            Object.entries(customVariables).filter(([, value]) => value.trim() !== "")
        );
        const { blob, filename } = await TemplateApi.createDocument(
            Number(company),
            [directory],
            cleanedCustomVariables
        );
        downloadBlob({ blob, filename });
    }

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <PageBackBar />
            </div>

            <div className={styles.card}>
                <div className={styles.cardHeader}>
                    <div className={styles.fileIcon}>
                        <FileText size={24} />
                    </div>
                    <div>
                        <h1 className={styles.title}>{documentName}</h1>
                        <p className={styles.subtitle}>Pasirinkite Įmonę ir sugeneruokite dokumentą</p>
                    </div>
                </div>

                <div className={styles.divider} />

                <div className={styles.form}>
                    <InputFieldSelect
                        placeholder="Įmonė"
                        onChange={setCompany}
                        options={companies.map((c) => ({
                            value: String(c.id),
                            label: `${c.companyType} ${c.companyName}`,
                        }))}
                    />
                </div>

                {customFields.length > 0 &&
                    <div>
                        <p>Papildomi laukai</p>
                        {customFields.map((field, index) => (
                            <div key={index}>
                                <InputFieldText
                                    placeholder={field}
                                    value={customVariables[field] ?? ""}
                                    onChange={(value) => updateCustomField(field, value)}
                                />
                            </div>
                        ))}
                    </div>
                }
                <button className={styles.submitButton} onClick={createDocument}>
                    <Download size={18} />
                    Sukurti dokumentą
                </button>
            </div>

            { company &&
                <CompanyCard id={Number(company)} />
            }

        </div>
    );
}