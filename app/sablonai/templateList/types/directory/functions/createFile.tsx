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

  async function createFromZipUpload(zip: File, path: string, fileType: string) {
    const res = await FilesApi.createFromZip(zip, path ?? "", fileType);

    if (res.error && (res.results?.length ?? 0) === 0) {
      MessageStore.push({
        title: "ZIP įkėlimas nepavyko",
        message: res.error,
        backgroundColor: "#e53e3e",
      });
      return;
    }

    for (const item of res.results ?? []) {
      if (item.status === "SUCCESS" && item.file) {
        setCatalogueTree((prev) => addFileToTree(prev, path ?? "", { ...item.file }));
      }
    }

    const skipped = res.skipped?.length ?? 0;
    if (skipped > 0) {
      MessageStore.push({
        title: "Archyvas išfiltruotas",
        message: `Praleista ${skipped} failų (ne Word/Excel arba sisteminiai / nesaugūs keliai).`,
        backgroundColor: "#3182ce",
      });
    }

    const failed = (res.results ?? []).filter((r) => r.status === "FAIL");
    if (failed.length > 0) {
      MessageStore.push({
        title: res.status === "FAIL" ? "ZIP įkėlimas nepavyko" : "Dalį ZIP failų nepavyko įkelti",
        message: failed
          .map((r) => r.error ?? r.source ?? "Nežinoma klaida")
          .slice(0, 5)
          .join("; "),
        backgroundColor: "#e53e3e",
      });
    }
  }

  async function createFiles(files: File[], path: string, fileType: string) {
    const zips = files.filter((f) => f.name.toLowerCase().endsWith(".zip"));
    const nonZips = files.filter((f) => !f.name.toLowerCase().endsWith(".zip"));

    for (const zip of zips) {
      await createFromZipUpload(zip, path, fileType);
    }

    const toUpload: File[] = [];

    for (const file of nonZips) {
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
