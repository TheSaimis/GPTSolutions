"use client";

import { useRouter } from "next/navigation";
import styles from "../fileList.module.scss";
import { TemplateApi } from "@/lib/api/templates";
import { useRef, useEffect, useState } from "react";
import { File } from "lucide-react";

type List = {
    name: string;
    directory?: string
}

export default function Files({ name, directory }: List) {

    const [collapsed, setCollapsed] = useState<string>("");
    const router = useRouter();

    function clicked() {
        router.push(`/sablonai/${directory}`);
    }


    function previewPDF() {
        TemplateApi.getTemplatePDF(directory).then((res) => {
            console.log(res);
        });
    }


    return (
        <div className={styles.file}>
            <div className={styles.itemContainer}>
                <div className={styles.item} onClick={clicked}>
                    <File className={styles.file} />
                    <p>{name}</p>
                </div>
                <button onClick={previewPDF} className={`${styles.button} buttons`}>Peržiūrėti šabloną</button>
            </div>
            <div className={`${collapsed ? styles.collapsed : ""} ${styles.child}`}>
            </div>
        </div>
    );
}