"use client";

import { useRouter } from "next/navigation";
import { AuthApi } from "@/lib/api/auth";
import styles from "./page.module.scss";
import InputFieldPassword from "@/components/inputFields/inputFieldPassword";
import InputFieldText from "@/components/inputFields/inputFieldText";
import { useEffect, useState } from "react";
import { Lock, User, LogIn } from "lucide-react";
import Image from "next/image";

export default function Login() {
  const router = useRouter();

  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");

  useEffect(() => {
    document.title = "Prisijungimas";
  }, []);

  async function login() {
    await AuthApi.login(username, password);
    router.push("/");
  }

  return (
    <div className={styles.login}>
      <div className={styles.loginCard}>
        <div className={styles.logoWrap}>
          <Image
            src="/logo-red.png"
            alt="Logo"
            width={80}
            height={80}
            className={styles.logo}
          />
        </div>
        <h1 className={styles.title}>Prisijungimas</h1>
        <p className={styles.subtitle}>Įveskite savo prisijungimo duomenis</p>

        <form
          className={styles.form}
          onSubmit={(e) => {
            e.preventDefault();
            login();
          }}
        >
          <div className={styles.inputFields}>
            <InputFieldText
              value={username}
              placeholder="Vartotojo vardas"
              onChange={setUsername}
              icon={User}
            />
            <InputFieldPassword
              value={password}
              placeholder="Slaptažodis"
              onChange={setPassword}
              icon={Lock}
            />
          </div>
          <button type="submit" className={styles.submitButton}>
            <LogIn size={20} />
            Prisijungti
          </button>
        </form>
      </div>
    </div>
  );
}