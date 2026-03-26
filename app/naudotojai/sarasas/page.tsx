"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import PageBackBar from "@/components/navigation/PageBackBar";
import UserCard from "@/components/userCard/userCard";
import { UsersApi } from "@/lib/api/users";
import type { User } from "@/lib/types/User";
import styles from "./page.module.scss";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import { UserPlus, SlidersHorizontal, X } from "lucide-react";
import {
    ROLE_OPTIONS,
    USER_SORT_OPTIONS,
    USER_VIEW_OPTIONS,
    normalizeRole,
    roleLabel,
    sortUsers,
} from "@/lib/filters";

export default function NaudotojuSarasasPage() {
    const [users, setUsers] = useState<User[] | null>(null);
    const [search, setSearch] = useState("");
    const [selectedRole, setSelectedRole] = useState("all");
    const [sortBy, setSortBy] = useState("name-asc");
    const [viewMode, setViewMode] = useState<"large" | "compact" | "mini">("large");
    const [filtersOpen, setFiltersOpen] = useState(false);

    useEffect(() => {
        document.title = "Naudotojų sąrašas";
        UsersApi.getAll()
            .then((data) => setUsers(Array.isArray(data) ? data : []))
            .catch(() => setUsers([]));
    }, []);

    const filteredUsers = useMemo(() => {
        if (!users) return [];
        const searchLower = search.trim().toLowerCase();
        const startsWithSearch = (value?: string) => (value ?? "").toLowerCase().startsWith(searchLower);

        const list = users.filter((user) => {
            const normalizedRole = normalizeRole(user.role);
            const matchesRole = selectedRole === "all" || normalizedRole === selectedRole;
            if (!matchesRole) return false;

            if (!searchLower) return true;
            const searchableValues = [user.firstName, user.lastName, user.email, roleLabel(user.role)];
            return searchableValues.some((value) => startsWithSearch(value));
        });

        return sortUsers(list, sortBy);
    }, [users, search, selectedRole, sortBy]);

    const activeFilterCount = [
        selectedRole !== "all",
        sortBy !== "name-asc",
        viewMode !== "large",
    ].filter(Boolean).length;

    const filterFields = (
        <>
            <InputFieldSelect
                options={ROLE_OPTIONS}
                placeholder="Pareigos"
                selected={"Pareigos"}
                onChange={setSelectedRole}
            />
            <InputFieldSelect
                options={USER_SORT_OPTIONS}
                placeholder="Rikiavimas"
                selected={"Rikiavimas"}
                onChange={setSortBy}
            />
            <InputFieldSelect
                options={USER_VIEW_OPTIONS}
                placeholder="Vartotojų išdėstymas"
                selected={"Vartotojų išdėstymas"}
                onChange={setViewMode as (v: string) => void}
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
                    <h1 className={styles.pageTitle}>Naudotojų sąrašas</h1>
                    <Link href="/naudotojai" className={styles.createButton}>
                        <UserPlus size={18} />
                        Pridėti naudotoją
                    </Link>
                </div>

                {users && users.length > 0 && (
                    <section className={styles.controls}>
                        <div className={styles.searchRow}>
                            <input
                                type="text"
                                placeholder="Paieška pagal vardą, pavardę, el. paštą, rolę..."
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

                {users === null ? (
                    <p className={styles.message}>Kraunama...</p>
                ) : users.length === 0 ? (
                    <p className={styles.message}>Naudotojų nėra.</p>
                ) : filteredUsers.length === 0 ? (
                    <p className={styles.message}>Pagal pasirinktus filtrus naudotojų nerasta.</p>
                ) : (
                    <div
                        className={`${styles.cardList} ${viewMode === "compact" ? styles.compactList : ""} ${viewMode === "mini" ? styles.miniList : ""}`}
                    >
                        {filteredUsers.map((user) =>
                            user.id != null ? (
                                <UserCard
                                    key={user.id}
                                    id={user.id}
                                    email={user.email}
                                    firstName={user.firstName}
                                    lastName={user.lastName}
                                    role={user.role}
                                    variant={viewMode}
                                    deleted={user.deleted}
                                />
                            ) : null
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
