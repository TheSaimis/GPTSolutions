"use client";

import styles from "./fileList.module.scss";
import InputFieldText from "@/components/inputFields/inputFieldText";
import Directory from "./types/directory/directory";
import Files from "./types/file/file";
import CreateDirectory from "./types/directory/functions/createDirectory";
import Filters from "../components/filters/filters";
import DropZone from "@/components/inputFields/dropZone";
import InputFieldFile from "@/components/inputFields/inputFieldFile";
import { Search, FolderPlus, SlidersHorizontal } from "lucide-react";
import { useCatalogueTree } from "../catalogueTreeContext";
import { useCreateFile } from "./types/directory/functions/createFile";
import { useCreateLink } from "./types/directory/functions/createLink";
import { useContextMenu } from "@/components/contextMenu/menuComponents/contextMenuProvider";
import { FILE_TYPES } from "@/lib/types/TemplateList";
import { useEffect, useState, useMemo, useRef, useCallback } from "react";

type FileListProps = {
    overflow?: boolean;
};

export default function FileList({ overflow }: FileListProps) {
    const { catalogueTree, filteredCatalogueTree, filters, setFilters, fileType } = useCatalogueTree();
    const [file, setFile] = useState<File | null>(null);
    const [create, setCreate] = useState(false);
    const [filtersOpen, setFiltersOpen] = useState(false);
    const { createFile, createFiles } = useCreateFile();
    const { openCreateLinkModal, linkModal } = useCreateLink();
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { openMenuFromEvent } = useContextMenu();

    useEffect(() => {
        if (!file) {
            return;
        }
        let cancelled = false;
        void (async () => {
            await createFile(file, "", fileType);
            if (!cancelled) {
                setFile(null);
            }
        })();
        return () => {
            cancelled = true;
        };
    }, [file, fileType, createFile]);

    const handleDroppedFiles = useCallback(
        async (files: File[]) => {
            await createFiles(files, "", fileType);
        },
        [createFiles, fileType],
    );

    const menuItems = useMemo(
        () => [
            {
                id: "newFolder",
                label: "Naujas aplankas",
                onClick: () => {
                    setCreate(true);
                },
            },
            {
                id: "newTemplate",
                label: "Naujas failas",
                onClick: () => {
                    fileInputRef.current?.click();
                },
            },
            {
                id: "newLink",
                label: "Nauja nuoroda",
                onClick: () => {
                    openCreateLinkModal("", fileType);
                },
            },
        ], [openCreateLinkModal, fileType]);

    return (
        <DropZone accept={FILE_TYPES} onFiles={handleDroppedFiles} className={styles.container}>
            <div style={{ display: "none" }}>
                <InputFieldFile ref={fileInputRef} onChange={setFile} value={file} accept={".docx"} />
            </div>
            <div className={styles.templateList} onContextMenu={(e) => openMenuFromEvent(e, menuItems)}>
                <div className={styles.card}>
                    <div className={styles.search}>
                        <InputFieldText
                            value={filters.search}
                            onChange={(value) => setFilters((prev) => ({ ...prev, search: value }))}
                            placeholder="Paieška"
                            icon={Search}
                        />
                        <button
                            type="button"
                            className={styles.filterToggle}
                            onClick={() => setFiltersOpen(true)}
                        >
                            <SlidersHorizontal size={18} />
                            Filtrai
                        </button>
                    </div>
                    <div className={`${styles.catalogueTree} ${overflow ? styles.overflow : ""}`}>
                        {create && (
                            <CreateDirectory fileType={fileType} onFocus={setCreate} />
                        )}
                        {catalogueTree && catalogueTree?.length > 0 ? (filteredCatalogueTree.map((node) =>
                            node.type === "file" ? (
                                <Files
                                    key={`${node.name}-${node.type}-${node.path}`}
                                    fileType={fileType}
                                    data={node}
                                />
                            ) : (
                                <Directory
                                    key={node.path ?? node.name}
                                    name={node.name}
                                    nodes={node.children}
                                    path={node.path}
                                    fileType={fileType}
                                />
                            )
                        )) :
                            <div className={styles.empty}>
                                <h1>Katalogas tuščias</h1>
                                <CreateDirectory placeholder={"Naujas katalogas"} path={""} icon={FolderPlus} fileType={fileType} onFocus={setCreate} />
                                <InputFieldFile placeholder="Naujas dokumentas" ref={fileInputRef} onChange={setFile} value={file} accept={".docx"} />
                            </div>
                        }
                    </div>
                </div>
            </div>
            <Filters isOpen={filtersOpen} onClose={() => setFiltersOpen(false)} />
            {linkModal}
        </DropZone>
    );
}