"use client";

import { useEffect, useState } from "react";
import { TemplateApi } from "@/lib/api/templates";
import { CompanyApi } from "@/lib/api/companies";
import type { Company } from "@/lib/types/Company";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import { FileText, Download } from "lucide-react";
import { DirectoryStore, useDirectoryStore } from "@/lib/globalVariables/directoriesToSend";
import PageBackBar from "@/components/navigation/PageBackBar";
import styles from "../[...template]/page.module.scss";

export default function TemplatePage() {

    // bullshit page, temporary replacement for [...template]

    const [directory, setDirectory] = useState([] as string[]);
    const [companies, setCompanies] = useState<Company[]>([]);
    const selectedDirectories = useDirectoryStore((state) => state.selected);
    const [company, setCompany] = useState("");

    useEffect(() => {
        getCompanies();
        setDirectory(selectedDirectories);
        document.title = "Sukurti dokumentus";
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

    async function getCompanies() {
        const data = await CompanyApi.getAll();
        setCompanies(data);
        console.log(data);
    }

    async function createDocument() {
        const { blob, filename } = await TemplateApi.createDocument(Number(company), directory);
        downloadBlob(blob, filename);
    }

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <PageBackBar />
            </div>

            <div className={styles.card}>


                { selectedDirectories.map(d => (
                    <div key={d} className={styles.cardHeader}>
                        <div className={styles.fileIcon}>
                            <FileText size={24} />
                        </div>
                        <div>
                            <h1 className={styles.title}>{d.split("/").pop()}</h1>
                        </div>
                    </div>
                ))
                }

                <div className={styles.divider} />

                <div className={styles.form}>
                    <InputFieldSelect placeholder="Įmonė" onChange={setCompany} options={companies.map(c => ({
                        value: String(c.id),
                        label: `${c.companyType} ${c.companyName}`
                    }))} />
                </div>

                <button className={styles.submitButton} onClick={createDocument}>
                    <Download size={18} />
                    Sukurti dokumentą
                </button>
            </div>
        </div>
    );
}