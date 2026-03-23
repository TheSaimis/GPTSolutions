"use client";

import FileList from "@/app/sablonai/templateList/fileList";
import { getCachedCatalogueTree, setCachedCatalogueTree } from "@/lib/cache/catalogueTreeCache";
import { CatalougeApi } from "@/lib/api/catalouges";
import { TemplateList } from "@/lib/types/TemplateList";
import { useEffect, useState } from "react";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import { CatalogueTreeProvider } from "@/app/sablonai/catalogueTreeContext";
import { Download } from "lucide-react";
import styles from "./deletedFiles.module.scss";

type Props = {
    deletedRoot: string;
};

export default function DeletedFiles({ deletedRoot }: Props) {
    const [templateList, setTemplateList] = useState<TemplateList[]>([]);
    const [fileType] = useState("deleted");

    useEffect(() => {
        async function getTemplateList() {
            const cacheKey = `${fileType}/${deletedRoot}`;
            const cachedTree = getCachedCatalogueTree(cacheKey);
            if (cachedTree) {
                setTemplateList(cachedTree);
                return;
            }
            const tree = await CatalougeApi.catalogueGetDeleted(deletedRoot);
            setCachedCatalogueTree(cacheKey, tree);
            setTemplateList(tree);
        }
        getTemplateList();
    }, [deletedRoot, fileType]);

    async function downloadDeleted() {
        const { blob, filename } = await CatalougeApi.catalogueDownload("deleted", deletedRoot);
        downloadBlob({ blob, filename });
    }

    return (
        <div className={styles.wrapper}>
            <button onClick={downloadDeleted} type="button" className={styles.downloadButton}>
                <Download size={14} />
                Atsisiųsti visus
            </button>
            <CatalogueTreeProvider fileType={fileType} initialTree={templateList}>
                <FileList overflow={true} />
            </CatalogueTreeProvider>
        </div>
    );
}
