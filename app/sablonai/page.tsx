"use client";

import { TemplateApi } from "@/lib/api/templates";
import { TemplateList } from "@/lib/types/TemplateList";
import { useEffect, useState } from "react";
import FileList from "./templateList/fileList";
import DirectoryMenu from "./menus/directoryMenu/directoryMenu";
import styles from "./page.module.scss";

export default function TemplatePage() {


    const [templateList, setTemplateList] = useState<TemplateList[]>([]);

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
            <DirectoryMenu/>
            <div className={styles.header}>
                <h1 className={styles.title}>Šablonai</h1>
                <p className={styles.subtitle}>Pasirinkite šabloną dokumentui sukurti</p>
            </div>
            <div className={styles.card}>

                <button onClick={downloadTemplates} className={`${styles.button} buttons`}>Atsiusti šablonų katalogą</button>

                <div className={styles.templatesList}>
                    {templateList.map((template) => (
                        <FileList key={template.name} name={template.name} type={template.type} children={template.children} directory={template.name} />
                    ))}
                </div>
            </div>
        </div> 
    );
}