"use client";

import { useRouter } from "next/navigation";
import styles from "../../fileList.module.scss";
import CheckBox from "@/components/inputFields/checkBox";
import { File } from "lucide-react";
import { setPDFToView } from "@/lib/globalVariables/pdfToView";
import { DirectoryStore, useDirectoryStore, } from "@/lib/globalVariables/directoriesToSend";
import { useContextMenu } from "@/components/contextMenu/menuComponents/contextMenuProvider";
import { useEffect, useRef, useState } from "react";
import InputFieldText from "@/components/inputFields/inputFieldText";
import { useCatalogueTree } from "@/app/sablonai/catalogueTreeContext";
import { Metadata } from "@/lib/types/TemplateList";
import { GeneratedFilesApi } from "@/lib/api/generatedFiles";
import { TemplateApi } from "@/lib/api/templates";

type List = {
  name: string;
  path: string;
  fileType?: string;
  metadata?: Metadata;
};

export default function GeneratedFiles({ name, path, fileType, metadata }: List) {

  const router = useRouter();
  const role = localStorage.getItem("role");

  const { search, setSearch } = useCatalogueTree();
  const { typeFilter, setTypeFilter } = useCatalogueTree();

  const [rename, setRename] = useState<boolean>(false);
  const [deleted, setDeleted] = useState<boolean>(false);
  const [currentName, setCurrentName] = useState<string>(name);
  const selected = useDirectoryStore((s) =>
    s.isSelected(path),
  );
  const [newName, setNewName] = useState<string>(name);
  const { openMenuFromEvent } = useContextMenu();
  const inputRef = useRef<HTMLInputElement>(null);

  function clicked() {
    // router.push(`/sablonai/${path}/${currentName}`);
  }

  function previewPDF() {
    GeneratedFilesApi.getGeneratedPDF(path).then(
      (res: any) => {
        setPDFToView(res);
      },
    );
  }

  function renameTemplate() {
    // TemplateApi.renameTemplate(path + "/" + currentName, newName).then(
    //   (res) => {
    //     if (res.status === "SUCCESS") {
    //       if (selected) {
    //         DirectoryStore.remove(path + "/" + currentName);
    //         DirectoryStore.add(path + "/" + newName);
    //       }
    //       setRename(false);
    //       setCurrentName(newName);
    //     }
    //   },
    // );
  }

  function deleteTemplate() {
    // TemplateApi.deleteTemplate(path + "/" + currentName).then((res) => {
    //   if (res.status === "SUCCESS") {
    //     DirectoryStore.remove(path + "/" + currentName);
    //     setDeleted(true);
    //   }
    // });
  }

  useEffect(() => {
    inputRef.current?.focus();
  }, [rename]);

  useEffect(() => {
    if (metadata?.custom?.templateId) {
      const res = TemplateApi.getById(metadata?.custom?.templateId).then((res) => {console.log(res)});
    }
  }, []);


  if ((search && !currentName.toLowerCase().includes(search.toLowerCase()) || (typeFilter.length > 0 && !typeFilter.includes(metadata?.custom?.type || "")))) {
    return null;
  }

  if (deleted) return null;

  return (
    <div>
      <div
        className={`${styles.files} ${selected ? styles.selected : ""}`}
        onContextMenu={(e) =>
          openMenuFromEvent(e, [
            {
              id: "open",
              label: "Atidaryti",
              onClick: clicked,
            },
            {
              id: "preview",
              label: "Peržiūrėti dokumentą",
              onClick: previewPDF,
            },
            {
              id: "add",
              label: "Pasirinkti",
              onClick: () => DirectoryStore.add(`${path}/${currentName}`),
            },

            ...(role === "ROLE_ADMIN"
              ? [
                {
                  id: "rename",
                  label: "Pervadinti",
                  onClick: () => {
                    setRename(true);
                    inputRef.current?.focus();
                  },
                },
                {
                  id: "delete",
                  label: `Ištrinti dokumentą ${currentName}`,
                  onClick: deleteTemplate,
                },
              ]
              : []),
          ])
        }
      >

        <div className={styles.itemContainer}>
          <div className={styles.item} onClick={clicked}>
            <File className={styles.file} />
            {rename ? (
              <div onClick={(e) => e.stopPropagation()}>
                <InputFieldText ref={inputRef} value={newName} onFocus={setRename} onChange={setNewName} onKeyDown={{ Enter: renameTemplate, Escape: () => setRename(false), }} />
              </div>
            ) : (
              <div className={styles.header}>
                <p className={styles.name}>{name}</p>
                {metadata?.custom?.created &&
                  <p className={styles.date}>Sukurta {metadata?.custom?.created}</p>
                }
              </div>
            )}
          </div>

          <div className={styles.inputContainer}>
            <button onClick={previewPDF} className={`${styles.button} buttons`}>
              Peržiūrėti failą
            </button>

            {/* <CheckBox
              value={selected}
              onChange={(checked: boolean) => {
                if (checked) DirectoryStore.add(path + "/" + currentName);
                else DirectoryStore.remove(path + "/" + currentName);
              }}
            /> */}
          </div>
        </div>
      </div>
    </div>
  );
}