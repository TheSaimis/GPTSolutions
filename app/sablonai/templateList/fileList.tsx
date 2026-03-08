"use client";

import styles from "./fileList.module.scss";
import { TemplateList } from "@/lib/types/TemplateList";
import { useState } from "react";
import Directory from "./types/directory/directory";
import Files from "./types/file/file";

type List = {
    name: string;
    type: string;
    children?: TemplateList[]
    directory?: string 
}

export default function FileList({ name, type, children, directory }: List) {

    const [childNodes, setChildNodes] = useState<TemplateList[]>(children ?? []);

    return (

        <div className={styles.templateList}>
            {type === "file" && (
                <Files name={name} directory={directory} />
            )}
            {type === "directory" && (
                <Directory name={name} children={childNodes} directory={directory} />
            )}
        </div>
    );
}