"use client";

import { useRouter } from "next/navigation";
import styles from "../../fileList.module.scss";
import { TemplateApi } from "@/lib/api/templates";
import CheckBox from "@/components/inputFields/checkBox";
import { File } from "lucide-react";
import { setPDFToView } from "@/lib/globalVariables/pdfToView";
import { DirectoryStore, useDirectoryStore } from "@/lib/globalVariables/directoriesToSend";
import { useContextMenu } from "@/components/contextMenu/menuComponents/contextMenuProvider";
import { useEffect, useRef, useState } from "react";
import InputFieldText from "@/components/inputFields/inputFieldText";

type List = {
    name: string;
    directory: string;
};

export default function Files({ name, directory }: List) {

    const router = useRouter();
    const selected = useDirectoryStore((s) => s.isSelected(directory));
    const [rename, setRename] = useState<boolean>(false);
    const [currentName, setCurrentName] = useState<string>(name);
    const [focused, setFocused] = useState<boolean>(false);
    const [newName, setNewName] = useState<string>(name);
    const { openMenuFromEvent } = useContextMenu();
    const inputRef = useRef<HTMLInputElement>(null);

    function clicked() {
        router.push(`/sablonai/${directory}`);
    }

    function previewPDF() {
        TemplateApi.getTemplatePDF(directory).then((res: any) => {
            setPDFToView(res);
        });
    }

    function renameTemplate() {
        TemplateApi.renameTemplate(directory, newName).then((res) => {
            if (res.status === "SUCCESS") {
                setRename(false)
                setCurrentName(newName);
            };
        })
    }

    useEffect(() => {
            inputRef.current?.focus();
    }, [rename]);

    return (

        <div className={`${styles.files} ${selected ? styles.selected : ""}`} onContextMenu={(e) => openMenuFromEvent(e, [
            {
                id: "open",
                label: "Atidaryti",
                onClick: clicked
            },
            {
                id: "preview",
                label: "Peržiūrėti šabloną",
                onClick: previewPDF
            },
            {
                id: "rename",
                label: "Pervadinti",
                onClick: () => { setRename(true); inputRef.current?.focus(); }
            },
            {
                id: "add",
                label: "Pasirinkti",
                onClick: () => DirectoryStore.add(directory)
            },
            {
                id: "delete",
                label: `Ištrinti šabloną ${currentName}`,
                onClick: () => DirectoryStore.remove(directory)
            }
        ])}>



            <div className={styles.itemContainer}>
                <div className={styles.item} onClick={clicked}>
                    <File className={styles.file} />
                    {rename ?
                        <div onClick={(e) => e.stopPropagation()}>
                            <InputFieldText ref={inputRef} value={newName} onFocus={setRename} onChange={setNewName} onKeyDown={{ "Enter": renameTemplate, Escape: () => setRename(false) }} />
                        </div>
                        :
                        <p>{currentName}</p>
                    }

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