"use client";

import { TemplateApi } from "@/lib/api/templates";
import { TemplateList } from "@/lib/types/TemplateList";
import { useEffect, useState } from "react";
import FileList from "./templateList/fileList";
import DirectoryMenu from "./components/directoryMenu/directoryMenu";
import styles from "./page.module.scss";
import { Download, ExternalLink } from "lucide-react";
import { CatalogueTreeProvider } from "./catalogueTreeContext";
import { useRouter } from "next/navigation";

export default function TemplatePage() {


    const [templateList, setTemplateList] = useState<TemplateList[]>([]);
    const router = useRouter();

    useEffect(() => {
        document.title = "Šablonai";
        getTemplateList();
    }, []);

    async function getTemplateList() {
        const data: TemplateList[] = await TemplateApi.getAll();
        setTemplateList(data);
    }

    async function downloadTemplates() {
        const { blob, filename } = await TemplateApi.getTemplatesZip();
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = filename || "templates.zip";
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }

    return (
        <div className={styles.templates}>
            <DirectoryMenu />
            <div className={styles.header}>
                <div className={styles.headerText}>
                    <h1 className={styles.title}>Šablonai</h1>
                    <p className={styles.subtitle}>Pasirinkite šabloną dokumentui sukurti</p>
                </div>
                <div className={styles.headerButtons}>
                    <button type="button" onClick={downloadTemplates} className={styles.downloadCatalogButton}>
                        <Download size={18} />
                        Atsisiųsti šablonų katalogą
                    </button>
                    <button type="button" onClick={() => router.push("/sablonai/sukurtiDokumentai")} className={styles.downloadCatalogButton}>
                        <ExternalLink size={18} />
                        Sukurtų dokumentų katalogas
                    </button>
                </div>
            </div>

                    <CatalogueTreeProvider initialTree={templateList}>
                        <FileList fileType="templates"/>
                    </CatalogueTreeProvider>

        </div>
    );
}