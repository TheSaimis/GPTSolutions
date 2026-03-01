"use client";

import { useRouter } from "next/navigation";
import styles from "../fileList.module.scss";
import { TemplateApi } from "@/lib/api/templates";
import CheckBox from "@/components/inputFields/checkBox";
import { File } from "lucide-react";
import { setPDFToView } from "@/lib/globalVariables/pdfToView";
import { DirectoryStore, useDirectoryStore } from "@/lib/globalVariables/directoriesToSend";

type List = {
    name: string;
    directory: string;
};

export default function Files({ name, directory }: List) {

    const router = useRouter();
    const selected = useDirectoryStore((s) => s.isSelected(directory));

    function clicked() {
        router.push(`/sablonai/${directory}`);
    }

    function previewPDF() {
        TemplateApi.getTemplatePDF(directory).then((res: any) => {
            setPDFToView(res);
        });
    }

    return (
        <div className={`${styles.files} ${selected ? styles.selected : ""}`}>
            <div className={styles.itemContainer}>
                <div className={styles.item} onClick={clicked}>
                    <File className={styles.file} />
                    <p>{name}</p>
                </div>

                <div className={styles.inputContainer}>
                    <button onClick={previewPDF} className={`${styles.button} buttons`}>
                        Peržiūrėti šabloną
                    </button>

                    <CheckBox
                        value={selected}
                        onChange={(checked: boolean) => {
                            if (checked) DirectoryStore.add(directory);
                            else DirectoryStore.remove(directory);
                        }}
                    />
                </div>
            </div>
        </div>
    );
}