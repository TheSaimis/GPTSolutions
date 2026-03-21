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

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  useEffect(() => {
    document.title = "Prisijungimas";
  }, []);

  async function login() {
    await AuthApi.login(email, password);
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

        <div
          className={styles.form}
        >
          <div className={styles.inputFields}>
            <InputFieldText
              value={email}
              placeholder="Prisijungimo paštas"
              onChange={setEmail}
              icon={User}
            />

            
            <InputFieldPassword
              value={password}
              placeholder="Slaptažodis"
              onChange={setPassword}
              onKeyDown={{
                Enter: login,
              }}
              icon={Lock}
            />
          </div>
          <button type="submit" onClick={login} className={styles.submitButton}>
            <LogIn size={20} />
            Prisijungti
          </button>
        </div>
      </div>
    </div>
  );
}