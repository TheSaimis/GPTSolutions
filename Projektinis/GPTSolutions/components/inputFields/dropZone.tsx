"use client";

import { useState } from "react";
import styles from "./styles/dropZone.module.scss";
import { MessageStore } from "@/lib/globalVariables/messages";

type Props = {
  onFile: (file: File) => void;
  children: React.ReactNode;
  accept?: readonly string[];
  className?: string;
  disabled?: boolean;
};

export default function DropZone({
  onFile,
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

    const file = e.dataTransfer.files?.[0];
    if (!file) return;

    if (isAccepted(file)) {
      onFile(file);
      return;
    }

    MessageStore.push({
      title: "Netinkamas failas",
      message: accept?.length
        ? `Šis failo formatas netinka. Leidžiami formatai: ${accept.join(", ")}`
        : "Šis failo formatas netinka.",
      backgroundColor: "#e53e3e",
    });
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