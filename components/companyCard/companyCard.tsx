"use client";

import { Building2, Pencil } from "lucide-react";
import Link from "next/link";
import { CompanyApi } from "@/lib/api/companies";
import styles from "./companyCard.module.scss";
import { useEffect, useState } from "react";
import { Company, companyLabels } from "@/lib/types/Company";

type CompanyCardProps = {
    id: number;
    company?: Company;
    variant?: "large" | "compact" | "mini";
};

const COMPACT_FIELDS: (keyof Company)[] = ["code", "companyType", "cityOrDistrict"];
const MINI_FIELDS: (keyof Company)[] = ["companyType"];

export default function CompanyCard({ id, company: companyProp, variant = "large" }: CompanyCardProps) {
    const [company, setCompany] = useState<Company | null>(companyProp ?? null);

    useEffect(() => {
        if (companyProp) {
            setCompany(companyProp);
            return;
        }
        CompanyApi.getById(id).then((res) => setCompany(res ?? null));
    }, [id, companyProp]);

    if (!company) {
        return <p>Kraunama įmonė...</p>;
    }

    const fieldsToRender =
        variant === "mini"
            ? Object.entries(company).filter(
                ([key, value]) =>
                    MINI_FIELDS.includes(key as keyof Company) && value !== null && value !== undefined
            )
            : variant === "compact"
            ? Object.entries(company).filter(
                ([key, value]) =>
                    COMPACT_FIELDS.includes(key as keyof Company) && value !== null && value !== undefined
            )
            : Object.entries(company).filter(([, value]) => value !== null && value !== undefined);

    return (
        <article
            className={`${styles.companyCard} ${variant === "compact" ? styles.compact : ""} ${variant === "mini" ? styles.mini : ""}`}
        >
            <div className={styles.cardHeader}>
                <div className={styles.cardIcon}>
                    <Building2 size={22} />
                </div>
                <h2 className={styles.companyName}>{company.companyName}</h2>
                <Link href={`/imone/${id}`} className={styles.editButton} title="Redaguoti įmonę">
                    <Pencil size={18} />
                </Link>
            </div>
            <dl className={styles.rekvizitai}>
                {fieldsToRender.map(([key, value]) => (
                    <div key={key} className={styles.row}>
                        <dt className={styles.label}>
                            {companyLabels[key as keyof Company] ?? key}
                        </dt>
                        <dd className={styles.value}>{value}</dd>
                    </div>
                ))}
            </dl>
        </article>
    );
}