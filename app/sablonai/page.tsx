"use client";

import { TemplateApi } from "@/lib/api/templates";
import { useParams, useRouter } from "next/navigation";
import styles from "./page.module.scss";
import { useEffect, useState } from "react";
import Link from "next/link";

export default function Login() {

    const [templateList, setTemplateList] = useState<string[]>([]);;
    const router = useRouter()

    useEffect(() => {
        document.title = "Šablonai";
        setTemplateList(["Template1", "Template2", "Template3"]);
    }, []);

    async function getTemplateList() {
        const data = await TemplateApi.getAll();
        setTemplateList(data);
    }

    return (
        <div className={styles.templates}>
            <div className={styles.templatesList}>
                {templateList.map((template) => (
                    <div className={styles.template} key={template}>
                        <Link href={`/sablonas/${template}`} className={`buttons ${styles.templateButton}`} key={template}>{template}</Link>
                        <button className={`buttons ${styles.templateButton}`}>Peržiūrėti šabloną</button>
                    </div>
                ))}
            </div>
        </div>
    );
}