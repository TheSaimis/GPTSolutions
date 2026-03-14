"use client"

import { useEffect, useState } from "react";
import UserCard from "@/components/userCard/userCard";
import { logout } from "@/lib/functions/logout";
import styles from "./page.module.scss";

export default function Profilis() {

    const [name, setName] = useState<string | null>(null);
    const [lastName, setLastName] = useState<string | null>(null);
    const [email, setEmail] = useState<string | null>(null);
    const [role, setRole] = useState<string | null>(null);

    useEffect(() => {
        setName(localStorage.getItem("name"));
        setLastName(localStorage.getItem("lastName"));
        setEmail(localStorage.getItem("email"));
        setRole(localStorage.getItem("role"));
    }, []);


    return (
        <div>
            { name && lastName && email && role &&
                <UserCard id={1} email={email} firstName={name} lastName={lastName} role={role} />
            }
            <button className="buttons" onClick={logout}>Atsijungti</button>
        </div>
    );
}