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
import DeletedFiles from "./deletedFiles/deletedFiles";
import {
    Download,
    RotateCcw,
    Trash2,
    ChevronDown,
    Users,
    FileText,
    FolderArchive,
    ScrollText,
    RefreshCw,
    AlertTriangle,
} from "lucide-react";
import PageBackBar from "@/components/navigation/PageBackBar";

type DeletedTab = "users" | "companies";
type DeletedFilesRoot = "templates" | "generated";

type SymfonyDateLike = {
    date?: string;
    timezone_type?: number;
    timezone?: string;
};

function formatAnyDate(value: unknown): string {
    if (!value) return "—";

    if (typeof value === "string") {
        const normalized = value.includes("T") ? value : value.replace(" ", "T");
        const parsed = new Date(normalized);
        return Number.isNaN(parsed.getTime())
            ? value
            : parsed.toLocaleString("lt-LT", {
                timeZone: "Europe/Vilnius",
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit",
            });
    }

    if (value instanceof Date) {
        return value.toLocaleString("lt-LT", {
            timeZone: "Europe/Vilnius",
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
        });
    }

    if (typeof value === "object" && value !== null && "date" in value) {
        const symfonyDate = value as SymfonyDateLike;
        if (!symfonyDate.date) return "—";

        const normalized = symfonyDate.date.includes("T")
            ? symfonyDate.date
            : symfonyDate.date.replace(" ", "T");

        const parsed = new Date(normalized);

        return Number.isNaN(parsed.getTime())
            ? symfonyDate.date
            : parsed.toLocaleString("lt-LT", {
                timeZone: "Europe/Vilnius",
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit",
            });
    }

    return String(value);
}

