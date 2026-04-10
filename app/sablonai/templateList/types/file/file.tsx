"use client";

import { useRouter } from "next/navigation";
import styles from "../../fileList.module.scss";
import { FilesApi } from "@/lib/api/files";
import type { DownloadResult } from "@/lib/api/api";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import { renameFileInTree } from "@/app/sablonai/components/utilities/renameFile";
import { removeFileFromTree } from "@/app/sablonai/components/utilities/deleteFile";
import CheckBox from "@/components/inputFields/checkBox";
import { File, Eye } from "lucide-react";
import { setPDFToView } from "@/lib/globalVariables/pdfToView";
import { DirectoryStore, useDirectoryStore } from "@/lib/globalVariables/directoriesToSend";
import { formatFileSize } from "@/lib/functions/formatFileSize";
import { useCatalogueTree } from "@/app/sablonai/catalogueTreeContext";
import { useContextMenu } from "@/components/contextMenu/menuComponents/contextMenuProvider";
import { useConfirmAction } from "@/components/confirmationPanel/confirmationPanel";
import { FILE_TYPE_COLORS, type TemplateList } from "@/lib/types/TemplateList";
import { useEffect, useRef, useState } from "react";
import InputFieldText from "@/components/inputFields/inputFieldText";
import { TEMPLATE_FILE_DRAG_MIME } from "@/app/sablonai/components/utilities/moveFileInTree";

type List = {
  data: TemplateList;
  fileType: string;
};

