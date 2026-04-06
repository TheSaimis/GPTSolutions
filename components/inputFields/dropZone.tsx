"use client";

import { useState } from "react";
import styles from "./styles/dropZone.module.scss";
import { MessageStore } from "@/lib/globalVariables/messages";
import { TEMPLATE_FILE_DRAG_MIME } from "@/app/sablonai/components/utilities/moveFileInTree";

type Props = {
  /** Called with every dropped file that passes `accept` (may be empty). */
  onFiles: (files: File[]) => void;
  children: React.ReactNode;
  accept?: readonly string[];
  className?: string;
  disabled?: boolean;
};

export default function DropZone({
  onFiles,
  children,
  accept,
  className,
  disabled = false,
}: Props) {
  const [dragOver, setDragOver] = useState(false);

  function isAccepted(file: File) {
    if (!accept || accept.length === 0) return true;

    return accept.some((rule) => {
      const value = rule.toLowerCase();

      if (value.startsWith(".")) {
        return file.name.toLowerCase().endsWith(value);
      }

      return file.type.toLowerCase() === value;
    });
  }

  function onDragOver(e: React.DragEvent) {
    if (disabled) return;
    e.preventDefault();
    e.stopPropagation();
    setDragOver(true);
  }

  function onDragLeave(e: React.DragEvent) {
    if (disabled) return;
    e.stopPropagation();
    setDragOver(false);
  }

  function onDrop(e: React.DragEvent) {
    if (disabled) return;
    e.preventDefault();
    e.stopPropagation();
    setDragOver(false);

    // Internal catalogue file drag (handled by folder targets), not an OS file drop
    if (Array.from(e.dataTransfer.types).includes(TEMPLATE_FILE_DRAG_MIME)) {
      return;
    }

    const list = e.dataTransfer.files;
    if (!list || list.length === 0) {
      return;
    }

    const all = Array.from(list);
    const accepted = all.filter(isAccepted);
    const rejected = all.length - accepted.length;

    if (accepted.length === 0) {
      MessageStore.push({
        title: "Netinkami failai",
        message: accept?.length
          ? `Nė vienas failas netinka. Leidžiami formatai: ${accept.join(", ")}`
          : "Nė vienas failas netinka.",
        backgroundColor: "#e53e3e",
      });
      return;
    }

    if (rejected > 0) {
      MessageStore.push({
        title: "Dalį failų praleista",
        message: `${rejected} failų neatitinka formato ir nebuvo įkelti.`,
        backgroundColor: "#f59e0b",
      });
    }

    onFiles(accepted);
  }

  return (
    <div
      className={`${className ?? ""} ${styles.dropZone} ${dragOver ? styles.dragOver : ""}`}
      onDragOver={onDragOver}
      onDragLeave={onDragLeave}
      onDrop={onDrop}
    >
      {children}
    </div>
  );
}
