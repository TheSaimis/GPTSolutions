"use client";

import styles from "../../fileList.module.scss";
import { TemplateList } from "@/lib/types/TemplateList";
import { TemplateApi } from "@/lib/api/templates";
import { useRef, useEffect, useState } from "react";
import { ChevronDown, Folder, ArrowUpToLine } from "lucide-react";
import Files from "../file";
import InputFieldFile from "@/components/inputFields/inputFieldFile";
import DropZone from "@/components/inputFields/dropZone";
import CreateDirectory from "./createDirectory";

type List = {
    name: string;
    children?: TemplateList[]
    directory?: string
}

export default function Directory({ name, children, directory }: List) {
    const [collapsed, setCollapsed] = useState<string>("");
    const [newFolderName, setNewFolderName] = useState<string>("");
    const [file, setFile] = useState<File | null>(null);
    const [childNodes, setChildNodes] = useState<TemplateList[]>(children ?? []);
    const [action, setAction] = useState<string>("");
    const fileInputRef = useRef<HTMLInputElement>(null);

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


    return (
                <DropZone onFile={setFile} accept=".docx" className={styles.directory} >
                    <div className={styles.itemContainer}>
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
                        {childNodes.map((child) => (
                            child.type === "file" ?
                                <Files key={child.name} name={child.name} directory={directory + "/" + child.name} />
                                :
                                <Directory key={child.name} name={child.name} children={child.children} directory={directory + "/" + child.name} />
                        ))}

                       {action == "new-folder" &&
                            <CreateDirectory directory={directory ?? ""} onUpload={setNewFolderName} />
                        } 

                    </div>
                </DropZone>
    );
}