export default function Files({ data, fileType }: List) {
  const router = useRouter();
  const [role] = useState<string | null>(() =>
    typeof window !== "undefined" ? localStorage.getItem("role") : null,
  );
  const canDragFile = role === "ROLE_ADMIN" && Boolean(fileType) && fileType !== "deleted";

  const selected = useDirectoryStore((s) => s.isSelected(data.path));
  const { openMenuFromEvent } = useContextMenu();
  const { setCatalogueTree } = useCatalogueTree();
  const inputRef = useRef<HTMLInputElement>(null);
  const { confirmAction } = useConfirmAction();
  const [rename, setRename] = useState<boolean>(false);

  function splitFileName(fullName: string) {
    const lastDot = fullName.lastIndexOf(".");
    if (lastDot === -1) {
      return { name: fullName, ext: "" };
    }
    return {
      name: fullName.slice(0, lastDot),
      ext: fullName.slice(lastDot),
    };
  }
  const { name, ext } = splitFileName(data.name);
  const isLink =
    ext.toLowerCase() === ".url" ||
    data.metadata?.custom?.mimeType === "application/internet-shortcut";
  const linkUrl = String(data.metadata?.custom?.linkUrl ?? "").trim();
  const [newName, setNewName] = useState(name);
  const [extension] = useState<string>(ext);

  function clicked() {
    if (isLink) {
      if (!linkUrl) return;
      window.open(linkUrl, "_blank", "noopener,noreferrer");
      return;
    }
    if (fileType == "generated") {
      const tid = data.metadata?.custom?.templateId;
      if (tid === undefined || tid === null || String(tid).trim() === "") {
        return;
      }
      router.push(`/sablonai/sukurtiDokumentai/${String(tid).trim()}/${fileType}/${data.path}`);
    } else if (fileType == "templates") {
      router.push(`/sablonai/kurtiDokumenta/${data.path}`);
    }
  }

  function previewPDF() {
    if (!fileType) return;
    FilesApi.getPDF(fileType, data.path).then((res: DownloadResult) => {
      setPDFToView(res);
    });
  }

  function renameFile() {
    if (!fileType) return;
    const cleanedName = newName.trimStart();
    if (!cleanedName) return;
    const finalName = cleanedName + extension;
    FilesApi.renameFile(data.path, finalName, fileType).then((res) => {
      if (res.status === "SUCCESS") {
        setRename(false);
        setNewName(cleanedName);
        setCatalogueTree((prev) => renameFileInTree(prev, data.path, finalName));
      }
    });
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
    });
    if (!confirmed) return;
    FilesApi.deleteFile(data.path, fileType).then((res) => {
      if (res.status === "SUCCESS") {
        DirectoryStore.remove(data.path);
        setCatalogueTree((prev) => removeFileFromTree(prev, data.path));
      }
    });
  }

  async function restoreFile() {
    FilesApi.restoreFile(data.path).then((res) => {
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

  const formatDate = (dateStr?: string) => {
    if (!dateStr) return "";
    const date = new Date(dateStr.replace(" ", "T"));
    return date.toLocaleDateString("lt-LT");
  };

  function onDragStartFile(e: React.DragEvent) {
    if (!canDragFile || !fileType) {
      e.preventDefault();
      return;
    }
    e.dataTransfer.setData(
      TEMPLATE_FILE_DRAG_MIME,
      JSON.stringify({ path: data.path, fileType }),
    );
    // Fallback for environments that do not preserve custom MIME types reliably.
    e.dataTransfer.setData(
      "text/plain",
      JSON.stringify({ path: data.path, fileType }),
    );
    e.dataTransfer.effectAllowed = "move";
  }

  return (
    <div>
      <div
        draggable={canDragFile}
        onDragStart={onDragStartFile}
        className={`${styles.files} ${selected ? styles.selected : ""} ${canDragFile ? styles.fileDraggable : ""}`}
        onContextMenu={(e) =>
          openMenuFromEvent(e, [
            {
              id: "open",
              label: isLink ? "Atidaryti nuorodą" : "Atidaryti",
              onClick: clicked,
            },
            ...(!isLink
              ? [
                {
                  id: "preview",
                  label: "Peržiūrėti failą",
                  onClick: previewPDF,
                },
              ]
              : []),
            {
              id: "download",
              label: isLink ? "Atsisiųsti nuorodą (.url)" : "Atsisiųsti",
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
                ...(fileType === "deleted"
                  ? [
                    {
                      id: "restore",
                      label: `Atstatyti failą ${data.name}`,
                      onClick: restoreFile,
                    },
                  ]
                  : []),
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
            <File className={styles.file} style={{ color: FILE_TYPE_COLORS[(data.metadata?.custom?.mimeType ?? "undefined") as keyof typeof FILE_TYPE_COLORS] }} />
            {rename ? (
              <div onClick={(e) => e.stopPropagation()}>
                <InputFieldText regex={/^[^\\/:*?"<>|\x00-\x1F]+$/} ref={inputRef} value={newName} onFocus={setRename} onChange={setNewName} onKeyDown={{ Enter: renameFile, Escape: () => setRename(false), }} />
              </div>
            ) : (
              <div className={styles.header}>
                <p className={styles.name}>{data.name}</p>
                {(() => {
                  const custom = data.metadata?.custom;
                  const core = data.metadata?.core;
                  const created = custom?.created ?? core?.created;
                  const modified = custom?.modifiedAt ?? core?.modified;
                  const editor = custom?.createdBy ?? custom?.lastModifiedBy;
                  const creator = custom?.createdBy;
                  const hasDates = Boolean(created || modified);
                  const extra = [
                    editor ? `Redagavo: ${editor}` : null,
                    creator ? `Sukūrė: ${creator}` : null,
                  ].filter(Boolean);
                  if (!hasDates && !extra) {
                    return null;
                  }
                  return (
                    <p className={styles.date}>
                      {hasDates ? (
                        <>
                          {created ? <>Sukurta {formatDate(created)}</> : null}
                          {created && modified ? " | " : null}
                          {modified ? <>Redaguota {formatDate(modified)}</> : null}
                          {hasDates ? <> | {formatFileSize(data.size || 0)}</> : null}
                        </>
                      ) : null}
                      {extra.length > 0 ? (
                        <>
                          {hasDates ? <br /> : null}
                          {extra.map((el, i) => (
                            <span key={i}>
                              {i > 0 ? " · " : null}
                              {el}
                            </span>
                          ))}
                        </>
                      ) : null}
                    </p>
                  );
                })()}
              </div>
            )}
          </div>

          <div className={styles.inputContainer}>
            {!isLink && (
              <button type="button" onClick={previewPDF} className={`${styles.button}`}>
                <Eye size={16} className={styles.icon} />
              </button>
            )}

            {fileType === "templates" && (
              <CheckBox
                value={selected}
                onChange={(checked: boolean) => {
                  if (checked) DirectoryStore.add(data.path);
                  else DirectoryStore.remove(data.path);
                }}
              />
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
