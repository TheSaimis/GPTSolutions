"use client";

import { useEffect, useState } from "react";
import styles from "./styles/dropZone.module.scss";

type Props = {
    onFile: (file: File) => void;
    children: React.ReactNode;
    accept?: string;
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
        if (!accept) return true;

        // supports ".docx,.pdf"
        const exts = accept.split(",").map(e => e.trim().toLowerCase());

        return exts.some(ext =>
          file.name.toLowerCase().endsWith(ext)
        );
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

        onFile(file);
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