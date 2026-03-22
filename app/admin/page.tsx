"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { notFound } from "next/navigation";
import styles from "./page.module.scss";
import { AuditApi } from "@/lib/api/audit";
import type { AuditLog } from "@/lib/types/AuditLog";
import { GeneratedFilesApi, generatedZipFallbackName } from "@/lib/api/generatedFiles";
import { Download } from "lucide-react";
import PageBackBar from "@/components/navigation/PageBackBar";

export default function AdminPage() {
    const [allowed, setAllowed] = useState<boolean | null>(null);

    const [logs, setLogs] = useState<AuditLog[] | null>(null);
    const [loadingLogs, setLoadingLogs] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [limit] = useState(20);
    const [offset, setOffset] = useState(0);

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

    useEffect(() => {
        if (allowed !== true) return;
        loadLogs(0);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [allowed]);

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
