"use client";

import { useEffect, useState } from "react";
import { ArrowLeft } from "lucide-react";
import Link from "next/link";
import CompanyCard from "@/components/companyCard/companyCard";
import { CompanyApi } from "@/lib/api/companies";
import type { Company } from "@/lib/types/Company";
import styles from "./page.module.scss";

export default function ImoniuSarasasPage() {
    const [companies, setCompanies] = useState<Company[] | null>(null);

    useEffect(() => {
        document.title = "Įmonių sąrašas";
        CompanyApi.getAll()
            .then((data) => setCompanies(Array.isArray(data) ? data : []))
            .catch(() => setCompanies([]));
    }, []);

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <Link href="/" className={styles.backLink}>
                    <ArrowLeft size={16} />
                    Grįžti į pradžią
                </Link>
            </div>

            <div className={styles.content}>
                <h1 className={styles.pageTitle}>Įmonių sąrašas</h1>
                {companies === null ? (
                    <p className={styles.message}>Kraunama...</p>
                ) : companies.length === 0 ? (
                    <p className={styles.message}>Įmonių nėra. Pridėkite įmonę puslapyje „Pridėti įmonę“.</p>
                ) : (
                    <div className={styles.cardList}>
                        {companies.map((company) =>
                            company.id != null ? (
                                <CompanyCard key={company.id} id={company.id} />
                            ) : null
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
