"use client";

import { GeneratedFilesApi } from "@/lib/api/generatedFiles";
import { TemplateList } from "@/lib/types/TemplateList";
import { useEffect, useState } from "react";
import FileList from "../templateList/fileList";
import DirectoryMenu from "../components/directoryMenu/directoryMenu";
import styles from "../page.module.scss";
import { Download } from "lucide-react";
import { CatalogueTreeProvider } from "../catalogueTreeContext";

export default function GeneratedFilesPage() {
  const [templateList, setTemplateList] = useState<TemplateList[]>([]);

  useEffect(() => {
    document.title = "Sukurti failai";
    async function getGeneratedFiles() {
      await GeneratedFilesApi.getAll().then((data) => setTemplateList(data));
    }
    getGeneratedFiles();
  }, []);

  async function downloadCatalogue() {
    const { blob, filename } = await GeneratedFilesApi.getAllZip();
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename || "templates.zip";
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  return (
    <div className={styles.templates}>
      <div className={styles.header}>
        <div className={styles.headerText}>
          <h1 className={styles.title}>Dokumentai</h1>
          <p className={styles.subtitle}>
            Pasirinkite veiksmą, kurį norite atlikti
          </p>
        </div>
        <button
          type="button"
          onClick={downloadCatalogue}
          className={styles.downloadCatalogButton}
        >
          <Download size={18} />
          Atsisiųsti katalogą
        </button>
      </div>
      <CatalogueTreeProvider initialTree={templateList}>
        <FileList fileType="generated" />
        <DirectoryMenu />
      </CatalogueTreeProvider>
    </div>
  );
}
