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

    const onUpload = async (kind: AapEquipmentTemplateKind, loc: AapTemplateLocale, input: HTMLInputElement) => {
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
            <p className={styles.itemText}>
                Dokumentai generuojami iš sistemos duomenų (darbuotojai ir priskirtos priemonės). Galite įkelti savo Word
                šabloną kiekvienai kalbai (LT, EN, RU) — jis saugomas duomenų bazėje ir naudojamas vietoj numatytųjų failų
                serveryje (pvz. <code>sarasas-aap EN.docx</code>).
            </p>
            <p className={styles.muted}>
                Lentelė neprivaloma. Įmonės laukai visada: sąrašui <code>{"${Kompanija}"}</code> ir kt.; kortelėms{" "}
                <code>{"${Kompanija}"}</code>, <code>{"${TIPAS}"}</code>, <code>{"${data}"}</code>. Jei norite lentelės:
                sąrašui eilutėje <code>{"${pareigybe}"}</code> arba <code>{"${pareigybes}"}</code>,{" "}
                <code>{"${priemones}"}</code>, <code>{"${terminas}"}</code>,{" "}
                <code>{"${eilNr}"}</code> (eilės numeris 1, 2, 3… — be <code>#1</code> šablone); be lentelės įdėkite vieną iš{" "}
                <code>{"${sarasas_turinys}"}</code>, <code>{"${sarasas_duomenys}"}</code>, <code>{"${aap_sarasas}"}</code>{" "}
                (tekstas su eilutėmis). Kortelėms: <code>{"${pareigybes}"}</code> ir lentelė su{" "}
                <code>{"${priemones}"}</code>, <code>{"${terminas}"}</code>, <code>{"${kiekis}"}</code>,{" "}
                <code>{"${vnt}"}</code>, <code>{"${pagrindas}"}</code>, <code>{"${eilNr}"}</code> (eilės Nr.; nenaudokite{" "}
                <code>{"${eil Nr# 1}"}</code> — rašykite tik <code>{"${eilNr}"}</code>) arba{" "}
                <code>{"${korteles_turinys}"}</code> / <code>{"${aap_korteles}"}</code>.
            </p>

            {!status ? (
                <p className={styles.muted}>Kraunama būsena…</p>
            ) : (
                <div style={{ marginTop: 12 }}>
                    {(["sarasas", "korteles"] as const).map((kind) => (
                            <div key={kind} className={styles.equipmentItemRow} style={{ flexDirection: "column", alignItems: "stretch" }}>
                                <strong style={{ marginBottom: 8 }}>{kindLabel(kind)}</strong>
                                <ul className={styles.list} style={{ maxHeight: "none", marginTop: 0 }}>
                                    {rowsForKind(status, kind).map((row) => (
                                        <li
                                            key={`${row.kind}-${row.locale}`}
                                            className={styles.item}
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
                                                        <label
                                                            className={`${styles.button} ${styles.buttonCompact}`}
                                                            style={{ cursor: "pointer" }}
                                                        >
                                                            {uploading === uploadKey(kind, row.locale) ? "Įkeliama…" : "Įkelti .doc / .docx"}
                                                            <input
                                                                type="file"
                                                                accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                                                style={{ display: "none" }}
                                                                disabled={uploading !== null}
                                                                onChange={(e) => onUpload(kind, row.locale, e.target)}
                                                            />
                                                        </label>
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
