"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { ChevronDown, Pencil, Tags } from "lucide-react";
import type { CompanyTypeRow } from "@/lib/types/Company";
import styles from "./CompanyTypeExpandableRow.module.scss";

const SHORT_FIELDS: { key: keyof CompanyTypeRow; label: string }[] = [
    { key: "typeShort", label: "LT" },
    { key: "typeShortEn", label: "EN" },
    { key: "typeShortRu", label: "RU" },
];

const FULL_FIELDS: { key: keyof CompanyTypeRow; label: string }[] = [
    { key: "type", label: "LT" },
    { key: "typeEn", label: "EN" },
    { key: "typeRu", label: "RU" },
];

type Props = {
    row: CompanyTypeRow;
};

function display(v: unknown): string {
    if (v == null) return "—";
    const s = String(v).trim();
    return s === "" ? "—" : s;
}

export default function CompanyTypeExpandableRow({ row }: Props) {
    const [expanded, setExpanded] = useState(false);
    const [role, setRole] = useState("");

    useEffect(() => {
        setRole(localStorage.getItem("role") || "");
    }, []);

    const shortLt = row.typeShort ?? "—";
    const fullLt = row.type ?? "—";
    const hasTranslations = Boolean(
        (row.typeShortEn && row.typeShortEn.trim()) ||
            (row.typeShortRu && row.typeShortRu.trim()) ||
            (row.typeEn && row.typeEn.trim()) ||
            (row.typeRu && row.typeRu.trim())
    );

    return (
        <article className={styles.wrap}>
            <button
                type="button"
                className={styles.summary}
                onClick={() => setExpanded((e) => !e)}
                aria-expanded={expanded}
            >
                <span className={styles.chevron} data-open={expanded}>
                    <ChevronDown size={20} aria-hidden />
                </span>
                <span className={styles.cellShort}>{shortLt}</span>
                <span className={styles.cellFull}>{fullLt}</span>
                <span className={styles.cellExtra}>{hasTranslations ? "EN / RU" : "—"}</span>
                {role === "ROLE_ADMIN" && (
                    <span className={styles.editWrap} onClick={(e) => e.stopPropagation()}>
                        <Link href={`/imones/tipai/${row.id}`} className={styles.editLink} title="Redaguoti tipą">
                            <Pencil size={18} />
                        </Link>
                    </span>
                )}
            </button>

            {expanded && (
                <div className={styles.details}>
                    <div className={styles.detailsInner}>
                        <div className={styles.detailsHeader}>
                            <Tags size={20} className={styles.detailsIcon} />
                            <span>Visi laukai</span>
                        </div>
                        <div className={styles.detailBody}>
                            <div className={styles.idRow}>
                                <span className={styles.idLabel}>ID</span>
                                <span className={styles.idValue}>{display(row.id)}</span>
                            </div>
                            <div className={styles.nameColumns}>
                                <div className={styles.nameColumn}>
                                    <h4 className={styles.columnTitle}>Trumpi pavadinimai</h4>
                                    <dl className={styles.columnDl}>
                                        {SHORT_FIELDS.map(({ key, label }) => (
                                            <div key={key} className={styles.fieldRow}>
                                                <dt className={styles.langTag}>{label}</dt>
                                                <dd className={styles.fieldValue}>{display(row[key])}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                </div>
                                <div className={styles.nameColumn}>
                                    <h4 className={styles.columnTitle}>Pilni pavadinimai</h4>
                                    <dl className={styles.columnDl}>
                                        {FULL_FIELDS.map(({ key, label }) => (
                                            <div key={key} className={styles.fieldRow}>
                                                <dt className={styles.langTag}>{label}</dt>
                                                <dd className={styles.fieldValue}>{display(row[key])}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </article>
    );
}
