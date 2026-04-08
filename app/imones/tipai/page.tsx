"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import PageBackBar from "@/components/navigation/PageBackBar";
import CompanyTypeExpandableRow from "@/components/companyTypeList/CompanyTypeExpandableRow";
import { CompanyTypeApi } from "@/lib/api/companyTypes";
import type { CompanyTypeRow } from "@/lib/types/Company";
import styles from "./page.module.scss";
import { ChevronLeft, ChevronRight, List, Plus } from "lucide-react";

const PAGE_SIZE = 15;

export default function ImoniuTipaiPage() {
    const [rows, setRows] = useState<CompanyTypeRow[] | null>(null);
    const [search, setSearch] = useState("");
    const [page, setPage] = useState(1);

    useEffect(() => {
        document.title = "Įmonių tipai";
        CompanyTypeApi.getAll()
            .then((data) => setRows(Array.isArray(data) ? data : []))
            .catch(() => setRows([]));
    }, []);

    const filtered = useMemo(() => {
        if (!rows) return [];
        const q = search.trim().toLowerCase();
        if (!q) return rows;
        return rows.filter((r) => {
            const hay = [
                r.typeShort,
                r.typeShortEn,
                r.typeShortRu,
                r.type,
                r.typeEn,
                r.typeRu,
            ]
                .filter(Boolean)
                .join(" ")
                .toLowerCase();
            return hay.includes(q);
        });
    }, [rows, search]);

    const totalFiltered = filtered.length;
    const totalPages = Math.max(1, Math.ceil(totalFiltered / PAGE_SIZE));
    const currentPage = Math.min(page, totalPages);

    useEffect(() => {
        setPage(1);
    }, [search]);

    useEffect(() => {
        setPage((p) => Math.min(p, totalPages));
    }, [totalPages]);

    const paginated = useMemo(() => {
        const start = (currentPage - 1) * PAGE_SIZE;
        return filtered.slice(start, start + PAGE_SIZE);
    }, [filtered, currentPage]);

    const rangeFrom = totalFiltered === 0 ? 0 : (currentPage - 1) * PAGE_SIZE + 1;
    const rangeTo = Math.min(currentPage * PAGE_SIZE, totalFiltered);

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <PageBackBar />
            </div>

            <div className={styles.content}>
                <div className={styles.titleRow}>
                    <h1 className={styles.pageTitle}>Įmonių tipai</h1>
                    <div className={styles.titleActions}>
                        <Link href="/imones/tipai/naujas" className={styles.createLink}>
                            <Plus size={18} />
                            Naujas tipas
                        </Link>
                        <Link href="/imones/sarasas" className={styles.backLink}>
                            <List size={18} />
                            Įmonių sąrašas
                        </Link>
                    </div>
                </div>

                {rows && rows.length > 0 && (
                    <section className={styles.controls}>
                        <div className={styles.searchRow}>
                            <input
                                type="text"
                                placeholder="Paieška pagal trumpą ar pilną pavadinimą (LT, EN, RU)..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className={styles.searchInput}
                            />
                        </div>
                    </section>
                )}

                {rows === null ? (
                    <p className={styles.message}>Kraunama...</p>
                ) : rows.length === 0 ? (
                    <p className={styles.message}>Įmonių tipų nėra.</p>
                ) : filtered.length === 0 ? (
                    <p className={styles.message}>Pagal paiešką tipų nerasta.</p>
                ) : (
                    <>
                        <div className={styles.expandableList}>
                            <div className={styles.listHeader} aria-hidden>
                                <span className={styles.hChevron} />
                                <span>Trumpas</span>
                                <span>Pilnas (LT)</span>
                                <span>Vertimai</span>
                                <span className={styles.hActions} />
                            </div>
                            {paginated.map((row) => (
                                <CompanyTypeExpandableRow key={row.id} row={row} />
                            ))}
                        </div>
                        <nav className={styles.pagination} aria-label="Tipų puslapiavimas">
                            <p className={styles.paginationInfo}>
                                Rodoma {rangeFrom}–{rangeTo} iš {totalFiltered}
                            </p>
                            {totalPages > 1 && (
                                <div className={styles.paginationButtons}>
                                    <button
                                        type="button"
                                        className={styles.paginationBtn}
                                        disabled={currentPage <= 1}
                                        onClick={() => setPage((p) => Math.max(1, p - 1))}
                                        aria-label="Ankstesnis puslapis"
                                    >
                                        <ChevronLeft size={20} />
                                    </button>
                                    <span className={styles.paginationPages}>
                                        {currentPage} / {totalPages}
                                    </span>
                                    <button
                                        type="button"
                                        className={styles.paginationBtn}
                                        disabled={currentPage >= totalPages}
                                        onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                                        aria-label="Kitas puslapis"
                                    >
                                        <ChevronRight size={20} />
                                    </button>
                                </div>
                            )}
                        </nav>
                    </>
                )}
            </div>
        </div>
    );
}
