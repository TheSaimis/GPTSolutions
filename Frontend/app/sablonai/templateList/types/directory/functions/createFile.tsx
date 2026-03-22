"use client";

import { addFileToTree } from "@/app/sablonai/components/utilities/addFile";
import { useCatalogueTree } from "@/app/sablonai/catalogueTreeContext";
import { FilesApi } from "@/lib/api/files";
import { FILE_TYPES } from "@/lib/types/TemplateList";

export function useCreateFile() {
    const { setCatalogueTree } = useCatalogueTree();
    async function createFile(file: File, path: string, fileType: string) {
        if (
            file?.name &&
            FILE_TYPES.includes(file.type as any) &&
            path &&
            fileType
        ) {
            const res = await FilesApi.createFile(file, path ?? "", fileType);

            if (res.status === "SUCCESS" && res.file) {
                const fileNode = {
                    ...res.file,
                };
                setCatalogueTree((prev) =>
                    addFileToTree(prev, path ?? "", fileNode)
                );
            }
        }
    }
    return { createFile };
}