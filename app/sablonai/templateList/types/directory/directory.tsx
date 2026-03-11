"use client";

import styles from "../../fileList.module.scss";
import { TemplateList } from "@/lib/types/TemplateList";
import { TemplateApi } from "@/lib/api/templates";
import { useRef, useEffect, useState } from "react";
import { ChevronDown, Folder, ArrowUpToLine } from "lucide-react";
import Files from "../file/file";
import InputFieldFile from "@/components/inputFields/inputFieldFile";
import DropZone from "@/components/inputFields/dropZone";
import CreateDirectory from "./functions/createDirectory";
import { useContextMenu } from "@/components/contextMenu/menuComponents/contextMenuProvider";
import { useCatalogueTree } from "@/app/sablonai/catalogueTreeContext";

type List = {
    name: string;
    children?: TemplateList[]
    fileType?: string,
    directory?: string
}

export default function Directory({ name, children, directory, fileType }: List) {
    const [collapsed, setCollapsed] = useState<string>("");
    const [rename, setRename] = useState<boolean>(false);
    const [create, setCreate] = useState<boolean>(false);
    const [file, setFile] = useState<File | null>(null);
    const [childNodes, setChildNodes] = useState<TemplateList[]>(children ?? []);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { openMenuFromEvent } = useContextMenu();
    const { search, setSearch } = useCatalogueTree();

    // used for knowing if the directory includes a file that matches the file search
    function matchesTree(nodes: TemplateList[], searchValue: string): boolean {
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
    const shouldShowDirectory =
    !normalizedSearch ||
    name.toLowerCase().includes(normalizedSearch) ||
    matchesTree(childNodes, normalizedSearch);

    function clicked() {
        setCollapsed(collapsed === "" ? "collapsed" : "")
    }

    useEffect(() => {
        if (file?.name && file.type === "application/vnd.openxmlformats-officedocument.wordprocessingml.document") {
            TemplateApi.createTemplate(file, directory ?? "").then((res) => {
                if (res.status === "SUCCESS" && childNodes.find((child) => child.name === file.name) === undefined) {
                    setChildNodes(prev => [
                        ...prev,
                        {
                            name: file.name,
                            type: "file"
                        }
                    ]);
                }
                setFile(null);
            })
        }
    }, [file])

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
                        label: `Naujas šablonas`,
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
                            console.log("Rename:", directory);
                        },
                    },

                ])
            }>
                <div className={styles.item} onClick={clicked}>
                    <ChevronDown className={`${collapsed ? styles.collapsed : ""} ${styles.arrow}`} />
                    <Folder size={16} />
                    <p>{name}</p>
                    <div onClick={(e) => {
                        fileInputRef.current?.click();
                        e.stopPropagation();
                    }}>
                        <ArrowUpToLine size={16} />
                        <div style={{ display: "none" }}>
                            <InputFieldFile ref={fileInputRef} onChange={setFile} value={file} accept={".docx"} />
                        </div>
                    </div>
                </div>
            </div>
            <div className={`${collapsed ? styles.collapsed : ""} ${styles.child}`}>
                {create &&
                    <CreateDirectory key={"createDirectory"} directory={directory ?? ""} onUpload={setChildNodes} onFocus={setCreate} folders={childNodes.filter((child) => child.type === "directory")} />
                }
                {childNodes.map((child) => (
                    child.type === "file" ?
                        <Files key={child.name} name={child.name} directory={directory} metadata={child.metadata} fileType={fileType} />
                        :
                        <Directory key={child.name} name={child.name} children={child.children} directory={`${directory}/${child.name}`} fileType={fileType} />
                ))}
            </div>
        </DropZone>
    );
}