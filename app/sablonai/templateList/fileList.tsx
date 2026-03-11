"use client";

import styles from "./fileList.module.scss";
import { TemplateList } from "@/lib/types/TemplateList";
import { useEffect, useState } from "react";
import { Search } from "lucide-react";
import InputFieldText from "@/components/inputFields/inputFieldText";
import Directory from "./types/directory/directory";
import Files from "./types/file/file";
import { useCatalogueTree } from "../catalogueTreeContext";

type FileListProps = {
    catalougeTree: TemplateList[];
    fileType?: string
};

export default function FileList({ catalougeTree, fileType }: FileListProps) {

    const { search, setSearch } = useCatalogueTree();

    useEffect(() => {
       console.log(catalougeTree);
    }, []);

    return (

        <div className={styles.templateList}>

            <div className={styles.card}>
                <div>
                    <InputFieldText value={search} onChange={setSearch} placeholder="Paieška" icon={Search} />
                </div>
                <div className={styles.catalogueTree}>
                    {catalougeTree.map((item) => (
                        item.type === "file" && (
                            <Files key={item.name} name={item.name} directory={""} fileType={fileType} createdAt={item.createdAt} metadata={item.metadata} modifiedAt={item.modifiedAt} />
                        )
                        ||
                        item.type === "directory" && (
                            <Directory key={item.name} name={item.name} children={item.children} directory={item.name} fileType={fileType} />
                        )
                    ))
                    }
                </div>
            </div>
            
        </div>
    );
}