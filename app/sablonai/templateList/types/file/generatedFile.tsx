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
    if (metadata?.custom?.templateId === undefined || metadata?.custom?.userId === undefined || metadata?.custom?.companyId === undefined) return
    router.push(`/sablonai/sukurtiDokumentai/${metadata.custom.templateId}/${metadata.custom.userId}/${metadata.custom.companyId}/${metadata.custom.created}/${path}`);
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
                  <p className={styles.date}>Redaguota {metadata?.custom?.created}</p>
                }
              </div>
            )}
          </div>

          <div className={styles.inputContainer}>
            <button onClick={previewPDF} className={`${styles.button} buttons`}>
              Peržiūrėti failą
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}