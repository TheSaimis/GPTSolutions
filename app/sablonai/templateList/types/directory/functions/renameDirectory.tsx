"use client";

import InputFieldText from "@/components/inputFields/inputFieldText";
import styles from "./functions.module.scss";
import { TemplateList } from "@/lib/types/TemplateList";
import { CatalougeApi } from "@/lib/api/catalouges";
import { useEffect, useRef, useState } from "react";
import { useMessageStore } from "@/lib/globalVariables/messages";
import { useCatalogueTree } from "@/app/sablonai/catalogueTreeContext";
import { renameDirectoryInTree } from "@/app/sablonai/components/utilities/renameDirectory";

type List = {
    name?: string;
    path?: string;
    folders?: TemplateList[];
    onFocus?: (b: boolean) => void;
}

export default function RenameDirectory({ name, path, onFocus, folders }: List) {

    const [folderName, setFolderName] = useState<string>(name ?? "");
    const [focused, setFocused] = useState<boolean>(true);
    const { setCatalogueTree } = useCatalogueTree();
    const inputRef = useRef<HTMLInputElement>(null);

    async function renameDirectory() {
        if (!folderName) return;
        if (folders?.find((folder) => folder.name == folderName)) {
            useMessageStore.getState().push({
                title: "Klaida",
                message: "Toks katalogas jau egzistuoja",
            })
            return;
        };
        const res = await CatalougeApi.catalogueRename(path ?? "", folderName);
        if (res.status != "SUCCESS") return;

        setCatalogueTree((prev) =>
            renameDirectoryInTree(prev, path ?? "", folderName)
        );
        onFocus?.(false);
    }

    function clearState() {
        onFocus?.(false);
    }

    useEffect(() => {
        inputRef.current?.focus();
    }, []);

    useEffect(() => {
        if (!focused) onFocus?.(false);
    }, [focused]);

    return (
        <div onClick={(e) => e.stopPropagation()} className={styles.createDirectoryContainer}>
            <div className={`${styles.inputContainer} ${focused ? "" : styles.create}`}>
                <InputFieldText ref={inputRef} value={folderName} onChange={setFolderName} onFocus={setFocused} onKeyDown={{ Enter: renameDirectory, Escape: () => clearState() }} />
            </div>
        </div>
    );
}