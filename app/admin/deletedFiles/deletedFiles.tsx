"use client";

import FileList from "@/app/sablonai/templateList/fileList";
import { getCachedCatalogueTree, setCachedCatalogueTree } from "@/lib/cache/catalogueTreeCache";
import { CatalougeApi } from "@/lib/api/catalouges";
import { TemplateList } from "@/lib/types/TemplateList";
import { useEffect, useState } from "react";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import { CatalogueTreeProvider } from "@/app/sablonai/catalogueTreeContext";

type Props = {
    deletedRoot: string
};

export default function DeletedFiles({ deletedRoot }: Props) {
    const [templateList, setTemplateList] = useState<TemplateList[]>([]);
    const [fileType, setFileType] = useState(`deleted`);

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
    }, []);

    async function downloadDeleted() {
        const { blob, filename } = await CatalougeApi.catalogueDownload("deleted", deletedRoot);
        downloadBlob({ blob, filename });
    }

    return (<>
        <button onClick={downloadDeleted} className="buttons">atsisiusti</button>
        <CatalogueTreeProvider fileType={fileType} initialTree={templateList}>
            <FileList overflow={true}/>
        </CatalogueTreeProvider>
    </>
    );
}
