"use client";

import styles from "./fileList.module.scss";
import { Search } from "lucide-react";
import InputFieldText from "@/components/inputFields/inputFieldText";
import Directory from "./types/directory/directory";
import Files from "./types/file/file";
import GeneratedFiles from "./types/file/generatedFile";
import { useCatalogueTree } from "../catalogueTreeContext";
import Filters from "../components/filters/filters";

type FileListProps = {
    fileType?: string;
};

export default function FileList({ fileType }: FileListProps) {
    const { filteredCatalogueTree, filters, setFilters } = useCatalogueTree();
    const Component = fileType === "generated" ? GeneratedFiles : Files;

    return (
        <div className={styles.container}>
            <div className={styles.templateList}>
                <div className={styles.card}>
                    <div>
                        <InputFieldText
                            value={filters.search}
                            onChange={(value) => setFilters((prev) => ({ ...prev, search: value }))}
                            placeholder="Paieška"
                            icon={Search}
                        />
                    </div>

                    <div className={styles.catalogueTree}>
                        {filteredCatalogueTree.map((node) =>
                            node.type === "file" ? (
                                <Component
                                    key={node.path ?? node.name}
                                    name={node.name}
                                    path={node.path ?? ""}
                                    metadata={node.metadata}
                                    fileType={fileType}
                                />
                            ) : (
                                <Directory
                                    key={node.path ?? node.name}
                                    name={node.name}
                                    children={node.children}
                                    path={node.path}
                                    fileType={fileType}
                                />
                            )
                        )}
                    </div>
                </div>
            </div>
            <Filters />
        </div>
    );
}