"use client";

import styles from "./fileList.module.scss";
import InputFieldText from "@/components/inputFields/inputFieldText";
import Directory from "./types/directory/directory";
import Files from "./types/file/file";
import CreateDirectory from "./types/directory/functions/createDirectory";
import Filters from "../components/filters/filters";
import DropZone from "@/components/inputFields/dropZone";
import InputFieldFile from "@/components/inputFields/inputFieldFile";
import { Search } from "lucide-react";
import { useCatalogueTree } from "../catalogueTreeContext";
import { useCreateFile } from "./types/directory/functions/createFile";
import { useContextMenu } from "@/components/contextMenu/menuComponents/contextMenuProvider";
import { useEffect, useState, useMemo, useRef } from "react";

type FileListProps = {
    fileType: string;
};

export default function FileList({ fileType }: FileListProps) {
    const { filteredCatalogueTree, filters, setFilters } = useCatalogueTree();
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
        <DropZone accept=".docx" onFile={setFile} className={styles.container}>
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
                        {filteredCatalogueTree.map((node) =>
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
                        )}
                    </div>
                </div>
            </div>
            <Filters />
        </DropZone>
    );
}