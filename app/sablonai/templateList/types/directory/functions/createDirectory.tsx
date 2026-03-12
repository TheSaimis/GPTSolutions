"use client";

import InputFieldText from "@/components/inputFields/inputFieldText";
import styles from "./functions.module.scss";
import { TemplateList } from "@/lib/types/TemplateList";
import { CatalougeApi } from "@/lib/api/catalouges";
import { useEffect, useRef, useState } from "react";
import { useMessageStore } from "@/lib/globalVariables/messages";
import { addDirectoryToTree } from "@/app/sablonai/components/utilities/addDirectory";
import { useCatalogueTree } from "@/app/sablonai/catalogueTreeContext";

type List = {
    path?: string
    folders?: TemplateList[];
    onFocus?: (b: boolean) => void;
}

export default function CreateDirectory({ path, onFocus, folders }: List) {

    const [folderName, setFolderName] = useState<string>("");
    const [focused, setFocused] = useState<boolean>(true);
    const { catalogueTree, setCatalogueTree } = useCatalogueTree();
    const inputRef = useRef<HTMLInputElement>(null);

    async function createDirectory() {
        if (!folderName) return;

        if (folders?.find((folder) => folder.name === folderName)) {
            useMessageStore.getState().push({
                title: "Klaida",
                message: "Toks katalogas jau egzistuoja",
            })
            return;
        };

        const res = await CatalougeApi.catalougeCreate(path ?? "", folderName);
        if (res.status != "SUCCESS") return;

        setCatalogueTree((prev) =>
            addDirectoryToTree(prev, path ?? "", folderName)
        );

        onFocus?.(false);
        // onUpload?.((prev) => [...prev, newNode]);
    }

    function clearState() {
        onFocus?.(false);
    }

    useEffect(() => {
        inputRef.current?.focus();
        console.log(folders);
    }, []);

    useEffect(() => {
        if (!focused) onFocus?.(false);
    }, [focused]);

    return (
        <div className={styles.createDirectoryContainer}>
            <div className={`${styles.inputContainer} ${focused ? "" : styles.create}`}>
                <InputFieldText ref={inputRef} placeholder="Naujas katalogas" value={folderName} onChange={setFolderName} onFocus={setFocused} onKeyDown={{ Enter: createDirectory, Escape: () => clearState() }} />
            </div>
        </div>
    );
}