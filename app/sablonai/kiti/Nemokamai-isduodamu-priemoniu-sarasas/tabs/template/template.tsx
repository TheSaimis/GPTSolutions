"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import styles from "../../page.module.scss";
import {
    AapEquipmentTemplateKind,
    AapEquipmentTemplateStatusRow,
    AapTemplateLocale,
    EquipmentApi,
} from "@/lib/api/equipment";
import { MessageStore } from "@/lib/globalVariables/messages";
import { setPDFToView } from "@/lib/globalVariables/pdfToView";
import DropZone from "@/components/inputFields/dropZone";

function kindLabel(kind: AapEquipmentTemplateKind): string {
    return kind === "sarasas" ? "AAP sąrašas" : "AAP kortelės + žiniaraščiai";
}

function sourceLabel(row: AapEquipmentTemplateStatusRow): string {
    if (row.source === "database") {
        return "DB";
    }
    if (row.source === "filesystem") {
        return "Failai";
    }
    return "Nėra";
}

function rowsForKind(
    status: AapEquipmentTemplateStatusRow[] | null,
    kind: AapEquipmentTemplateKind,
): AapEquipmentTemplateStatusRow[] {
    if (!status) return [];
    return status.filter((r) => r.kind === kind);
}

export default function EquipmentTemplate() {
    const [role, setRole] = useState<string | null>(null);
    const [status, setStatus] = useState<AapEquipmentTemplateStatusRow[] | null>(null);
    const [uploading, setUploading] = useState<string | null>(null);
    const [previewingPdf, setPreviewingPdf] = useState<string | null>(null);

    const loadStatus = useCallback(() => {
        EquipmentApi.getAapTemplateStatus()
            .then((r) => setStatus(r.templates))
            .catch(() => setStatus(null));
    }, []);

    useEffect(() => {
        if (typeof window === "undefined") return;
        setRole(localStorage.getItem("role"));
    }, []);

    useEffect(() => {
        loadStatus();
    }, [loadStatus]);

    const isAdmin = role === "ROLE_ADMIN";

    const uploadKey = useMemo(
        () => (kind: AapEquipmentTemplateKind, loc: AapTemplateLocale) => `${kind}:${loc}`,
        [],
    );

    const uploadTemplateFile = async (kind: AapEquipmentTemplateKind, loc: AapTemplateLocale, file: File) => {
        const lower = file.name.toLowerCase();
        if (!lower.endsWith(".doc") && !lower.endsWith(".docx")) {
            MessageStore.push({
                title: "Netinkamas formatas",
                message: "Pasirinkite .doc arba .docx failą.",
                backgroundColor: "#e53e3e",
            });
            return;
        }
        setUploading(uploadKey(kind, loc));
        try {
            await EquipmentApi.uploadAapTemplate(kind, file, loc);
            MessageStore.push({
                title: "Įkelta",
                message: `${kindLabel(kind)} (${loc.toUpperCase()}): šablonas išsaugotas duomenų bazėje.`,
                backgroundColor: "#16a34a",
            });
            loadStatus();
        } catch {
            /* api.ts jau rodo klaidą */
        } finally {
            setUploading(null);
        }
    };

    const onPreviewPdf = async (kind: AapEquipmentTemplateKind, loc: AapTemplateLocale) => {
        setPreviewingPdf(uploadKey(kind, loc));
        try {
            const pdf = await EquipmentApi.getAapTemplatePdf(kind, loc);
            setPDFToView(pdf);
        } catch {
            /* api.ts jau rodo klaidą */
        } finally {
            setPreviewingPdf(null);
        }
    };

    const onDelete = async (kind: AapEquipmentTemplateKind, loc: AapTemplateLocale) => {
        if (
            !window.confirm(
                `Pašalinti įkeltą šabloną „${kindLabel(kind)}“ (${loc.toUpperCase()})? Naudosis numatytasis serverio failas (jei yra).`,
            )
        ) {
            return;
        }
        try {
            await EquipmentApi.deleteAapTemplate(kind, loc);
            MessageStore.push({
                title: "Pašalinta",
                message: "DB šablonas pašalintas.",
                backgroundColor: "#0ea5e9",
            });
            loadStatus();
        } catch {
            /* api.ts */
        }
    };

    return (
        <div className={styles.card}>
            {!status ? (
                <p className={styles.muted}>Kraunama…</p>
            ) : (
                <div style={{ marginTop: 12 }}>
                    {(["sarasas", "korteles"] as const).map((kind) => (
                            <div key={kind} className={styles.equipmentItemRow} style={{ flexDirection: "column", alignItems: "stretch" }}>
                                <strong style={{ marginBottom: 8 }}>{kindLabel(kind)}</strong>
                                <ul className={styles.list} style={{ maxHeight: "none", marginTop: 0 }}>
                                    {rowsForKind(status, kind).map((row) => (
                                        <li
                                            key={`${row.kind}-${row.locale}`}
                                            className={`${styles.item} ${styles.templateLocaleRow}`}
                                            style={{ flexWrap: "wrap" }}
                                        >
                                            <div className={styles.equipmentItemMain}>
                                                <strong>{row.locale.toUpperCase()}</strong>
                                                <p className={styles.mutedSmall} style={{ margin: "6px 0 0" }}>
                                                    {sourceLabel(row)}
                                                    {row.source === "database" && row.originalFilename ? (
                                                        <>
                                                            {" "}
                                                            — <span>{row.originalFilename}</span>
                                                            {row.updatedAt ? (
                                                                <span>
                                                                    {" "}
                                                                    ({new Date(row.updatedAt).toLocaleString("lt-LT")})
                                                                </span>
                                                            ) : null}
                                                        </>
                                                    ) : null}
                                                </p>
                                            </div>
                                            <div className={styles.actions}>
                                                {row.source !== "none" ? (
                                                    <button
                                                        type="button"
                                                        className={`${styles.button} ${styles.buttonSecondary} ${styles.buttonCompact}`}
                                                        disabled={previewingPdf !== null || uploading !== null}
                                                        onClick={() => onPreviewPdf(kind, row.locale)}
                                                    >
                                                        {previewingPdf === uploadKey(kind, row.locale) ? "PDF…" : "Peržiūrėti PDF"}
                                                    </button>
                                                ) : null}
                                                {isAdmin ? (
                                                    <>
                                                        <DropZone
                                                            accept={[".doc", ".docx"]}
                                                            disabled={uploading !== null}
                                                            onFiles={(files) => {
                                                                const f = files[0];
                                                                if (f) {
                                                                    void uploadTemplateFile(kind, row.locale, f);
                                                                }
                                                            }}
                                                            className={styles.templateUploadDropZone}
                                                        >
                                                            <div className={styles.templateUploadDropInner}>
                                                                <p className={styles.templateUploadHint}>
                                                                    Nutempkite .doc / .docx čia arba pasirinkite failą.
                                                                </p>
                                                                <label
                                                                    className={`${styles.button} ${styles.buttonCompact} ${styles.templateUploadBrowse}`}
                                                                    style={{ cursor: "pointer" }}
                                                                >
                                                                    {uploading === uploadKey(kind, row.locale)
                                                                        ? "Įkeliama…"
                                                                        : "Pasirinkti failą"}
                                                                    <input
                                                                        type="file"
                                                                        accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                                                        style={{ display: "none" }}
                                                                        disabled={uploading !== null}
                                                                        onChange={(e) => {
                                                                            const file = e.target.files?.[0];
                                                                            e.target.value = "";
                                                                            if (file) {
                                                                                void uploadTemplateFile(kind, row.locale, file);
                                                                            }
                                                                        }}
                                                                    />
                                                                </label>
                                                            </div>
                                                        </DropZone>
                                                        {row.source === "database" ? (
                                                            <button
                                                                type="button"
                                                                className={`${styles.button} ${styles.buttonDanger} ${styles.buttonCompact}`}
                                                                disabled={uploading !== null}
                                                                onClick={() => onDelete(kind, row.locale)}
                                                            >
                                                                Šalinti iš DB
                                                            </button>
                                                        ) : null}
                                                    </>
                                                ) : null}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                    ))}
                </div>
            )}
        </div>
    );
}
