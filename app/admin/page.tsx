"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { notFound } from "next/navigation";
import styles from "./page.module.scss";
import { AuditApi } from "@/lib/api/audit";
import type { AuditLog } from "@/lib/types/AuditLog";
import { GeneratedFilesApi } from "@/lib/api/generatedFiles";
import { Download } from "lucide-react";

export default function AdminPage() {
    const [allowed, setAllowed] = useState<boolean | null>(null);

    const [logs, setLogs] = useState<AuditLog[] | null>(null);
    const [loadingLogs, setLoadingLogs] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [limit] = useState(20);
    const [offset, setOffset] = useState(0);

    useEffect(() => {
        const role = localStorage.getItem("role") || "";
        const isAdmin = role === "ROLE_ADMIN";
        setAllowed(isAdmin);
    }, []);

    async function downloadGeneratedZip() {
        const { blob, filename } = await GeneratedFilesApi.getAllZip();
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = filename || "generated.zip";
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

    useEffect(() => {
        if (allowed !== true) return;
        loadLogs(0);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [allowed]);

    return (
        <div className={styles.page}>
            <div className={styles.content}>
                <div className={styles.topBar}>
                    <div className={styles.pageTitle}>Admin Panel</div>
                    <div className={styles.pageSubtitle}>Restricted to ROLE_ADMIN</div>
                </div>

                {allowed === false && notFound()}
                {allowed === null ? (
                    <div className={styles.message}>Loading...</div>
                ) : (
                    <>
                        <div className={styles.grid}>
                            <div className={`${styles.card} ${styles.cardCompanies}`}>
                                <div className={styles.cardTitle}>Companies</div>
                                <div className={styles.cardActions}>
                                    <Link className={styles.linkButtonNeutral} href="/imones">
                                        Add company
                                    </Link>
                                    <Link className={styles.linkButtonNeutral} href="/imones/sarasas">
                                        Company list
                                    </Link>
                                </div>
                            </div>

                            <div className={`${styles.card} ${styles.cardUsers}`}>
                                <div className={styles.cardTitle}>Users</div>
                                <div className={styles.cardActions}>
                                    <Link className={styles.linkButtonNeutral} href="/naudotojai">
                                        Add user
                                    </Link>
                                    <Link className={styles.linkButtonNeutral} href="/naudotojai/sarasas">
                                        User list
                                    </Link>
                                </div>
                            </div>

                            <div className={`${styles.card} ${styles.cardGenerated}`}>
                                <div className={styles.cardTitle}>Generated</div>
                                <div className={styles.cardActions}>
                                    <button
                                        type="button"
                                        className={styles.primaryButton}
                                        onClick={downloadGeneratedZip}
                                    >
                                        <Download size={16} />
                                        Download generated zip
                                    </button>
                                    <Link className={styles.secondaryLinkButton} href="/sablonai">
                                        Open templates
                                    </Link>
                                </div>
                            </div>
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
                                                <th>User ID</th>
                                                <th>Action</th>
                                                <th>Created At</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {[...logs]
                                                .sort((a, b) => b.createdAt.localeCompare(a.createdAt))
                                                .map((log) => (
                                                    <tr key={log.id}>
                                                        <td>{log.id}</td>
                                                        <td>{log.userId ?? "—"}</td>
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

