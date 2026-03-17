"use client";

import styles from "../../fileList.module.scss";
import { TemplateList } from "@/lib/types/TemplateList";
import { TemplateApi } from "@/lib/api/templates";
import { FilesApi } from "@/lib/api/files";
import { useRef, useEffect, useState } from "react";
import { ChevronDown, Folder, ArrowUpToLine } from "lucide-react";
import Files from "../file/file";
import InputFieldFile from "@/components/inputFields/inputFieldFile";
import DropZone from "@/components/inputFields/dropZone";
import CreateDirectory from "./functions/createDirectory";
import RenameDirectory from "./functions/renameDirectory";
import { addFileToTree } from "@/app/sablonai/components/utilities/addFile";
import { useContextMenu } from "@/components/contextMenu/menuComponents/contextMenuProvider";
import { useCatalogueTree } from "@/app/sablonai/catalogueTreeContext";
import GeneratedFiles from "../file/generatedFile";

type List = {
    name: string;
    fileType: string,
    children?: TemplateList[]
    path?: string
}

export default function Directory({ name, children, path, fileType }: List) {

    const [collapsed, setCollapsed] = useState<boolean>(false);
    const [rename, setRename] = useState<boolean>(false);
    const [create, setCreate] = useState<boolean>(false);
    const [file, setFile] = useState<File | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { openMenuFromEvent } = useContextMenu();
    const { setCatalogueTree } = useCatalogueTree();
    const Component = fileType === "generated" ? GeneratedFiles : Files;

    function clicked() {
        setCollapsed(!collapsed);
    }

    useEffect(() => {
        if (
            file?.name &&
            file.type === "application/vnd.openxmlformats-officedocument.wordprocessingml.document" &&
            fileType
        ) {
            FilesApi.createFile(file, path ?? "", fileType).then((res) => {
                if (res.status === "SUCCESS" && res.file) {
                    const fileNode = {
                        ...res.file
                    };
                    setCatalogueTree((prev) =>
                        addFileToTree(prev, path ?? "", fileNode)
                    );
                }

                setFile(null);
            });
        }
    }, [file, fileType, path, setCatalogueTree]);

    return (
        <DropZone onFile={setFile} accept=".docx" className={styles.directory} >
            <div className={styles.itemContainer} onContextMenu={(e) =>
                openMenuFromEvent(e, [
                    {
                        id: "newFolder",
                        label: "Naujas aplankas",
                        onClick: () => {
                            setCreate(true);
                        },
                    },
                    {
                        id: "newTemplate",
                        label: `Naujas failas`,
                        onClick: () => {
                            fileInputRef.current?.click();
                        },
                    },
                    {
                        id: "renameFolder",
                        label: `Pervadintį aplanką "${name}"`,
                        onClick: () => {
                            setRename(true);
                        },
                    },
                    {
                        id: "deleteFolder",
                        label: `Ištrinti aplanką "${name}"`,
                        onClick: () => {
                            console.log("Delete:", path);
                        },
                    },
                ])
            }>
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
                            <InputFieldFile ref={fileInputRef} onChange={setFile} value={file} accept={".docx"} />
                        </div>
                    </div>
                </div>
            </div>

            <div className={`${collapsed ? styles.collapsed : ""} ${styles.child}`}>
                {create &&
                    <CreateDirectory key={"createDirectory"} path={path ?? ""} onFocus={setCreate} folders={children?.filter((child) => child.type === "directory")} />
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