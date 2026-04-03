"use client";

import FileList from "./templateList/fileList";
import DirectoryMenu from "./components/directoryMenu/directoryMenu";
import styles from "./page.module.scss";
import { getCachedCatalogueTree, setCachedCatalogueTree } from "@/lib/cache/catalogueTreeCache";
import { TemplateApi } from "@/lib/api/templates";
import { TemplateList } from "@/lib/types/TemplateList";
import { useEffect, useState } from "react";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import { Download, ExternalLink } from "lucide-react";
import { CatalogueTreeProvider } from "./catalogueTreeContext";
import { useRouter } from "next/navigation";
import PageBackBar from "@/components/navigation/PageBackBar";
import Link from "next/link";

export default function TemplatePage() {
  const [templateList, setTemplateList] = useState<TemplateList[]>([]);
  const [fileType, setFileType] = useState("templates");
  const router = useRouter();

  useEffect(() => {
    document.title = "Šablonai";
    async function getTemplateList() {
      const cacheKey = fileType;
      const cachedTree = getCachedCatalogueTree(cacheKey);
      if (cachedTree) {
        setTemplateList(cachedTree);
        return;
      }
      const tree = await TemplateApi.getAll();
      setCachedCatalogueTree(cacheKey, tree);
      setTemplateList(tree);
    }
    getTemplateList();
  }, []);

  async function downloadTemplates() {
    const { blob, filename } = await TemplateApi.getTemplatesZip();
    downloadBlob({ blob, filename });
  }

  async function downloadAAP() {
    const { blob, filename } = await TemplateApi.createAPPDocument(1);
    downloadBlob({ blob, filename });
  }

  return (
    <div className={styles.templates}>
      <PageBackBar className={styles.backBar} />
      <DirectoryMenu />
      <div className={styles.header}>
        <div className={styles.headerText}>
          <h1 className={styles.title}>Šablonai</h1>
          <p className={styles.subtitle}>
            Pasirinkite šabloną dokumentui sukurti
          </p>
        </div>
        <div className={styles.headerButtons}>
          <button
            type="button"
            onClick={downloadTemplates}
            className={styles.downloadCatalogButton}
          >
            <Download size={18} />
            Atsisiųsti šablonų katalogą
          </button>

          <button
            type="button"
            onClick={() => router.push("/sablonai/sukurtiDokumentai")}
            className={styles.downloadCatalogButton}
          >
            <ExternalLink size={18} />
            Sukurtų dokumentų katalogas
          </button>
        </div>
      </div>

      <div className={styles.templateNav}>
        <Link className={styles.templateNavLink} href="/sablonai/kiti/AAP">
          AAP
        </Link>
        <Link className={styles.templateNavLink} href="/sablonai/kiti/pazyma">
          Sveikatos tikrinimo pažymos
        </Link>
        <Link
          className={styles.templateNavLink}
          href="/sablonai/patvirtinimai"
        >
          Kenksmingų faktorių nustatymo pažyma
        </Link>
        <Link
          className={styles.templateNavLink}
          href="/sablonai/kiti/Nemokamai-isduodamu-priemoniu-sarasas"
        >
          Nemokamai išduodamų AP sąrašas
        </Link>
      </div>

      <CatalogueTreeProvider fileType={fileType} initialTree={templateList}>
        <FileList />
      </CatalogueTreeProvider>
    </div>
  );
}
