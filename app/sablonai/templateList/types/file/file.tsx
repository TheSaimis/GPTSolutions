"use client";

import { useRouter } from "next/navigation";
import styles from "../../fileList.module.scss";
import { FilesApi } from "@/lib/api/files";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import { renameFileInTree } from "@/app/sablonai/components/utilities/renameFile";
import { removeFileFromTree } from "@/app/sablonai/components/utilities/deleteFile";
import CheckBox from "@/components/inputFields/checkBox";
import { File } from "lucide-react";
import { setPDFToView } from "@/lib/globalVariables/pdfToView";
import { DirectoryStore, useDirectoryStore, } from "@/lib/globalVariables/directoriesToSend";
import { formatFileSize } from "@/lib/functions/formatFileSize";
import { useCatalogueTree } from "@/app/sablonai/catalogueTreeContext";
import { useContextMenu } from "@/components/contextMenu/menuComponents/contextMenuProvider";
import { useConfirmAction } from "@/components/confirmationPanel/confirmationPanel";
import type { TemplateList } from "@/lib/types/TemplateList";
import { useEffect, useRef, useState } from "react";
import InputFieldText from "@/components/inputFields/inputFieldText";

type List = {
  data: TemplateList;
  fileType: string;
};

export default function Files({ data, fileType }: List) {

  const router = useRouter();
  const role = localStorage.getItem("role");

  const [rename, setRename] = useState<boolean>(false);
  const selected = useDirectoryStore((s) => s.isSelected(data.path));
  const [newName, setNewName] = useState<string>(data.name);
  const { openMenuFromEvent } = useContextMenu();
  const { setCatalogueTree } = useCatalogueTree();
  const inputRef = useRef<HTMLInputElement>(null);
  const { confirmAction } = useConfirmAction();

  function clicked() {
    if (fileType == "generated") {
      if (data.metadata?.custom?.templateId === undefined || data.metadata?.custom?.userId === undefined || data.metadata?.custom?.companyId === undefined) return
      router.push(`/sablonai/sukurtiDokumentai/${data.metadata.custom.templateId}/${data.metadata.custom.userId}/${data.metadata.custom.companyId}/${data.metadata.custom.created}/${data.path}`);
    } else if (fileType == "templates") {
      router.push(`/sablonai/${data.path}`);
    }
  }

  function previewPDF() {
    if (!fileType) return;
    FilesApi.getPDF(fileType, data.path).then(
      (res: any) => {
        setPDFToView(res);
      },
    );
  }

  function renameFile() {
    if (!fileType) return;
    FilesApi.renameFile(data.path, newName, fileType).then(
      (res) => {
        if (res.status === "SUCCESS") {
          setRename(false);
          setCatalogueTree((prev) => renameFileInTree(prev, data.path, newName));
        }
      },
    );
  }

  async function deleteTemplate() {
    if (!fileType) return;
    const confirmed = await confirmAction({
      type: "delete",
      title: "Ištrinti failą?",
      message: "Ištrynus failą jis bus saugomas ištrintų failų kataloge 7 dienas.\n Po 7 dienu failas bus ištrintas visam laikui.\n Jeigu trinate šabloną, su juo susije sukurti dokumentai negalės būti atnaujinami.",
      confirmText: "Ištrinti",
      cancelText: "Atšaukti",
      icon: File,
    })
    if (!confirmed) return;
    FilesApi.deleteFile(data.path, fileType).then((res) => {
      if (res.status === "SUCCESS") {
        DirectoryStore.remove(data.path);
        setCatalogueTree((prev) => removeFileFromTree(prev, data.path));
      }
    });
  }

  function downloadFile() {
    FilesApi.downloadFile(`${fileType}/${data.path}`).then((res) => { downloadBlob(res); });
  }

  useEffect(() => {
    inputRef.current?.focus();
  }, [rename]);

  useEffect(() => {
    console.log(data);
  }, [data]);

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
              label: "Peržiūrėti failą",
              onClick: previewPDF,
            },
            {
              id: "download",
              label: "Atsisiųsti",
              onClick: downloadFile,
            },
            ...(fileType === "templates" ? [
              {
                id: "add",
                label: "Pasirinkti",
                onClick: () => DirectoryStore.add(`${data.path}`),
              },
            ] : []),
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
                  label: `Ištrinti failą ${data.name}`,
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
                <InputFieldText ref={inputRef} value={newName} onFocus={setRename} onChange={setNewName} onKeyDown={{ Enter: renameFile, Escape: () => setRename(false), }} />
              </div>
            ) : (
              <div className={styles.header}>
                <p className={styles.name}>{data.name}</p>
                {data.metadata?.custom?.created &&
                  <p className={styles.date}>Sukurta {data.metadata?.custom?.created} {formatFileSize(data.size || 0)}</p>
                }
              </div>
            )}
          </div>

          <div className={styles.inputContainer}>
            <button onClick={previewPDF} className={`${styles.button} buttons`}>
              Peržiūrėti failą
            </button>

            {fileType === "templates" &&
              <CheckBox
                value={selected}
                onChange={(checked: boolean) => {
                  if (checked) DirectoryStore.add(data.path);
                  else DirectoryStore.remove(data.path);
                }}
              />
            }
          </div>
        </div>
      </div>
    </div>
  );
}