"use client";

import { readDocxMetadataFromBlob } from "@/lib/functions/metadataParser";
import { addFileToTree } from "@/app/sablonai/components/utilities/addFile";
import { useCatalogueTree } from "@/app/sablonai/catalogueTreeContext";
import { FilesApi } from "@/lib/api/files";
import { FILE_TYPES, type TemplateList } from "@/lib/types/TemplateList";
import { MessageStore } from "@/lib/globalVariables/messages";

function findFileByTemplateId(
  nodes: TemplateList[],
  templateId: string
): TemplateList | null {
  for (const node of nodes) {
    if (
      node.type === "file" &&
      node.metadata?.custom?.templateId === templateId
    ) {
      return node;
    }

    if (node.type === "directory" && node.children?.length) {
      const found = findFileByTemplateId(node.children, templateId);
      if (found) return found;
    }
  }

  return null;
}

export function useCreateFile() {
  const { setCatalogueTree, catalogueTree, setFilters } = useCatalogueTree();

  async function createFile(file: File, path: string, fileType: string) {
    if (!file?.name || !FILE_TYPES.includes(file.type as any) || !fileType) {
      return;
    }

    try {
      const parsedMetadata = await readDocxMetadataFromBlob(file);
      const templateId = parsedMetadata.custom?.templateId ?? "";

      if (templateId && fileType !== "generated") {
        const existingFile = findFileByTemplateId(catalogueTree ?? [], templateId);

        if (existingFile) {
          setFilters((prev) => ({
            ...prev,
            search: existingFile.name,
          }));

          MessageStore.push({
            title: "Toks šablonas jau egzistuoja",
            message: `Rastas failas su tuo pačiu ID metaduomenyse: ${existingFile.name}. Norint šitą failą atnaujinti, ji ištrinkite ir įkelkite naują.`,
            backgroundColor: "#e53e3e",
          });

          return;
        }
      }
    } catch (error) {
      console.error("Failed to read file metadata:", error);
    }

    const res = await FilesApi.createFile(file, path ?? "", fileType);

    if (res.status === "SUCCESS" && res.file) {
      const fileNode = {
        ...res.file,
      };

      setCatalogueTree((prev) => addFileToTree(prev, path ?? "", fileNode));
    }
  }

  return { createFile };
}