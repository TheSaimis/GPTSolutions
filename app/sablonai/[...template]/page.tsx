"use client";

// rushed code for features but it works so far

import { useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { TemplateApi } from "@/lib/api/templates";
import { CompanyApi } from "@/lib/api/companies";
import { FilesApi } from "@/lib/api/files";
import { extractUnknownVariablesFromDocx } from "@/lib/functions/wordVariableParser";
import type { Company } from "@/lib/types/Company";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import InputFieldText from "@/components/inputFields/inputFieldText";
import { FileText, Download, ArrowLeft } from "lucide-react";
import { getCachedWordFile, setCachedWordFile } from "@/lib/cache/wordFileCache";
import Link from "next/link";
import styles from "./page.module.scss";

export default function TemplatePage() {

    const { template } = useParams();
    const templatePath = Array.isArray(template) ? template.join("/") : template;
    const fileName = Array.isArray(template) ? template.at(-1) : template;
    const [directory, setDirectory] = useState(decodeURIComponent(templatePath || ""));
    const [documentName, setDocumentName] = useState(fileName);
    const [customFields, setCustomFields] = useState<string[]>([]);
    const [fake, setFake] = useState<string>("");
    const [templateFile, setTemplateFile] = useState<Blob | null>(null);

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

    function downloadBlob(blob: Blob, filename: string) {
        const allowedMimeTypes = [
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "application/msword",
            "application/zip",
            "application/x-zip-compressed",
            "application/octet-stream",
        ];
        const allowedExtensions = [".docx", ".doc"];
        const hasValidMime = allowedMimeTypes.includes(blob.type);
        const hasValidExtension = allowedExtensions.some(ext =>
            filename.toLowerCase().endsWith(ext)
        );
        if (!hasValidMime && !hasValidExtension) {
            return;
        }
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
    }

    useEffect(() => {
        async function getTemplateWord() {
            // cache the files since the user might go back and forth a lot and keep having to download the .docx file again
            const cacheKey = `templates/${directory}`;
            const cachedBlob = getCachedWordFile(cacheKey);
            if (cachedBlob) {
                setTemplateFile(cachedBlob);
                extractUnknownVariablesFromDocx(cachedBlob).then((result) => setCustomFields(result));
                return;
            }
            const { blob } = await FilesApi.downloadFile(cacheKey);
            setCachedWordFile(cacheKey, blob);
            setTemplateFile(blob);
            downloadBlob(blob, "nzn.docx");
            extractUnknownVariablesFromDocx(blob).then((result) => console.log(result));
        }
        getTemplateWord();
    })

    async function getCompanies() {
        const data = await CompanyApi.getAll();
        setCompanies(data);
        console.log(data);
    }

    async function createDocument() {
        const { blob, filename } = await TemplateApi.createDocument(Number(company), [directory]);
        downloadBlob(blob, filename);
    }

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <Link href="/sablonai" className={styles.backLink}>
                    <ArrowLeft size={16} />
                    Grįžti į šablonus
                </Link>
            </div>

            <div className={styles.card}>
                <div className={styles.cardHeader}>
                    <div className={styles.fileIcon}>
                        <FileText size={24} />
                    </div>
                    <div>
                        <h1 className={styles.title}>{documentName}</h1>
                        <p className={styles.subtitle}>Pasirinkite Įmone ir sugeneruokite dokumentą</p>
                    </div>
                </div>

                <div className={styles.divider} />

                <div className={styles.form}>
                    <InputFieldSelect placeholder="Įmonė" onChange={setCompany} options={companies.map(c => ({
                        value: String(c.id),
                        label: `${c.companyType} ${c.companyName}`
                    }))} />
                </div>

                <div>
                    <p>Papildomi laukai</p>
                    {customFields && customFields.map((field, index) => (
                        <div key={index}>
                            <InputFieldText placeholder={field} value={fake} onChange={setFake} />
                        </div>
                    ))}
                </div>

                <button className={styles.submitButton} onClick={createDocument}>
                    <Download size={18} />
                    Sukurti dokumentą
                </button>
            </div>
        </div>
    );
}