"use client";

import { CatalougeApi } from "@/lib/api/catalouges";
import { useConfirmAction } from "@/components/confirmationPanel/confirmationPanel";
import { Trash } from "lucide-react";
import { removeDirectoryFromTree } from "@/app/sablonai/components/utilities/removeDirectory";
import { useCatalogueTree } from "@/app/sablonai/catalogueTreeContext";

export function useDeleteFolder() {
  const { setCatalogueTree } = useCatalogueTree();
  const { confirmAction } = useConfirmAction();

  async function deleteFolder(fileType: string, path: string) {
    const confirmed = await confirmAction({
      type: "delete",
      title: "Ištrinti katalogą?",
      message: "Ištrynus katalogą jo atkurti nepavyks.",
      confirmText: "Ištrinti",
      cancelText: "Atšaukti",
      icon: Trash,
    });

    if (!confirmed) return;

    const res = await CatalougeApi.catalogueDelete(fileType ?? "", path ?? "");

    if (res.status === "SUCCESS") {
      setCatalogueTree((prev) => removeDirectoryFromTree(prev, path ?? ""));
    }
  }

  return { deleteFolder };
}