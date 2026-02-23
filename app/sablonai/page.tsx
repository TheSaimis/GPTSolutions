"use client";

import { TemplateApi } from "@/lib/api/templates";
import { TemplateList } from "@/lib/types/TemplateList";
import { useEffect, useState } from "react";
import FileList from "./templateList/fileList";
import styles from "./page.module.scss";

export default function Login() {


    const [templateList, setTemplateList] = useState<TemplateList[]>([]);

    useEffect(() => {
        document.title = "Šablonai";
        getTemplateList();
    }, []);

    async function getTemplateList() {
        const data: TemplateList[] = await TemplateApi.getAll();
        setTemplateList(data[0]);
    }


    return (
        <div className={styles.templates}>
            <div className={styles.header}>
                <h1 className={styles.title}>Šablonai</h1>
                <p className={styles.subtitle}>Pasirinkite šabloną dokumentui sukurti</p>
            </div>
            <div className={styles.card}>
                <div className={styles.templatesList}>
                    {templateList.map((template) => (
                        <FileList key={template.name} name={template.name} type={template.type} children={template.children} directory={template.name} />
                    ))}
                </div>
            </div>
        </div>
    );
}