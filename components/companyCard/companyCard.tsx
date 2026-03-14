"use client";

import { Building2, Pencil } from "lucide-react";
import Link from "next/link";
import { CompanyApi } from "@/lib/api/companies";
import styles from "./companyCard.module.scss";
import { useEffect, useState } from "react";
import { Company, companyLabels } from "@/lib/types/Company";

export default function CompanyCard({ id }: { id: number }) {
    const [company, setCompany] = useState<Company | null>(null);

    useEffect(() => {
        CompanyApi.getById(id).then((res) => setCompany(res ?? null));
    }, [id]);

    if (!company) {
        return <p>Kraunama įmonė...</p>;
    }

    return (
        <article className={styles.companyCard}>
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
                {Object.entries(company)
                    .filter(([, value]) => value !== null && value !== undefined)
                    .map(([key, value]) => (
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