export default function AdminPage() {
    const [allowed, setAllowed] = useState<boolean | null>(null);

    const [logs, setLogs] = useState<AuditLog[] | null>(null);
    const [loadingLogs, setLoadingLogs] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [limit] = useState(20);
    const [offset, setOffset] = useState(0);
    const [deletedRoot, setDeletedRoot] = useState<DeletedFilesRoot>("templates");

    const [deletedTab, setDeletedTab] = useState<DeletedTab>("users");
    const [deletedUsers, setDeletedUsers] = useState<User[]>([]);
    const [deletedCompanies, setDeletedCompanies] = useState<Company[]>([]);
    const [loadingDeleted, setLoadingDeleted] = useState(false);
    const [restoringId, setRestoringId] = useState<number | null>(null);
    const [deletedOpen, setDeletedOpen] = useState(true);
    const [deletedFilesOpen, setDeletedFilesOpen] = useState(false);

    useEffect(() => {
        document.title = "Administravimas";
        const role = localStorage.getItem("role") || "";
        setAllowed(role === "ROLE_ADMIN");
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
                const allCompanies = await CompanyApi.getAllDeleted();
                setDeletedCompanies(allCompanies);
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
        } finally {
            setRestoringId(null);
        }
    }

    async function restoreCompany(id: number) {
        setRestoringId(id);
        try {
            await CompanyApi.companyRestore(id);
            setDeletedCompanies((prev) => prev.filter((c) => c.id !== id));
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

    if (allowed === false) {
        notFound();
    }

    return (
        <div className={styles.page}>
            <div className={styles.content}>
                <PageBackBar />

                <div className={styles.topBar}>
                    <div className={styles.pageTitle}>Administratoriaus skydelis</div>
                    <div className={styles.pageSubtitle}>Prieinama tik administratoriams (ROLE_ADMIN)</div>
                </div>

                {allowed === null ? (
                    <div className={styles.message}>Kraunama...</div>
                ) : (
                    <>
                        {/* Quick actions grid */}
                        <div className={styles.grid}>
                            <div className={`${styles.card} ${styles.cardUsers}`}>
                                <div className={styles.cardHeader}>
                                    <div className={`${styles.cardIcon} ${styles.cardIconBlue}`}>
                                        <Users size={20} />
                                    </div>
                                    <div className={styles.cardTitle}>Naudotojai</div>
                                </div>
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
                                <div className={styles.cardHeader}>
                                    <div className={`${styles.cardIcon} ${styles.cardIconRed}`}>
                                        <FileText size={20} />
                                    </div>
                                    <div className={styles.cardTitle}>Sukurti dokumentai</div>
                                </div>
                                <div className={styles.cardActions}>
                                    <button
                                        type="button"
                                        className={styles.primaryButton}
                                        onClick={downloadGeneratedZip}
                                    >
                                        <Download size={16} />
                                        Atsisiųsti archyvą (.ZIP)
                                    </button>
                                    <Link className={styles.secondaryLinkButton} href="/sablonai">
                                        Atidaryti šablonus
                                    </Link>
                                </div>
                            </div>
                        </div>

                        {/* Deleted files section */}
                        <div className={`${styles.card} ${styles.sectionCard}`}>
                            <button
                                type="button"
                                className={styles.sectionHeader}
                                onClick={() => setDeletedFilesOpen(!deletedFilesOpen)}
                            >
                                <div className={styles.sectionHeaderLeft}>
                                    <div className={`${styles.sectionIcon} ${styles.sectionIconOrange}`}>
                                        <FolderArchive size={18} />
                                    </div>
                                    <span className={styles.sectionTitle}>Ištrintų failų katalogas</span>
                                </div>
                                <ChevronDown
                                    size={20}
                                    className={`${styles.chevron} ${deletedFilesOpen ? styles.chevronOpen : ""}`}
                                />
                            </button>

                            {deletedFilesOpen && (
                                <div className={styles.sectionBody}>
                                    <div className={styles.warningBanner}>
                                        <AlertTriangle size={16} />
                                        <span>Ištrinus dokumentus iš šio katalogo, jie bus pašalinti visam laikui.</span>
                                    </div>

                                    <div className={styles.tabs}>
                                        <button
                                            type="button"
                                            className={`${styles.tab} ${deletedRoot === "templates" ? styles.tabActive : ""}`}
                                            onClick={() => setDeletedRoot("templates")}
                                        >
                                            Šablonai
                                        </button>
                                        <button
                                            type="button"
                                            className={`${styles.tab} ${deletedRoot === "generated" ? styles.tabActive : ""}`}
                                            onClick={() => setDeletedRoot("generated")}
                                        >
                                            Sugeneruoti dokumentai
                                        </button>
                                    </div>

                                    <div className={styles.deletedFilesContent}>
                                        {deletedRoot === "templates" && (
                                            <DeletedFiles deletedRoot={deletedRoot} />
                                        )}
                                        {deletedRoot === "generated" && (
                                            <DeletedFiles deletedRoot={deletedRoot} />
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Deleted users & companies section */}
                        <div className={`${styles.card} ${styles.sectionCard}`}>
                            <button
                                type="button"
                                className={styles.sectionHeader}
                                onClick={() => setDeletedOpen(!deletedOpen)}
                            >
                                <div className={styles.sectionHeaderLeft}>
                                    <div className={`${styles.sectionIcon} ${styles.sectionIconRed}`}>
                                        <Trash2 size={18} />
                                    </div>
                                    <span className={styles.sectionTitle}>
                                        Ištrintos ({deletedCount})
                                    </span>
                                </div>
                                <ChevronDown
                                    size={20}
                                    className={`${styles.chevron} ${deletedOpen ? styles.chevronOpen : ""}`}
                                />
                            </button>

                            {deletedOpen && (
                                <div className={styles.sectionBody}>
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
                                            <div className={styles.emptyState}>Nėra ištrintų naudotojų.</div>
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
                                                                <td className={styles.idCell}>{user.id}</td>
                                                                <td>{user.firstName || "—"}</td>
                                                                <td>{user.lastName || "—"}</td>
                                                                <td className={styles.emailCell}>{user.email || "—"}</td>
                                                                <td className={styles.dateCell}>{formatAnyDate(user.deletedDate)}</td>
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
                                        <div className={styles.emptyState}>Nėra ištrintų įmonių.</div>
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
                                                            <td className={styles.idCell}>{company.id}</td>
                                                            <td>{company.companyName || "—"}</td>
                                                            <td>{company.code || "—"}</td>
                                                            <td>{company.companyType || "—"}</td>
                                                            <td className={styles.dateCell}>{formatAnyDate(company.deletedDate)}</td>
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
                                            <RefreshCw size={14} />
                                            Atnaujinti sąrašą
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Audit logs */}
                        <div className={`${styles.card} ${styles.sectionCard}`}>
                            <div className={styles.sectionHeaderStatic}>
                                <div className={styles.sectionHeaderLeft}>
                                    <div className={`${styles.sectionIcon} ${styles.sectionIconGray}`}>
                                        <ScrollText size={18} />
                                    </div>
                                    <span className={styles.sectionTitle}>Audit logs</span>
                                </div>
                            </div>

                            <div className={styles.sectionBody}>
                                {error && <div className={styles.messageError}>{error}</div>}

                                {logs === null ? (
                                    <div className={styles.message}>Kraunami audit logs...</div>
                                ) : logs.length === 0 ? (
                                    <div className={styles.emptyState}>Audit logų nerasta.</div>
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
                                                            <td className={styles.idCell}>{log.id}</td>
                                                            <td>
                                                                <Link className={styles.userLink} href={`/naudotojai/${log.userId}`}>
                                                                    {log.userId ?? "—"}
                                                                </Link>
                                                            </td>
                                                            <td className={styles.actionCell}>{log.action}</td>
                                                            <td className={styles.dateCell}>{formatAnyDate(log.createdAt)}</td>
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
                                        <RefreshCw size={14} />
                                        Atnaujinti
                                    </button>
                                    <button
                                        type="button"
                                        className={styles.secondaryButton}
                                        onClick={() => loadLogs(offset + limit)}
                                        disabled={loadingLogs || logs === null}
                                    >
                                        Rodyti daugiau
                                    </button>
                                </div>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}
