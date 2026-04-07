"use client";

import { use, useEffect, useState } from "react";
import Link from "next/link";
import { Tags, Save } from "lucide-react";
import PageBackBar from "@/components/navigation/PageBackBar";
import { CompanyTypeApi } from "@/lib/api/companyTypes";
import { MessageStore } from "@/lib/globalVariables/messages";
import InputFieldText from "@/components/inputFields/inputFieldText";
import styles from "./page.module.scss";

type PageParams = Promise<{ id: string }>;

function emptyToNull(s: string): string | null {
    const t = s.trim();
    return t === "" ? null : t;
}

export default function ImonesTipoRedagavimasPage({ params }: { params: PageParams }) {
    const { id: idParam } = use(params);
    const id = typeof idParam === "string" ? parseInt(idParam, 10) : NaN;

    const [loading, setLoading] = useState(!Number.isNaN(id));
    const [role, setRole] = useState("");
    const [typeShort, setTypeShort] = useState("");
    const [typeShortEn, setTypeShortEn] = useState("");
    const [typeShortRu, setTypeShortRu] = useState("");
    const [type, setType] = useState("");
    const [typeEn, setTypeEn] = useState("");
    const [typeRu, setTypeRu] = useState("");
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setRole(localStorage.getItem("role") || "");
    }, []);

    useEffect(() => {
        if (Number.isNaN(id)) return;
        document.title = "Redaguoti įmonės tipą";
        CompanyTypeApi.getById(id)
            .then((r) => {
                setTypeShort(r.typeShort ?? "");
                setTypeShortEn(r.typeShortEn ?? "");
                setTypeShortRu(r.typeShortRu ?? "");
                setType(r.type ?? "");
                setTypeEn(r.typeEn ?? "");
                setTypeRu(r.typeRu ?? "");
            })
            .catch(() => {
                MessageStore.push({
                    title: "Klaida",
                    message: "Nepavyko įkelti tipo",
                    backgroundColor: "#e53e3e",
                });
            })
            .finally(() => setLoading(false));
    }, [id]);

    async function handleSubmit() {
        if (Number.isNaN(id) || role !== "ROLE_ADMIN") return;
        if (!typeShort.trim() || !type.trim()) {
            MessageStore.push({
                title: "Klaida",
                message: "Trumpas ir pilnas pavadinimas (LT) yra privalomi.",
                backgroundColor: "#e53e3e",
            });
            return;
        }
        setSaving(true);
        try {
            await CompanyTypeApi.update(id, {
                typeShort: typeShort.trim(),
                type: type.trim(),
                typeShortEn: emptyToNull(typeShortEn),
                typeShortRu: emptyToNull(typeShortRu),
                typeEn: emptyToNull(typeEn),
                typeRu: emptyToNull(typeRu),
            });
            MessageStore.push({
                title: "Sėkmingai",
                message: "Įmonės tipas atnaujintas",
                backgroundColor: "#22C55E",
            });
        } catch (e) {
            MessageStore.push({
                title: "Klaida",
                message: (e as Error)?.message ?? "Nepavyko išsaugoti",
                backgroundColor: "#e53e3e",
            });
        } finally {
            setSaving(false);
        }
    }

    const isAdmin = role === "ROLE_ADMIN";

    if (loading) {
        return (
            <div className={styles.page}>
                <p className={styles.message}>Kraunama...</p>
            </div>
        );
    }

    if (Number.isNaN(id)) {
        return (
            <div className={styles.page}>
                <p className={styles.message}>Neteisingas tipo ID.</p>
                <Link href="/imones/tipai">Grįžti į sąrašą</Link>
            </div>
        );
    }

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <PageBackBar />
            </div>

            <div className={styles.card}>
                <div className={styles.cardHeader}>
                    <div className={styles.fileIcon}>
                        <Tags size={24} />
                    </div>
                    <div>
                        <h1 className={styles.title}>Redaguoti įmonės tipą</h1>
                        <p className={styles.subtitle}>
                            Trumpi ir pilni pavadinimai lietuvių, anglų ir rusų kalbomis (ID: {id})
                        </p>
                    </div>
                </div>

                <div className={styles.divider} />

                {!isAdmin && (
                    <p className={styles.hint}>
                        Redaguoti gali tik administratorius. Žemiau rodomi duomenys peržiūrai.
                    </p>
                )}

                <div className={styles.form}>
                    <div className={styles.langBlock}>
                        <h2 className={styles.langTitle}>Lietuvių kalba</h2>
                        <InputFieldText
                            value={typeShort}
                            onChange={setTypeShort}
                            placeholder="Trumpas pavadinimas (pvz. UAB)"
                            disabled={!isAdmin}
                        />
                        <InputFieldText
                            value={type}
                            onChange={setType}
                            placeholder="Pilnas pavadinimas (pvz. Uždaroji akcinė bendrovė)"
                            disabled={!isAdmin}
                        />
                    </div>

                    <div className={styles.langBlock}>
                        <h2 className={styles.langTitle}>Anglų kalba</h2>
                        <div className={styles.row}>
                            <InputFieldText
                                value={typeShortEn}
                                onChange={setTypeShortEn}
                                placeholder="Trumpas (EN)"
                                disabled={!isAdmin}
                            />
                            <InputFieldText
                                value={typeEn}
                                onChange={setTypeEn}
                                placeholder="Pilnas pavadinimas (EN)"
                                disabled={!isAdmin}
                            />
                        </div>
                    </div>

                    <div className={styles.langBlock}>
                        <h2 className={styles.langTitle}>Rusų kalba</h2>
                        <div className={styles.row}>
                            <InputFieldText
                                value={typeShortRu}
                                onChange={setTypeShortRu}
                                placeholder="Trumpas (RU)"
                                disabled={!isAdmin}
                            />
                            <InputFieldText
                                value={typeRu}
                                onChange={setTypeRu}
                                placeholder="Pilnas pavadinimas (RU)"
                                disabled={!isAdmin}
                            />
                        </div>
                    </div>
                </div>

                {isAdmin && (
                    <button
                        type="button"
                        className={styles.submitButton}
                        onClick={handleSubmit}
                        disabled={saving}
                    >
                        <Save size={18} />
                        {saving ? "Saugoma..." : "Išsaugoti"}
                    </button>
                )}
            </div>
        </div>
    );
}
