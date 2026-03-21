"use client";

import { useEffect, useState } from "react";
import UserCard from "@/components/userCard/userCard";
import { logout } from "@/lib/functions/logout";
import PageBackBar from "@/components/navigation/PageBackBar";
import styles from "./page.module.scss";

export default function Profilis() {

    const [id, setId] = useState<number | null>(null);
    const [name, setName] = useState<string | null>(null);
    const [lastName, setLastName] = useState<string | null>(null);
    const [email, setEmail] = useState<string | null>(null);
    const [role, setRole] = useState<string | null>(null);

    useEffect(() => {
        const storedId = localStorage.getItem("id");
        if (storedId) setId(parseInt(storedId, 10));
        setName(localStorage.getItem("name"));
        setLastName(localStorage.getItem("lastName"));
        setEmail(localStorage.getItem("email"));
        setRole(localStorage.getItem("role"));
    }, []);

    return (
        <div className={styles.shell}>
            <div className={styles.page}>
                <PageBackBar />
                {name && lastName && email && role && (
                    <UserCard id={id ?? undefined} email={email} firstName={name} lastName={lastName} role={role} />
                )}
                <button type="button" className={styles.logout} onClick={logout}>
                    Atsijungti
                </button>
            </div>
        </div>
    );
}