"use client";

import styles from "./fileList.module.scss";
import { TemplateList } from "@/lib/types/TemplateList";
import { Search } from "lucide-react";
import InputFieldText from "@/components/inputFields/inputFieldText";
import Directory from "./types/directory/directory";
import Files from "./types/file/file";
import { useCatalogueTree } from "../catalogueTreeContext";
import GeneratedFiles from "./types/file/generatedFile";
import { useEffect } from "react";

type FileListProps = {
    catalougeTreeProp: TemplateList[];
    fileType?: string
};

export default function FileList({ catalougeTreeProp, fileType }: FileListProps) {

    const { search, setSearch } = useCatalogueTree();
    const { catalogueTree, setCatalogueTree } = useCatalogueTree();
    const Component = fileType === "generated" ? GeneratedFiles : Files;

    useEffect(() => {
        setCatalogueTree(catalougeTreeProp);
        console.log(catalogueTree);
        console.log(catalougeTreeProp);
    }, [catalougeTreeProp]);

    return (

        <div className={styles.templateList}>

            <div className={styles.card}>
                <div>
                    <InputFieldText value={search} onChange={setSearch} placeholder="Paieška" icon={Search} />
                </div>
                <div className={styles.catalogueTree}>
                    {catalogueTree.map((item) => (
                        item.type === "file" && (
                            <Component key={item.name} name={item.name} path={item.path ?? item.name} fileType={fileType} createdAt={item.createdAt} metadata={item.metadata} modifiedAt={item.modifiedAt} />
                        )
                        ||
                        item.type === "directory" && (
                            <Directory key={item.name} name={item.name} children={item.children} path={item.path} fileType={fileType} />
                        )
                    ))
                    }
                </div>
            </div>
        </div>
    );
}