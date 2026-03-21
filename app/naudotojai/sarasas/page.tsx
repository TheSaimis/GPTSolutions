"use client";

import { useEffect, useMemo, useState } from "react";
import PageBackBar from "@/components/navigation/PageBackBar";
import UserCard from "@/components/userCard/userCard";
import { UsersApi } from "@/lib/api/users";
import type { User } from "@/lib/types/User";
import styles from "./page.module.scss";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";

export default function NaudotojuSarasasPage() {
    const [users, setUsers] = useState<User[] | null>(null);
    const [search, setSearch] = useState("");
    const [selectedRole, setSelectedRole] = useState("all");
    const [sortBy, setSortBy] = useState("name-asc");
    const [viewMode, setViewMode] = useState<"large" | "compact" | "mini">("large");

    useEffect(() => {
        document.title = "Naudotojų sąrašas";
        UsersApi.getAll()
            .then((data) => setUsers(Array.isArray(data) ? data : []))
            .catch(() => setUsers([]));
    }, []);

    const normalizeRole = (role?: string | string[] | null) => {
        const rawRole = Array.isArray(role) ? role[0] : role;
        const value = typeof rawRole === "string" ? rawRole.toUpperCase() : "";
        if (value.includes("ROLE_ADMIN") || value.includes("ADMIN")) return "ROLE_ADMIN";
        if (value.includes("ROLE_USER") || value.includes("USER")) return "ROLE_USER";
        return "";
    };

    const roleLabel = (role?: string) => {
        const normalized = normalizeRole(role);
        if (normalized === "ROLE_ADMIN") return "Administratorius";
        if (normalized === "ROLE_USER") return "Naudotojas";
        return "Nenurodyta";
    };

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

        const sortedList = [...list];
        sortedList.sort((a, b) => {
            const aFullName = `${a.firstName ?? ""} ${a.lastName ?? ""}`.trim();
            const bFullName = `${b.firstName ?? ""} ${b.lastName ?? ""}`.trim();

            if (sortBy === "name-asc") return aFullName.localeCompare(bFullName, "lt");
            if (sortBy === "name-desc") return bFullName.localeCompare(aFullName, "lt");
            if (sortBy === "email-asc") return (a.email ?? "").localeCompare(b.email ?? "", "lt");
            if (sortBy === "email-desc") return (b.email ?? "").localeCompare(a.email ?? "", "lt");
            return 0;
        });

        return sortedList;
    }, [users, search, selectedRole, sortBy]);

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <PageBackBar />
            </div>

            <div className={styles.content}>
                <h1 className={styles.pageTitle}>Naudotojų sąrašas</h1>
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
                        </div>

                        <div className={styles.filtersRow}>
                            <InputFieldSelect
                                options={[
                                    { value: "all", label: "Visi" },
                                    { value: "ROLE_ADMIN", label: "Administratoriai" },
                                    { value: "ROLE_USER", label: "Naudotojai" },
                                ]}
                                placeholder="Pareigos"
                                selected={"Pareigos"}
                                onChange={setSelectedRole}
                            />

                            <InputFieldSelect
                                options={[
                                    { value: "name-asc", label: "Vardas (A-Z)" },
                                    { value: "name-desc", label: "Vardas (Z-A)" },
                                    { value: "email-asc", label: "El. paštas (A-Z)" },
                                    { value: "email-desc", label: "El. paštas (Z-A)" },
                                ]}
                                placeholder="Rikiavimas"
                                selected={"Rikiavimas"}
                                onChange={setSortBy}
                            />

                            <InputFieldSelect
                                options={[
                                    { value: "compact", label: "Eilutėmis" },
                                    { value: "large", label: "Kortelėmis" },
                                    { value: "mini", label: "Kompaktiškas" },
                                ]}
                                placeholder="Vartotojų išdėstymas"
                                selected={"Vartotojų išdėstymas"}
                                onChange={setViewMode as any}
                            />
                        </div>
                    </section>
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
                                />
                            ) : null
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
