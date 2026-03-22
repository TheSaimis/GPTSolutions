"use client";

import styles from "../../fileList.module.scss";
import { TemplateList } from "@/lib/types/TemplateList";
import { useRef, useEffect, useState, useMemo } from "react";
import { ChevronDown, Folder, ArrowUpToLine } from "lucide-react";
import Files from "../file/file";
import InputFieldFile from "@/components/inputFields/inputFieldFile";
import DropZone from "@/components/inputFields/dropZone";
import CreateDirectory from "./functions/createDirectory";
import RenameDirectory from "./functions/renameDirectory";
import { useContextMenu } from "@/components/contextMenu/menuComponents/contextMenuProvider";
import { useDeleteFolder } from "./functions/deleteDirectory";
import { useCreateFile } from "./functions/createFile";
import { CatalougeApi } from "@/lib/api/catalouges";
import { downloadBlob } from "@/lib/functions/downloadBlob";

type DirectoryList = {
    name: string;
    fileType: string,
    children?: TemplateList[]
    path?: string
}

export default function Directory({ name, children, path, fileType }: DirectoryList) {

    const [collapsed, setCollapsed] = useState<boolean>(false);
    const [rename, setRename] = useState<boolean>(false);
    const [create, setCreate] = useState<boolean>(false);
    const [file, setFile] = useState<File | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { openMenuFromEvent } = useContextMenu();
    const { deleteFolder } = useDeleteFolder();
    const { createFile } = useCreateFile();
    const role = localStorage.getItem("role");
    function clicked() {
        setCollapsed(!collapsed);
    }

    useEffect(() => {
        if (file) {
            createFile(file, path ?? "", fileType);
            setFile(null);
        }
    }, [file, fileType, path, createFile]);

    async function downloadFolder() {
        const res = await CatalougeApi.catalogueDownload(fileType ?? "", path ?? "");
        downloadBlob(res);
    }

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
                id: "downloadFolder",
                label: "Atsisiunti aplanką",
                onClick: () => {
                    downloadFolder();
                },
            },
            ...(role === "ROLE_ADMIN" ? [
                {
                    id: "renameFolder",
                    label: `Pervadinti aplanką "${name}"`,
                    onClick: () => {
                        setRename(true);
                    },
                },
                {
                    id: "deleteFolder",
                    label: `Ištrinti aplanką "${name}"`,
                    onClick: () => {
                        deleteFolder(fileType, path ?? "");
                    },
                },
            ] : [])
        ],
        [name, fileType, path, deleteFolder]
    );

    return (
        <DropZone onFile={setFile} accept={[".docx", ".xlsx"]} className={styles.directory} >
            <div className={styles.itemContainer}
                onContextMenu={(e) => openMenuFromEvent(e, menuItems)}
            >
                <div className={styles.item} onClick={clicked}>
                    <ChevronDown className={`${collapsed ? styles.collapsed : ""} ${styles.arrow}`} />
                    <Folder size={16} />

                    {rename ? (
                        <RenameDirectory name={name} path={path} onFocus={setRename} fileType={fileType} folders={children?.filter((child) => child.type === "directory")} />
                    ) : (
                        <p>{name}</p>
                    )}

                    <div onClick={(e) => { fileInputRef.current?.click(); e.stopPropagation(); }}>
                        <ArrowUpToLine size={16} />
                        <div style={{ display: "none" }}>
                            <InputFieldFile ref={fileInputRef} onChange={setFile} value={file} accept={[".docx", ".xlsx"]} />
                        </div>
                    </div>
                </div>
            </div>

            <div className={`${collapsed ? styles.collapsed : ""} ${styles.child}`}>
                {create &&
                    <CreateDirectory key={"createDirectory"} fileType={fileType} path={path ?? ""} onFocus={setCreate} folders={children?.filter((child) => child.type === "directory")} />
                }
                {(children ?? []).map((child) => child.type === "file" ? (
                    <Files key={child.path ?? child.name} fileType={fileType} data={child} />
                ) : (
                    <Directory key={child.path ?? child.name} name={child.name} children={child.children} path={child.path} fileType={fileType} />
                )
                )}
            </div>
        </DropZone>
    );
}