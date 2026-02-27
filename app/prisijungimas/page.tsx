"use client";

import { useRouter } from "next/navigation";
import { AuthApi } from "@/lib/api/auth";
import styles from "./page.module.scss";
import InputFieldPassword from "@/components/inputFields/inputFieldPassword";
import InputFieldText from "@/components/inputFields/inputFieldText";
import { useEffect, useState } from "react";
import { Lock, User } from "lucide-react";

export default function Login() {
  const router = useRouter();

  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");

  useEffect(() => {
    document.title = "Prisijungimas";
  }, []);

  async function login() {
    await AuthApi.login(username, password);
    console.log("Prisijungimas");
    router.push("/");
  }

  return (
    <div className={styles.login}>
      <div className={styles.loginForm}>
        <h1>Įveskite savo prisijungimo duomenis</h1>
        <div className={styles.inputFields}>
          <InputFieldText value={username} placeholder="Vartotojo vardas" onChange={setUsername} icon={User} />
          <InputFieldPassword value={password} placeholder="Slaptažodis" onChange={setPassword} icon={Lock} />
        </div>

        <button className={`${styles.button} buttons`} onClick={login}>Prisijungti</button>
      </div>
    </div>
  );
}