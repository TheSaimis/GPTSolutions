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

  async function createFiles(files: File[], path: string, fileType: string) {
    const toUpload: File[] = [];

    for (const file of files) {
      if (
        !file?.name ||
        !(FILE_TYPES as readonly string[]).includes(file.type) ||
        !fileType
      ) {
        continue;
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

            continue;
          }
        }
      } catch (error) {
        console.error("Failed to read file metadata:", error);
      }

      toUpload.push(file);
    }

    if (toUpload.length === 0) {
      return;
    }

    const res = await FilesApi.createFiles(toUpload, path ?? "", fileType);

    for (const item of res.results) {
      if (item.status === "SUCCESS" && item.file) {
        setCatalogueTree((prev) => addFileToTree(prev, path ?? "", { ...item.file }));
      }
    }

    if (res.status === "PARTIAL" || res.status === "FAIL") {
      const failed = res.results.filter((r) => r.status === "FAIL").length;
      if (failed > 0) {
        MessageStore.push({
          title: res.status === "FAIL" ? "Įkėlimas nepavyko" : "Dalį failų nepavyko įkelti",
          message:
            res.results
              .filter((r) => r.status === "FAIL")
              .map((r) => r.error ?? "Nežinoma klaida")
              .slice(0, 5)
              .join("; ") + (failed > 5 ? ` … (+${failed - 5})` : ""),
          backgroundColor: "#e53e3e",
        });
      }
    }
  }

  async function createFile(file: File, path: string, fileType: string) {
    await createFiles([file], path, fileType);
  }

  return { createFile, createFiles };
}
