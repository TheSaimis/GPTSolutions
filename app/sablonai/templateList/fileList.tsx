"use client";

import { useRouter } from "next/navigation";
import styles from "./fileList.module.scss";
import { TemplateList } from "@/lib/types/TemplateList";
import { TemplateApi } from "@/lib/api/templates";
import { useRef, useEffect, useState } from "react";
import { ChevronDown, File, Folder, ArrowUpToLine } from "lucide-react";
import Directory from "./types/directory";
import Files from "./types/file";
import InputFieldFile from "@/components/inputFields/inputFieldFile";
import DropZone from "@/components/inputFields/dropZone";

type List = {
    name: string;
    type: string;
    children?: TemplateList[]
    directory?: string
}

export default function FileList({ name, type, children, directory }: List) {

    // const [collapsed, setCollapsed] = useState<string>("");
    // const [file, setFile] = useState<File | null>(null);
    const [childNodes, setChildNodes] = useState<TemplateList[]>(children ?? []);
    // const fileInputRef = useRef<HTMLInputElement>(null);
    // const router = useRouter();

    // function clicked() {
    //     switch (type) {
    //         case "file":
    //             router.push(`/sablonai/${directory}`);
    //             break;
    //         case "directory":
    //             setCollapsed(collapsed === "" ? "collapsed" : "")
    //             break;
    //     }
    // }

    // useEffect(() => {
    //     if (file?.name && file.type === "application/vnd.openxmlformats-officedocument.wordprocessingml.document") {
    //         TemplateApi.createTemplate(file, directory ?? "").then((res) => {
    //             if (res.status === "SUCCESS" && childNodes.find((child) => child.name === file.name) === undefined) {
    //                 setChildNodes(prev => [
    //                     ...prev,
    //                     {
    //                         name: file.name,
    //                         type: "file"
    //                     }
    //                 ]);
    //             }
    //             setFile(null);
    //         })
    //     }
    // }, [file])

    return (
        <div className={styles.templateList}>
            {type === "file" && (
                <Files name={name} directory={directory} type={""} />
            )}
            {type === "directory" && (
                <Directory name={name} type={""} children={childNodes} directory={directory} />
            )}
        </div>
    );
}