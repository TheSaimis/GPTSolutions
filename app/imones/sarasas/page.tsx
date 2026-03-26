"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import PageBackBar from "@/components/navigation/PageBackBar";
import CompanyExpandableRow from "@/components/companyList/CompanyExpandableRow";
import { CompanyApi } from "@/lib/api/companies";
import type { Company } from "@/lib/types/Company";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import styles from "./page.module.scss";
import { Building2, SlidersHorizontal, X } from "lucide-react";
import {
    COMPANY_SORT_OPTIONS,
    DELETED_STATUS_OPTIONS,
    buildCompanyTypeOptions,
    sortCompanies,
    matchesDeletedFilter,
    type DeletedFilter,
} from "@/lib/filters";

export default function ImoniuSarasasPage() {
    const [companies, setCompanies] = useState<Company[] | null>(null);
    const [search, setSearch] = useState("");
    const [selectedType, setSelectedType] = useState("all");
    const [sortBy, setSortBy] = useState("name-asc");
    const [deletedFilter, setDeletedFilter] = useState<DeletedFilter>("active");
    const [filtersOpen, setFiltersOpen] = useState(false);

    useEffect(() => {
        document.title = "Įmonių sąrašas";
        CompanyApi.getAll()
            .then((data) => setCompanies(Array.isArray(data) ? data : []))
            .catch(() => setCompanies([]));
    }, []);

    const companyTypeOptions = useMemo(
        () => buildCompanyTypeOptions(companies ?? []),
        [companies]
    );

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
            if (!matchesDeletedFilter(company, deletedFilter)) return false;
            const matchesType = selectedType === "all" || company.companyType === selectedType;
            if (!matchesType) return false;
            return matchesSearch(company);
        });

        return sortCompanies(list, sortBy);
    }, [companies, search, selectedType, sortBy, deletedFilter]);

    const sortLabel = useMemo(
        () => COMPANY_SORT_OPTIONS.find((o) => o.value === sortBy)?.label ?? sortBy,
        [sortBy]
    );

    const activeFilterCount = [
        selectedType !== "all",
        sortBy !== "name-asc",
        deletedFilter !== "active",
    ].filter(Boolean).length;

    const filterFields = (
        <>
            <InputFieldSelect
                key={`type-${selectedType}`}
                options={companyTypeOptions}
                selected={selectedType === "all" ? "Visi tipai" : selectedType}
                placeholder="Įmonės tipas"
                onChange={setSelectedType}
            />
            <InputFieldSelect
                options={DELETED_STATUS_OPTIONS}
                placeholder="Būsena"
                selected={DELETED_STATUS_OPTIONS.find((o) => o.value === deletedFilter)?.label ?? "Būsena"}
                onChange={(v) => setDeletedFilter(v as DeletedFilter)}
            />
            <InputFieldSelect
                key={`sort-${sortBy}`}
                options={COMPANY_SORT_OPTIONS}
                selected={sortLabel}
                placeholder="Rikiavimas"
                onChange={setSortBy}
            />
        </>
    );

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <PageBackBar />
            </div>

            <div className={styles.content}>
                <div className={styles.titleRow}>
                    <h1 className={styles.pageTitle}>Įmonių sąrašas</h1>
                    <Link href="/imones" className={styles.createButton}>
                        <Building2 size={18} />
                        Pridėti įmonę
                    </Link>
                </div>

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
                            <button
                                type="button"
                                className={`${styles.filterToggle} ${filtersOpen ? styles.filterToggleActive : ""}`}
                                onClick={() => setFiltersOpen(true)}
                            >
                                <SlidersHorizontal size={18} />
                                Filtrai
                                {activeFilterCount > 0 && (
                                    <span className={styles.filterBadge}>{activeFilterCount}</span>
                                )}
                            </button>
                        </div>

                        <div className={styles.filtersRow}>
                            {filterFields}
                        </div>
                    </section>
                )}

                {filtersOpen && (
                    <>
                        <div className={styles.drawerOverlay} onClick={() => setFiltersOpen(false)} />
                        <div className={styles.drawer}>
                            <div className={styles.drawerHeader}>
                                <h2>Filtrai</h2>
                                <button type="button" className={styles.drawerClose} onClick={() => setFiltersOpen(false)}>
                                    <X size={20} />
                                </button>
                            </div>
                            <div className={styles.drawerBody}>
                                {filterFields}
                            </div>
                        </div>
                    </>
                )}

                {companies === null ? (
                    <p className={styles.message}>Kraunama...</p>
                ) : companies.length === 0 ? (
                    <p className={styles.message}>Įmonių nėra. Pridėkite įmonę puslapyje „Pridėti įmonę".</p>
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
