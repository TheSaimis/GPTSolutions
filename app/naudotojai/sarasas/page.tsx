"use client";

import { useEffect, useState } from "react";
import { ArrowLeft } from "lucide-react";
import Link from "next/link";
import UserCard from "@/components/userCard/userCard";
import { UsersApi } from "@/lib/api/users";
import type { User } from "@/lib/types/User";
import styles from "./page.module.scss";

export default function NaudotojuSarasasPage() {
    const [users, setUsers] = useState<User[] | null>(null);

    useEffect(() => {
        document.title = "Naudotojų sąrašas";
        UsersApi.getAll()
            .then((data) => setUsers(Array.isArray(data) ? data : []))
            .catch(() => setUsers([]));
    }, []);

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <Link href="/" className={styles.backLink}>
                    <ArrowLeft size={16} />
                    Grįžti į pradžią
                </Link>
            </div>

            <div className={styles.content}>
                <h1 className={styles.pageTitle}>Naudotojų sąrašas</h1>
                {users === null ? (
                    <p className={styles.message}>Kraunama...</p>
                ) : users.length === 0 ? (
                    <p className={styles.message}>Naudotojų nėra.</p>
                ) : (
                    <div className={styles.cardList}>
                        {users.map((user) =>
                            user.id != null ? (
                                <UserCard
                                    key={user.id}
                                    id={user.id}
                                    email={user.email}
                                    firstName={user.firstName}
                                    lastName={user.lastName}
                                    role={user.role}
                                />
                            ) : null
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
