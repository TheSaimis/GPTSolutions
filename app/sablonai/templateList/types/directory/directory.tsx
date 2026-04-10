"use client";

import styles from "../../fileList.module.scss";
import { TemplateList } from "@/lib/types/TemplateList";
import { useRef, useEffect, useState, useMemo, useCallback } from "react";
import { ChevronDown, Folder, ArrowUpToLine } from "lucide-react";
import Files from "../file/file";
import InputFieldFile from "@/components/inputFields/inputFieldFile";
import DropZone from "@/components/inputFields/dropZone";
import CreateDirectory from "./functions/createDirectory";
import RenameDirectory from "./functions/renameDirectory";
import { TemplateApi } from "@/lib/api/templates";
import { DirectoryStore } from "@/lib/globalVariables/directoriesToSend";
import { useContextMenu } from "@/components/contextMenu/menuComponents/contextMenuProvider";
import { useDeleteFolder } from "./functions/deleteDirectory";
import { useCreateFile } from "./functions/createFile";
import { useCreateLink } from "./functions/createLink";
import { useDirectoryFileMoveDrop } from "./functions/useDirectoryFileMoveDrop";
import { CatalougeApi } from "@/lib/api/catalouges";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import { extractTemplateIds } from "@/app/sablonai/components/utilities/extractTemplateIds";

type DirectoryList = {
    name: string;
    fileType: string,
    nodes?: TemplateList[]
    path?: string
}

export default function Directory({ name, nodes, path, fileType }: DirectoryList) {

    const [collapsed, setCollapsed] = useState<boolean>(fileType == "generated");
    const [rename, setRename] = useState<boolean>(false);
    const [create, setCreate] = useState<boolean>(false);
    const [file, setFile] = useState<File | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { openMenuFromEvent } = useContextMenu();
    const { deleteFolder } = useDeleteFolder();
    const { createFile, createFiles } = useCreateFile();
    const { openCreateLinkModal, linkModal } = useCreateLink();
    
    const [role] = useState<string | null>(() =>
        typeof window !== "undefined" ? localStorage.getItem("role") : null,
    );
    const { onDirectoryDragOver, onDirectoryDrop } = useDirectoryFileMoveDrop({
        role,
        fileType,
        path,
    });

    const clicked = useCallback(() => {
        setCollapsed(!collapsed);
    }, [collapsed]);

    useEffect(() => {
        if (!file) {
            return;
        }
        let cancelled = false;
        void (async () => {
            await createFile(file, path ?? "", fileType);
            if (!cancelled) {
                setFile(null);
            }
        })();
        return () => {
            cancelled = true;
        };
    }, [file, fileType, path, createFile]);

    const handleDroppedFiles = useCallback(
        async (files: File[]) => {
            await createFiles(files, path ?? "", fileType);
        },
        [createFiles, fileType, path],
    );

    const downloadFolder = useCallback(async () => {
        const res = await CatalougeApi.catalogueDownload(fileType ?? "", path ?? "");
        downloadBlob(res);
    }, [fileType, path]);

    const getTemplatePaths = useCallback(async () => {
        const ids = extractTemplateIds(nodes ?? []);
        if (ids.length === 0) {
            return;
        }
        try {
            const res = await TemplateApi.getById(ids);
            const paths = res.map((r) => r.path);
            paths.forEach((templatePath) => {
                DirectoryStore.add(templatePath);
            });
        } catch {}
    }, [nodes]);

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
                    openCreateLinkModal(path ?? "", fileType);
                },
            },
            {
                id: "downloadFolder",
                label: "Atsisiunti aplanką",
                onClick: () => {
                    downloadFolder();
                },
            },
            {
                id: "extractTemplateIds",
                label: "Pasirinkti šablonus",
                onClick: () => {
                    getTemplatePaths();
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
        [name, fileType, path, deleteFolder, role, downloadFolder, getTemplatePaths, openCreateLinkModal]
    );

    return (
        <DropZone onFiles={handleDroppedFiles} accept={[".doc", ".docx", ".xlsx", ".zip"]} className={styles.directory} >
            <div
                onDragOver={onDirectoryDragOver}
                onDrop={onDirectoryDrop}
            >
                <div
                    className={styles.itemContainer}
                    onContextMenu={(e) => openMenuFromEvent(e, menuItems)}
                >
                    <div className={styles.item} onClick={clicked}>
                        <ChevronDown className={`${collapsed ? styles.collapsed : ""} ${styles.arrow}`} />
                        <Folder size={16} />

                        {rename ? (
                            <RenameDirectory name={name} path={path} onFocus={setRename} fileType={fileType} folders={nodes?.filter((child) => child.type === "directory")} />
                        ) : (
                            <p>{name}</p>
                        )}

                        <div onClick={(e) => { fileInputRef.current?.click(); e.stopPropagation(); }}>
                            <ArrowUpToLine size={16} />
                            <div style={{ display: "none" }}>
                                <InputFieldFile ref={fileInputRef} onChange={setFile} value={file} accept={[".doc", ".docx", ".xlsx", ".zip"]} />
                            </div>
                        </div>
                    </div>
                </div>

                <div className={`${collapsed ? styles.collapsed : ""} ${styles.child}`}>
                    {create &&
                        <CreateDirectory key={"createDirectory"} fileType={fileType} path={path ?? ""} onFocus={setCreate} folders={nodes?.filter((child) => child.type === "directory")} />
                    }
                    {(nodes ?? []).map((child) => child.type === "file" ? (
                        <Files key={`${child.name}-${child.type}-${path}`} fileType={fileType} data={child} />
                    ) : (
                        <Directory key={child.path ?? child.name} name={child.name} nodes={child.children} path={child.path} fileType={fileType} />
                    )
                    )}
                </div>
            </div>
            {linkModal}
        </DropZone>
    );
}