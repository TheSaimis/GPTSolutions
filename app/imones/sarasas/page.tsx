"use client";

import { useEffect, useMemo, useState } from "react";
import { ArrowLeft } from "lucide-react";
import Link from "next/link";
import CompanyCard from "@/components/companyCard/companyCard";
import { CompanyApi } from "@/lib/api/companies";
import type { Company } from "@/lib/types/Company";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import styles from "./page.module.scss";

export default function ImoniuSarasasPage() {
    const [companies, setCompanies] = useState<Company[] | null>(null);
    const [search, setSearch] = useState("");
    const [selectedType, setSelectedType] = useState("Visi tipai");
    const [sortBy, setSortBy] = useState("name-asc");
    const [viewMode, setViewMode] = useState<"large" | "compact" | "mini">("large");

    useEffect(() => {
        document.title = "Įmonių sąrašas";
        CompanyApi.getAll()
            .then((data) => setCompanies(Array.isArray(data) ? data : []))
            .catch(() => setCompanies([]));
    }, []);

    const companyTypes = useMemo(() => {
        if (!companies) return ["Visi tipai"];
        const types = Array.from(
            new Set(
                companies
                    .map((company) => company.companyType?.trim())
                    .filter((value): value is string => Boolean(value))
            )
        ).sort((a, b) => a.localeCompare(b, "lt"));
        return ["Visi tipai", ...types];
    }, [companies]);

    const filteredCompanies = useMemo(() => {
        if (!companies) return [];

        const searchLower = search.trim().toLowerCase();
        const startsWithSearch = (value?: string) => (value ?? "").toLowerCase().startsWith(searchLower);
        const list = companies.filter((company) => {
            const matchesType = selectedType === "Visi tipai" || company.companyType === selectedType;
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
                            <InputFieldSelect
                                options={companyTypes}
                                selected={"Imonės tipas"}
                                placeholder="Pasirinkite tipą"
                                onChange={setSelectedType}
                            />
                            <InputFieldSelect
                                options={[
                                    { value: "Nerikiuoti", label: "Nerikiuoti" },
                                    { value: "name-asc", label: "Pagal pavadinimą (A-Z)" },
                                    { value: "name-desc", label: "Pagal pavadinimą (Z-A)" },
                                    { value: "code-asc", label: "Pagal kodą (A-Z)" },
                                    { value: "code-desc", label: "Pagal kodą (Z-A)" },
                                    { value: "created-newest", label: "Pagal sukurimo datą (Nuo naujausių)" },
                                    { value: "created-oldest", label: "Pagal sukurimo datą (Nuo seniausių)" },
                                ]}
                                selected={"Rikiavimas"}
                                placeholder="Pasirinkite rikiavimo tipą"
                                onChange={setSortBy}
                            />
                            <InputFieldSelect
                                options={[
                                    { value: "large", label: "Kortelėmis" },
                                    { value: "compact", label: "Eilutėmis" },
                                    { value: "mini", label: "Kompaktiškas" },
                                ]}
                                selected={"Įmonių išdėstymas"}
                                placeholder="Įmonių išdėstymas"
                                onChange={setViewMode as any}
                            />
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
