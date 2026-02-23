"use client";

import { useRouter } from "next/navigation";
import styles from "./fileList.module.scss";
import { TemplateList } from "@/lib/types/TemplateList";
import { useEffect, useState } from "react";
import { ChevronDown, File, Folder } from "lucide-react";

type List = {
    name: string;
    type: string;
    children?: TemplateList[]
    directory?: string
}

export default function FileList({ name, type, children, directory }: List) {

    const [collapsed, setCollapsed] = useState<string>("");
    const router = useRouter();

    function clicked() {
        switch (type) {
            case "file":
                router.push(`/sablonai/${directory}`);
                break;
            case "directory":
                setCollapsed(collapsed === "" ? "collapsed" : "")
                break;
        }
    }

    return (
        <div className={styles.templateList}>
            <div className={styles.itemContainer}>
                <div className={styles.item} onClick={clicked}>
                    {type === "directory" && (
                        <>
                            <ChevronDown className={`${collapsed ? styles.collapsed : ""} ${styles.arrow}`} />
                            <Folder size={16} />
                        </>
                    )}
                    {type === "file" &&
                        <File className={styles.file} />
                    }
                    <p>{name}</p>
                </div>
                {type === "file" &&
                    <button className={`${styles.button} buttons`}>Peržiūrėti šabloną</button>
                }
            </div>

            <div className={`${collapsed ? styles.collapsed : ""} ${styles.child}`}>
                {children && children.map((child) => (
                    <FileList key={child.name} name={child.name} children={child.children} type={child.type} directory={name + "/" + child.name}/>
                ))}
            </div>
        </div>
    );
}