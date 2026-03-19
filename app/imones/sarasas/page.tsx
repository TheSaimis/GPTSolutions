"use client";

import { useEffect, useMemo, useState } from "react";
import { ArrowLeft } from "lucide-react";
import Link from "next/link";
import CompanyCard from "@/components/companyCard/companyCard";
import { CompanyApi } from "@/lib/api/companies";
import type { Company } from "@/lib/types/Company";
import styles from "./page.module.scss";

export default function ImoniuSarasasPage() {
    const [companies, setCompanies] = useState<Company[] | null>(null);
    const [search, setSearch] = useState("");
    const [selectedType, setSelectedType] = useState("all");
    const [sortBy, setSortBy] = useState("name-asc");
    const [viewMode, setViewMode] = useState<"large" | "compact" | "mini">("large");

    useEffect(() => {
        document.title = "Įmonių sąrašas";
        CompanyApi.getAll()
            .then((data) => setCompanies(Array.isArray(data) ? data : []))
            .catch(() => setCompanies([]));
    }, []);

    const companyTypes = useMemo(() => {
        if (!companies) return [];
        return Array.from(
            new Set(
                companies
                    .map((company) => company.companyType?.trim())
                    .filter((value): value is string => Boolean(value))
            )
        ).sort((a, b) => a.localeCompare(b, "lt"));
    }, [companies]);

    const filteredCompanies = useMemo(() => {
        if (!companies) return [];

        const searchLower = search.trim().toLowerCase();
        const startsWithSearch = (value?: string) => (value ?? "").toLowerCase().startsWith(searchLower);
        const list = companies.filter((company) => {
            const matchesType = selectedType === "all" || company.companyType === selectedType;
            if (!matchesType) return false;

            if (!searchLower) return true;
            return startsWithSearch(company.companyName);
        });

        const sortedList = [...list];
        sortedList.sort((a, b) => {
            if (sortBy === "name-asc") {
                return (a.companyName ?? "").localeCompare(b.companyName ?? "", "lt");
            }
            if (sortBy === "name-desc") {
                return (b.companyName ?? "").localeCompare(a.companyName ?? "", "lt");
            }
            if (sortBy === "code-asc") {
                return (a.code ?? "").localeCompare(b.code ?? "", "lt");
            }
            if (sortBy === "code-desc") {
                return (b.code ?? "").localeCompare(a.code ?? "", "lt");
            }
            if (sortBy === "created-newest") {
                return new Date(b.createdAt ?? 0).getTime() - new Date(a.createdAt ?? 0).getTime();
            }
            if (sortBy === "created-oldest") {
                return new Date(a.createdAt ?? 0).getTime() - new Date(b.createdAt ?? 0).getTime();
            }
            return 0;
        });

        return sortedList;
    }, [companies, search, selectedType, sortBy]);

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
                {companies && companies.length > 0 && (
                    <section className={styles.controls}>
                        <div className={styles.searchRow}>
                            <input
                                type="text"
                                placeholder="Paieška pagal pavadinimą, kodą, adresą..."
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                className={styles.searchInput}
                            />
                        </div>

                        <div className={styles.filtersRow}>
                            <select
                                value={selectedType}
                                onChange={(event) => setSelectedType(event.target.value)}
                                className={styles.select}
                            >
                                <option value="all">Visi įmonių tipai</option>
                                {companyTypes.map((type) => (
                                    <option key={type} value={type}>
                                        {type}
                                    </option>
                                ))}
                            </select>

                            <select
                                value={sortBy}
                                onChange={(event) => setSortBy(event.target.value)}
                                className={styles.select}
                            >
                                <option value="name-asc">Rikiuoti: pavadinimas A-Z</option>
                                <option value="name-desc">Rikiuoti: pavadinimas Z-A</option>
                                <option value="code-asc">Rikiuoti: kodas A-Z</option>
                                <option value="code-desc">Rikiuoti: kodas Z-A</option>
                                <option value="created-newest">Rikiuoti: naujausios pirmiau</option>
                                <option value="created-oldest">Rikiuoti: seniausios pirmiau</option>
                            </select>

                            <select
                                value={viewMode}
                                onChange={(event) =>
                                    setViewMode(event.target.value as "large" | "compact" | "mini")
                                }
                                className={styles.select}
                            >
                                <option value="large">Išdėstymas: dideliais langeliais</option>
                                <option value="compact">Išdėstymas: eilutėmis</option>
                                <option value="mini">Išdėstymas: mažais langeliais</option>
                            </select>
                        </div>
                    </section>
                )}
                {companies === null ? (
                    <p className={styles.message}>Kraunama...</p>
                ) : companies.length === 0 ? (
                    <p className={styles.message}>Įmonių nėra. Pridėkite įmonę puslapyje „Pridėti įmonę“.</p>
                ) : filteredCompanies.length === 0 ? (
                    <p className={styles.message}>Pagal pasirinktus filtrus įmonių nerasta.</p>
                ) : (
                    <div
                        className={`${styles.cardList} ${viewMode === "compact" ? styles.compactList : ""} ${viewMode === "mini" ? styles.miniList : ""}`}
                    >
                        {filteredCompanies.map((company) =>
                            company.id != null ? (
                                <CompanyCard key={company.id} id={company.id} company={company} variant={viewMode} />
                            ) : null
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
