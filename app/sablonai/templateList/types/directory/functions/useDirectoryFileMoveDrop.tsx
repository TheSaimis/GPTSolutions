"use client";

import { useCallback } from "react";
import { FilesApi } from "@/lib/api/files";
import { MessageStore } from "@/lib/globalVariables/messages";
import {
  moveFileInTree,
  TEMPLATE_FILE_DRAG_MIME,
  type TemplateFileDragPayload,
} from "@/app/sablonai/components/utilities/moveFileInTree";
import { useCatalogueTree } from "@/app/sablonai/catalogueTreeContext";

type Params = {
  role: string | null;
  fileType: string;
  path?: string;
};

export function useDirectoryFileMoveDrop({ role, fileType, path }: Params) {
  const { setCatalogueTree } = useCatalogueTree();

  const onDirectoryDragOver = useCallback(
    (e: React.DragEvent) => {
      if (role !== "ROLE_ADMIN") return;
      e.preventDefault();
      e.dataTransfer.dropEffect = "move";
    },
    [role],
  );

  const onDirectoryDrop = useCallback(
    async (e: React.DragEvent) => {
      if (role !== "ROLE_ADMIN") return;

      e.preventDefault();

      try {
        const raw =
          e.dataTransfer.getData(TEMPLATE_FILE_DRAG_MIME) ||
          e.dataTransfer.getData("text/plain");
        if (!raw) return;
        const payload = JSON.parse(raw) as TemplateFileDragPayload;
        if (!payload?.path || !payload?.fileType) return;
        if (payload.fileType !== fileType) return;

        const targetDirectory = (path ?? "").replace(/^\/+|\/+$/g, "");
        const currentDirectory = payload.path.includes("/")
          ? payload.path
              .slice(0, payload.path.lastIndexOf("/"))
              .replace(/^\/+|\/+$/g, "")
          : "";
        if (currentDirectory === targetDirectory) return;

        const res = await FilesApi.changeDirectory(
          fileType,
          payload.path,
          targetDirectory,
        );
        if (res.status !== "SUCCESS") {
          MessageStore.push({
            title: "Nepavyko perkelti failo",
            message: res.error ?? "Serveris atmetė failo perkėlimą.",
            backgroundColor: "#e53e3e",
          });
          return;
        }

        setCatalogueTree((prev) =>
          moveFileInTree(prev, payload.path, targetDirectory),
        );
      } catch {
        MessageStore.push({
          title: "Klaida",
          message: "Nepavyko perkelti failo.",
          backgroundColor: "#e53e3e",
        });
      }
    },
    [fileType, path, role, setCatalogueTree],
  );

  return { onDirectoryDragOver, onDirectoryDrop };
}
