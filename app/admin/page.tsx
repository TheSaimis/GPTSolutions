"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { notFound } from "next/navigation";
import styles from "./page.module.scss";
import { AuditApi } from "@/lib/api/audit";
import type { AuditLog } from "@/lib/types/AuditLog";
import { GeneratedFilesApi, generatedZipFallbackName } from "@/lib/api/generatedFiles";
import { UsersApi } from "@/lib/api/users";
import { CompanyApi } from "@/lib/api/companies";
import type { User } from "@/lib/types/User";
import type { Company } from "@/lib/types/Company";
import { Download, RotateCcw, Trash2, ChevronDown } from "lucide-react";
import PageBackBar from "@/components/navigation/PageBackBar";

type DeletedTab = "users" | "companies";

export default function AdminPage() {
    const [allowed, setAllowed] = useState<boolean | null>(null);

    const [logs, setLogs] = useState<AuditLog[] | null>(null);
    const [loadingLogs, setLoadingLogs] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [limit] = useState(20);
    const [offset, setOffset] = useState(0);

    const [deletedTab, setDeletedTab] = useState<DeletedTab>("users");
    const [deletedUsers, setDeletedUsers] = useState<User[]>([]);
    const [deletedCompanies, setDeletedCompanies] = useState<Company[]>([]);
    const [loadingDeleted, setLoadingDeleted] = useState(false);
    const [restoringId, setRestoringId] = useState<number | null>(null);
    const [deletedOpen, setDeletedOpen] = useState(true);

    useEffect(() => {
        document.title = "Administravimas";
        const role = localStorage.getItem("role") || "";
        const isAdmin = role === "ROLE_ADMIN";
        setAllowed(isAdmin);
    }, []);

    async function downloadGeneratedZip() {
        const { blob, filename } = await GeneratedFilesApi.getAllZip();
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = filename || generatedZipFallbackName();
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }

    async function loadLogs(nextOffset: number) {
        setLoadingLogs(true);
        setError(null);
        try {
            const data = await AuditApi.list(limit, nextOffset);
            setLogs(nextOffset === 0 ? data : [...(logs ?? []), ...data]);
            setOffset(nextOffset);
        } catch (e) {
            setError((e as Error)?.message ?? "Failed to load audit logs");
        } finally {
            setLoadingLogs(false);
        }
    }

    async function loadDeletedItems() {
        setLoadingDeleted(true);
        try {
            const allUsers = await UsersApi.getAll();
            setDeletedUsers(allUsers.filter((u) => u.deleted));

            try {
                const allCompanies = await CompanyApi.getAll();
                setDeletedCompanies(allCompanies.filter((c) => c.deleted));
            } catch {
                setDeletedCompanies([]);
            }
        } catch {
            setDeletedUsers([]);
            setDeletedCompanies([]);
        } finally {
            setLoadingDeleted(false);
        }
    }

    async function restoreUser(id: number) {
        setRestoringId(id);
        try {
            await UsersApi.userRestore(id);
            setDeletedUsers((prev) => prev.filter((u) => u.id !== id));
        } catch {
            /* handled by api */
        } finally {
            setRestoringId(null);
        }
    }

    async function restoreCompany(id: number) {
        setRestoringId(id);
        try {
            await CompanyApi.companyRestore(id);
            setDeletedCompanies((prev) => prev.filter((c) => c.id !== id));
        } catch {
            /* handled by api */
        } finally {
            setRestoringId(null);
        }
    }

    useEffect(() => {
        if (allowed !== true) return;
        loadLogs(0);
        loadDeletedItems();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [allowed]);

    const deletedCount = deletedUsers.length + deletedCompanies.length;

    return (
        <div className={styles.page}>
            <div className={styles.content}>
                <PageBackBar />
                <div className={styles.topBar}>
                    <div className={styles.pageTitle}>Administratoriaus skydelis</div>
                    <div className={styles.pageSubtitle}>Prieinama tik administratoriams (ROLE_ADMIN)</div>
                </div>

                {allowed === false && notFound()}
                {allowed === null ? (
                    <div className={styles.message}>Kraunama...</div>
                ) : (
                    <>
                        <div className={styles.grid}>
                            <div className={`${styles.card} ${styles.cardUsers}`}>
                                <div className={styles.cardTitle}>Naudotojai</div>
                                <div className={styles.cardActions}>
                                    <Link className={styles.linkButtonNeutral} href="/naudotojai">
                                        Pridėti naudotoją
                                    </Link>
                                    <Link className={styles.linkButtonNeutral} href="/naudotojai/sarasas">
                                        Naudotojų sąrašas
                                    </Link>
                                </div>
                            </div>

                            <div className={`${styles.card} ${styles.cardGenerated}`}>
                                <div className={styles.cardTitle}>Sukurti dokumentai</div>
                                <div className={styles.cardActions}>
                                    <button
                                        type="button"
                                        className={styles.primaryButton}
                                        onClick={downloadGeneratedZip}
                                    >
                                        <Download size={16} />
                                        Atsisiųsti sukurtų dokumentų archyvą (.ZIP)
                                    </button>
                                    <Link className={styles.secondaryLinkButton} href="/sablonai">
                                        Atidaryti šablonus
                                    </Link>
                                </div>
                            </div>
                        </div>

                        {/* Deleted items section */}
                        <div className={`${styles.card} ${styles.deletedSection}`}>
                            <button
                                type="button"
                                className={styles.deletedHeader}
                                onClick={() => setDeletedOpen(!deletedOpen)}
                            >
                                <div className={styles.deletedHeaderLeft}>
                                    <Trash2 size={18} />
                                    <span className={styles.cardTitle} style={{ marginBottom: 0 }}>
                                        Ištrintos ({deletedCount})
                                    </span>
                                </div>
                                <ChevronDown
                                    size={20}
                                    className={`${styles.chevron} ${deletedOpen ? styles.chevronOpen : ""}`}
                                />
                            </button>

                            {deletedOpen && (
                                <div className={styles.deletedBody}>
                                    <div className={styles.tabs}>
                                        <button
                                            type="button"
                                            className={`${styles.tab} ${deletedTab === "users" ? styles.tabActive : ""}`}
                                            onClick={() => setDeletedTab("users")}
                                        >
                                            Naudotojai ({deletedUsers.length})
                                        </button>
                                        <button
                                            type="button"
                                            className={`${styles.tab} ${deletedTab === "companies" ? styles.tabActive : ""}`}
                                            onClick={() => setDeletedTab("companies")}
                                        >
                                            Įmonės ({deletedCompanies.length})
                                        </button>
                                    </div>

                                    {loadingDeleted ? (
                                        <div className={styles.message}>Kraunama...</div>
                                    ) : deletedTab === "users" ? (
                                        deletedUsers.length === 0 ? (
                                            <div className={styles.message}>Nėra ištrintų naudotojų.</div>
                                        ) : (
                                            <div className={styles.tableWrap}>
                                                <table className={styles.deletedTable}>
                                                    <thead>
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>Vardas</th>
                                                            <th>Pavardė</th>
                                                            <th>El. paštas</th>
                                                            <th>Ištrinimo data</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {deletedUsers.map((user) => (
                                                            <tr key={user.id}>
                                                                <td>{user.id}</td>
                                                                <td>{user.firstName || "—"}</td>
                                                                <td>{user.lastName || "—"}</td>
                                                                <td>{user.email || "—"}</td>
                                                                <td>{user.deletedDate || "—"}</td>
                                                                <td>
                                                                    <button
                                                                        type="button"
                                                                        className={styles.restoreButton}
                                                                        disabled={restoringId === user.id}
                                                                        onClick={() => restoreUser(user.id!)}
                                                                    >
                                                                        <RotateCcw size={14} />
                                                                        {restoringId === user.id ? "Grąžinama..." : "Grąžinti"}
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )
                                    ) : deletedCompanies.length === 0 ? (
                                        <div className={styles.message}>Nėra ištrintų įmonių.</div>
                                    ) : (
                                        <div className={styles.tableWrap}>
                                            <table className={styles.deletedTable}>
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Pavadinimas</th>
                                                        <th>Kodas</th>
                                                        <th>Tipas</th>
                                                        <th>Ištrinimo data</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {deletedCompanies.map((company) => (
                                                        <tr key={company.id}>
                                                            <td>{company.id}</td>
                                                            <td>{company.companyName || "—"}</td>
                                                            <td>{company.code || "—"}</td>
                                                            <td>{company.companyType || "—"}</td>
                                                            <td>{company.deletedDate || "—"}</td>
                                                            <td>
                                                                <button
                                                                    type="button"
                                                                    className={styles.restoreButton}
                                                                    disabled={restoringId === company.id}
                                                                    onClick={() => restoreCompany(company.id!)}
                                                                >
                                                                    <RotateCcw size={14} />
                                                                    {restoringId === company.id ? "Grąžinama..." : "Grąžinti"}
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}

                                    <div className={styles.actionsRow}>
                                        <button
                                            type="button"
                                            className={styles.secondaryButton}
                                            onClick={loadDeletedItems}
                                            disabled={loadingDeleted}
                                        >
                                            Atnaujinti sąrašą
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className={styles.card}>
                            <div className={styles.cardTitle}>Audit logs</div>

                            {error && <div className={styles.messageError}>{error}</div>}

                            {logs === null ? (
                                <div className={styles.message}>Loading audit logs...</div>
                            ) : logs.length === 0 ? (
                                <div className={styles.message}>No audit logs found.</div>
                            ) : (
                                <div className={styles.tableWrap}>
                                    <table className={styles.table}>
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Naudotojas</th>
                                                <th>Veiksmas</th>
                                                <th>Data</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {[...logs]
                                                .sort((a, b) => b.createdAt.localeCompare(a.createdAt))
                                                .map((log) => (
                                                    <tr key={log.id}>
                                                        <td>{log.id}</td>
                                                        <td>
                                                            <Link href={`/naudotojai/${log.userId}`}>
                                                                {log.userId ?? "—"}
                                                            </Link>
                                                        </td>
                                                        <td className={styles.actionCell}>
                                                            {log.action}
                                                        </td>
                                                        <td>{log.createdAt}</td>
                                                    </tr>
                                                ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            <div className={styles.actionsRow}>
                                <button
                                    type="button"
                                    className={styles.primaryButton}
                                    onClick={() => loadLogs(0)}
                                    disabled={loadingLogs}
                                >
                                    Refresh
                                </button>
                                <button
                                    type="button"
                                    className={styles.secondaryButton}
                                    onClick={() => loadLogs(offset + limit)}
                                    disabled={loadingLogs || logs === null}
                                >
                                    Load more
                                </button>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}
