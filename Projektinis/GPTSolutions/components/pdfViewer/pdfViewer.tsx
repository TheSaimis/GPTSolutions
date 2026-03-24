"use client";

import { useEffect, useMemo } from "react";
import { usePDFToView } from "@/lib/globalVariables/pdfToView";
import { X } from "lucide-react";
import styles from "./pdfViewer.module.scss";

export default function PdfViewer() {

    const blob = usePDFToView((s) => s.blob);

    const url = useMemo(() => {
        if (!blob) return "";
        if (blob.type !== "application/pdf") {
          return "";
        }
        return URL.createObjectURL(blob);
      }, [blob]);

    useEffect(() => {
        return () => {
            if (url) URL.revokeObjectURL(url);
        };
    }, [url]);

    useEffect(() => {
        function handleKeyDown(e: KeyboardEvent) {
          if (e.key == "Escape" || e.key === "Esc") {
            usePDFToView.setState({ blob: null });
          }
        }
    
        window.addEventListener("keydown", handleKeyDown);
        return () => {
          window.removeEventListener("keydown", handleKeyDown);
        };
    
      }, []);

    if (!url) return null;

    return (
        <div className={styles.container}>
            <div onClick={() => usePDFToView.setState({ blob: null })} className={`${styles.pdfViewer} ${blob ? styles.open : ""}`}>
                <button onClick={(e) => { usePDFToView.setState({ blob: null }), e.stopPropagation() }}><X /></button>
                <iframe
                    src={url}
                    style={{ width: "100%", height: "100vh", border: "none" }}
                    title="PDF preview"
                />
            </div>
        </div>
    );
}