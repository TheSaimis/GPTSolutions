"use client";

import styles from "../../fileList.module.scss";
import { TemplateList } from "@/lib/types/TemplateList";
import { TemplateApi } from "@/lib/api/templates";
import { useRef, useEffect, useState } from "react";
import { ChevronDown, Folder, ArrowUpToLine } from "lucide-react";
import Files from "../file/file";
import InputFieldFile from "@/components/inputFields/inputFieldFile";
import InputFieldText from "@/components/inputFields/inputFieldText";
import DropZone from "@/components/inputFields/dropZone";
import CreateDirectory from "./functions/createDirectory";
import RenameDirectory from "./functions/renameDirectory";
import { addFileToTree } from "@/app/sablonai/components/utilities/addFile";
import { useContextMenu } from "@/components/contextMenu/menuComponents/contextMenuProvider";
import { useCatalogueTree } from "@/app/sablonai/catalogueTreeContext";
import { renameDirectoryInTree } from "@/app/sablonai/components/utilities/renameDirectory";
import GeneratedFiles from "../file/generatedFile";
import { CatalougeApi } from "@/lib/api/catalouges";

type List = {
    name: string;
    children?: TemplateList[]
    fileType?: string,
    path?: string
}

export default function Directory({ name, children, path, fileType }: List) {

    const [collapsed, setCollapsed] = useState<string>("");
    const [rename, setRename] = useState<boolean>(false);
    const [create, setCreate] = useState<boolean>(false);
    const [file, setFile] = useState<File | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { openMenuFromEvent } = useContextMenu();
    const { search, setSearch, catalogueTree, setCatalogueTree, typeFilter, setTypeFilter, companyFilter, setCompanyFilter } = useCatalogueTree();
    const Component = fileType === "generated" ? GeneratedFiles : Files;
    const inputRef = useRef<HTMLInputElement>(null);

    // used for knowing if the path includes a file that matches the file search
    function matchesTree(nodes: TemplateList[], searchValue: string, typeValues?: string[]): boolean {
        const normalizedSearch = searchValue.trim().toLowerCase();
        if (!normalizedSearch) return true;
        return nodes.some((node) => {
            const nameMatches = node.name.toLowerCase().includes(normalizedSearch);
            if (nameMatches) return true;

            if (node.type === "directory" && node.children) {
                return matchesTree(node.children, normalizedSearch);
            }
            return false;
        });
    }

    const normalizedSearch = search.trim().toLowerCase();
    const shouldShowDirectory = !normalizedSearch || name.toLowerCase().includes(normalizedSearch) || matchesTree(children ?? [], normalizedSearch);

    function clicked() {
        setCollapsed(collapsed === "" ? "collapsed" : "")
    }

    useEffect(() => {
        if (file?.name && file.type === "application/vnd.openxmlformats-officedocument.wordprocessingml.document") {
            TemplateApi.createTemplate(file, path ?? "").then((res) => {
                if (res.status === "SUCCESS") {
                    console.log(path);
                    setCatalogueTree((prev) => addFileToTree(prev, path ?? "", file.name));
                }
                setFile(null);
            })
        }
    }, [file])

    useEffect(() => {
        inputRef.current?.focus();
    }, [rename]);

    if (!shouldShowDirectory) return null;
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
                            console.log("Rename:", path);
                        },
                    },

                ])
            }>
                <div className={styles.item} onClick={clicked}>
                    <ChevronDown className={`${collapsed ? styles.collapsed : ""} ${styles.arrow}`} />
                    <Folder size={16} />

                    {rename ? (
                        <RenameDirectory name={name} path={path} onFocus={setRename} folders={children?.filter((child) => child.type === "directory")} />
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
                {(children ?? []).map((child) =>
                    child.type === "file" ? (
                        <Component key={child.path ?? child.name} name={child.name} path={child.path ?? ""} metadata={child.metadata} fileType={fileType} />
                    ) : (
                        <Directory key={child.path ?? child.name} name={child.name} children={child.children} path={child.path} fileType={fileType} />
                    )
                )}
            </div>
        </DropZone>
    );
}