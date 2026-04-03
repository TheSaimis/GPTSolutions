"use client";

import { ArchiveApi } from "@/lib/api/archive";
import { CatalougeApi } from "@/lib/api/catalouges";
import { TemplateList } from "@/lib/types/TemplateList";
import { useEffect, useState } from "react";
import FileList from "../templateList/fileList";
import DirectoryMenu from "../components/directoryMenu/directoryMenu";
import styles from "../page.module.scss";
import { Download, ExternalLink } from "lucide-react";
import { CatalogueTreeProvider } from "../catalogueTreeContext";
import { getCachedCatalogueTree, setCachedCatalogueTree } from "@/lib/cache/catalogueTreeCache";
import { useRouter } from "next/navigation";
import PageBackBar from "@/components/navigation/PageBackBar";
import { downloadBlob } from "@/lib/functions/downloadBlob";

export default function ArchivedFilesPage() {
    const [templateList, setTemplateList] = useState<TemplateList[]>([]);
    const fileType = "archive";
    const router = useRouter();

    useEffect(() => {
        document.title = "Archyvas";
        async function getGeneratedFiles() {
            const cacheKey = fileType;
            const cachedTree = getCachedCatalogueTree(cacheKey);
            if (cachedTree) {
                setTemplateList(cachedTree);
                return;
            }
            const tree = await ArchiveApi.getAll();
            setCachedCatalogueTree(cacheKey, tree);
            setTemplateList(tree);
        }
        getGeneratedFiles();
    }, []);

    async function downloadCatalogue() {
        const { blob, filename } = await CatalougeApi.catalogueDownload(fileType, "");
        downloadBlob({ blob, filename });
    }

    return (
        <div className={styles.templates}>
            <PageBackBar className={styles.backBar} />
            <div className={styles.header}>
                <div className={styles.headerText}>
                    <h1 className={styles.title}>Archyvas</h1>
                    <p className={styles.subtitle}>
                        Šiame puslapyje galite peržiūrėti ir atsisiųsti sukurtų dokumentų archyvą.
                    </p>
                </div>
                <div className={styles.headerButtons}>
                    <button
                        type="button"
                        onClick={downloadCatalogue}
                        className={styles.downloadCatalogButton}
                    >
                        <Download size={18} />
                        Atsisiųsti katalogą
                    </button>
                    <button
                        type="button"
                        onClick={() => router.push("/sablonai")}
                        className={styles.downloadCatalogButton}
                    >
                        <ExternalLink size={18} />
                        Šablonų katalogas
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
            <CatalogueTreeProvider fileType={fileType} initialTree={templateList}>
                <FileList />
                <DirectoryMenu />
            </CatalogueTreeProvider>
        </div>
    );
}
