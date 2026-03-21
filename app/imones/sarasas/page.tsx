"use client";

import { useEffect, useMemo, useState } from "react";
import PageBackBar from "@/components/navigation/PageBackBar";
import CompanyExpandableRow from "@/components/companyList/CompanyExpandableRow";
import { CompanyApi } from "@/lib/api/companies";
import type { Company } from "@/lib/types/Company";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import styles from "./page.module.scss";

const SORT_OPTIONS: { value: string; label: string }[] = [
    { value: "name-asc", label: "Pagal pavadinimą (A-Z)" },
    { value: "name-desc", label: "Pagal pavadinimą (Z-A)" },
    { value: "code-asc", label: "Pagal kodą (A-Z)" },
    { value: "code-desc", label: "Pagal kodą (Z-A)" },
    { value: "created-newest", label: "Pagal sukurimo datą (nuo naujausių)" },
    { value: "created-oldest", label: "Pagal sukurimo datą (nuo seniausių)" },
];

export default function ImoniuSarasasPage() {
    const [companies, setCompanies] = useState<Company[] | null>(null);
    const [search, setSearch] = useState("");
    const [selectedType, setSelectedType] = useState("Visi tipai");
    const [sortBy, setSortBy] = useState("name-asc");

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
        const matchesSearch = (company: Company) => {
            if (!searchLower) return true;
            const hay = [
                company.companyName,
                company.code,
                company.address,
                company.cityOrDistrict,
            ]
                .filter(Boolean)
                .join(" ")
                .toLowerCase();
            return hay.includes(searchLower);
        };
        const list = companies.filter((company) => {
            const matchesType = selectedType === "Visi tipai" || company.companyType === selectedType;
            if (!matchesType) return false;
            return matchesSearch(company);
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

    const sortLabel = useMemo(() => {
        return SORT_OPTIONS.find((o) => o.value === sortBy)?.label ?? sortBy;
    }, [sortBy]);

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <PageBackBar />
            </div>

            <div className={styles.content}>
                <h1 className={styles.pageTitle}>Įmonių sąrašas</h1>
                {companies && companies.length > 0 && (
                    <section className={styles.controls}>
                        <div className={styles.searchRow}>
                            <input
                                type="text"
                                placeholder="Paieška pagal pavadinimą, kodą, adresą, miestą..."
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                className={styles.searchInput}
                            />
                        </div>

                        <div className={styles.filtersRow}>
                            <InputFieldSelect
                                key={`type-${selectedType}`}
                                options={companyTypes}
                                selected={selectedType}
                                placeholder="Įmonės tipas"
                                onChange={setSelectedType}
                            />
                            <InputFieldSelect
                                key={`sort-${sortBy}`}
                                options={SORT_OPTIONS}
                                selected={sortLabel}
                                placeholder="Rikiavimas"
                                onChange={setSortBy}
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
                    <div className={styles.expandableList}>
                        <div className={styles.listHeader} aria-hidden>
                            <span className={styles.hChevron} />
                            <span>Tipas</span>
                            <span>Pavadinimas</span>
                            <span>Kodas</span>
                            <span className={styles.hActions} />
                        </div>
                        {filteredCompanies.map((company) =>
                            company.id != null ? (
                                <CompanyExpandableRow key={company.id} company={company} />
                            ) : null
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
