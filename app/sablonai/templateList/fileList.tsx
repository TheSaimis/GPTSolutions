"use client";

import styles from "./fileList.module.scss";
import InputFieldText from "@/components/inputFields/inputFieldText";
import Directory from "./types/directory/directory";
import Files from "./types/file/file";
import CreateDirectory from "./types/directory/functions/createDirectory";
import Filters from "../components/filters/filters";
import DropZone from "@/components/inputFields/dropZone";
import InputFieldFile from "@/components/inputFields/inputFieldFile";
import { Search, FolderPlus } from "lucide-react";
import { useCatalogueTree } from "../catalogueTreeContext";
import { useCreateFile } from "./types/directory/functions/createFile";
import { useContextMenu } from "@/components/contextMenu/menuComponents/contextMenuProvider";
import { FILE_TYPES } from "@/lib/types/TemplateList";
import { useEffect, useState, useMemo, useRef } from "react";

export default function FileList() {
    const { catalogueTree, filteredCatalogueTree, filters, setFilters, fileType } = useCatalogueTree();
    const [file, setFile] = useState<File | null>(null);
    const [create, setCreate] = useState(false);
    const { createFile } = useCreateFile();
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { openMenuFromEvent } = useContextMenu();

    useEffect(() => {
        if (file) {
            createFile(file, "", fileType);
            setFile(null);
        }
    }, [file, fileType, createFile]);

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
        ], []);

    return (
        <DropZone accept={FILE_TYPES} onFile={setFile} className={styles.container}>
            <div style={{ display: "none" }}>
                <InputFieldFile ref={fileInputRef} onChange={setFile} value={file} accept={".docx"} />
            </div>
            <div className={styles.templateList} onContextMenu={(e) => openMenuFromEvent(e, menuItems)}>
                <div className={styles.card}>
                    <div>
                        <InputFieldText
                            value={filters.search}
                            onChange={(value) => setFilters((prev) => ({ ...prev, search: value }))}
                            placeholder="Paieška"
                            icon={Search}
                        />
                    </div>
                    <div className={styles.catalogueTree}>
                        {create && (
                            <CreateDirectory fileType={fileType} onFocus={setCreate} />
                        )}
                        {catalogueTree && catalogueTree?.length > 0 ? (filteredCatalogueTree.map((node) =>
                            node.type === "file" ? (
                                <Files
                                    key={node.path ?? node.name}
                                    fileType={fileType}
                                    data={node}
                                />
                            ) : (
                                <Directory
                                    key={node.path ?? node.name}
                                    name={node.name}
                                    children={node.children}
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
            <Filters />
        </DropZone>
    );
}