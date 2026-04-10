"use client";

import { useCallback, useEffect, useState } from "react";
import styles from "../../page.module.scss";
import {
    AapEquipmentTemplateKind,
    AapEquipmentTemplateStatusRow,
    EquipmentApi,
} from "@/lib/api/equipment";
import { MessageStore } from "@/lib/globalVariables/messages";
import { setPDFToView } from "@/lib/globalVariables/pdfToView";

function kindLabel(kind: AapEquipmentTemplateKind): string {
    return kind === "sarasas" ? "AAP sąrašas" : "AAP kortelės + žiniaraščiai";
}

function sourceLabel(row: AapEquipmentTemplateStatusRow): string {
    if (row.source === "database") {
        return "Duomenų bazėje (įkeltas šablonas)";
    }
    if (row.source === "filesystem") {
        return "Failai serveryje (numatytasis)";
    }
    return "Šablonas nerastas — įkelkite .docx arba .doc";
}

export default function EquipmentTemplate() {
    const [role, setRole] = useState<string | null>(null);
    const [status, setStatus] = useState<AapEquipmentTemplateStatusRow[] | null>(null);
    const [uploading, setUploading] = useState<AapEquipmentTemplateKind | null>(null);
    const [previewingPdf, setPreviewingPdf] = useState<AapEquipmentTemplateKind | null>(null);

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

    const onUpload = async (kind: AapEquipmentTemplateKind, input: HTMLInputElement) => {
        const file = input.files?.[0];
        input.value = "";
        if (!file) return;
        const lower = file.name.toLowerCase();
        if (!lower.endsWith(".doc") && !lower.endsWith(".docx")) {
            MessageStore.push({
                title: "Netinkamas formatas",
                message: "Pasirinkite .doc arba .docx failą.",
                backgroundColor: "#e53e3e",
            });
            return;
        }
        setUploading(kind);
        try {
            await EquipmentApi.uploadAapTemplate(kind, file);
            MessageStore.push({
                title: "Įkelta",
                message: `${kindLabel(kind)}: šablonas išsaugotas duomenų bazėje.`,
                backgroundColor: "#16a34a",
            });
            loadStatus();
        } catch {
            /* api.ts jau rodo klaidą */
        } finally {
            setUploading(null);
        }
    };

    const onPreviewPdf = async (kind: AapEquipmentTemplateKind) => {
        setPreviewingPdf(kind);
        try {
            const pdf = await EquipmentApi.getAapTemplatePdf(kind);
            setPDFToView(pdf);
        } catch {
            /* api.ts jau rodo klaidą */
        } finally {
            setPreviewingPdf(null);
        }
    };

    const onDelete = async (kind: AapEquipmentTemplateKind) => {
        if (!window.confirm(`Pašalinti įkeltą šabloną „${kindLabel(kind)}“? Naudosis numatytasis serverio failas (jei yra).`)) {
            return;
        }
        try {
            await EquipmentApi.deleteAapTemplate(kind);
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
            <p className={styles.itemText}>
                Dokumentai generuojami iš sistemos duomenų (darbuotojai ir priskirtos priemonės). Galite įkelti savo Word
                šabloną — jis saugomas duomenų bazėje ir naudojamas vietoj numatytųjų failų serveryje.
            </p>
            <p className={styles.muted}>
                Lentelė neprivaloma. Įmonės laukai visada: sąrašui <code>{"${Kompanija}"}</code> ir kt.; kortelėms{" "}
                <code>{"${Kompanija}"}</code>, <code>{"${TIPAS}"}</code>, <code>{"${data}"}</code>. Jei norite lentelės:
                sąrašui eilutėje <code>{"${pareigybe}"}</code> arba <code>{"${pareigybes}"}</code>,{" "}
                <code>{"${priemones}"}</code>, <code>{"${terminas}"}</code>; be lentelės įdėkite vieną iš{" "}
                <code>{"${sarasas_turinys}"}</code>, <code>{"${sarasas_duomenys}"}</code>, <code>{"${aap_sarasas}"}</code>{" "}
                (tekstas su eilutėmis). Kortelėms: <code>{"${pareigybes}"}</code> ir lentelė su{" "}
                <code>{"${priemones}"}</code>, <code>{"${terminas}"}</code>, <code>{"${kiekis}"}</code>,{" "}
                <code>{"${vnt}"}</code> arba <code>{"${korteles_turinys}"}</code> / <code>{"${aap_korteles}"}</code>.
            </p>

            {!status ? (
                <p className={styles.muted}>Kraunama būsena…</p>
            ) : (
                <ul className={styles.list} style={{ maxHeight: "none", marginTop: 12 }}>
                    {status.map((row) => (
                        <li key={row.kind} className={styles.item} style={{ flexWrap: "wrap" }}>
                            <div className={styles.equipmentItemMain}>
                                <strong>{kindLabel(row.kind)}</strong>
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
                                        onClick={() => onPreviewPdf(row.kind)}
                                    >
                                        {previewingPdf === row.kind ? "PDF…" : "Peržiūrėti PDF"}
                                    </button>
                                ) : null}
                            {isAdmin ? (
                                <>
                                    <label className={`${styles.button} ${styles.buttonCompact}`} style={{ cursor: "pointer" }}>
                                        {uploading === row.kind ? "Įkeliama…" : "Įkelti .doc / .docx"}
                                        <input
                                            type="file"
                                            accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                            style={{ display: "none" }}
                                            disabled={uploading !== null}
                                            onChange={(e) => onUpload(row.kind, e.target)}
                                        />
                                    </label>
                                    {row.source === "database" ? (
                                        <button
                                            type="button"
                                            className={`${styles.button} ${styles.buttonDanger} ${styles.buttonCompact}`}
                                            disabled={uploading !== null}
                                            onClick={() => onDelete(row.kind)}
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
            )}

            {!isAdmin ? (
                <p className={styles.helpNote} style={{ marginTop: 12 }}>
                    Šablonų įkėlimą gali atlikti tik administratorius (ROLE_ADMIN).
                </p>
            ) : (
                <p className={styles.mutedSmall} style={{ marginTop: 12 }}>
                    .doc failams serveryje turi būti įdiegtas LibreOffice (konvertavimui į .docx).
                </p>
            )}
        </div>
    );
}
