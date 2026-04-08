"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { Save, Tags } from "lucide-react";
import PageBackBar from "@/components/navigation/PageBackBar";
import { CompanyTypeApi } from "@/lib/api/companyTypes";
import { MessageStore } from "@/lib/globalVariables/messages";
import InputFieldText from "@/components/inputFields/inputFieldText";
import styles from "./page.module.scss";

function emptyToNull(value: string): string | null {
  const trimmed = value.trim();
  return trimmed === "" ? null : trimmed;
}

export default function NaujasImonesTipasPage() {
  const [role, setRole] = useState("");
  const [typeShort, setTypeShort] = useState("");
  const [typeShortEn, setTypeShortEn] = useState("");
  const [typeShortRu, setTypeShortRu] = useState("");
  const [type, setType] = useState("");
  const [typeEn, setTypeEn] = useState("");
  const [typeRu, setTypeRu] = useState("");
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    document.title = "Naujas įmonės tipas";
    setRole(localStorage.getItem("role") || "");
  }, []);

  async function handleSubmit() {
    if (role !== "ROLE_ADMIN") return;
    if (!typeShort.trim() || !type.trim()) {
      MessageStore.push({
        title: "Klaida",
        message: "Trumpas ir pilnas pavadinimas (LT) yra privalomi.",
        backgroundColor: "#e53e3e",
      });
      return;
    }

    setSaving(true);
    try {
      const created = await CompanyTypeApi.create({
        typeShort: typeShort.trim(),
        type: type.trim(),
        typeShortEn: emptyToNull(typeShortEn),
        typeShortRu: emptyToNull(typeShortRu),
        typeEn: emptyToNull(typeEn),
        typeRu: emptyToNull(typeRu),
      });

      MessageStore.push({
        title: "Sėkmingai",
        message: "Įmonės tipas sukurtas",
        backgroundColor: "#22C55E",
      });

      setTypeShort(created.typeShort ?? "");
      setType(created.type ?? "");
      setTypeShortEn(created.typeShortEn ?? "");
      setTypeShortRu(created.typeShortRu ?? "");
      setTypeEn(created.typeEn ?? "");
      setTypeRu(created.typeRu ?? "");
    } catch (error) {
      MessageStore.push({
        title: "Klaida",
        message: (error as Error)?.message ?? "Nepavyko sukurti tipo",
        backgroundColor: "#e53e3e",
      });
    } finally {
      setSaving(false);
    }
  }

  const isAdmin = role === "ROLE_ADMIN";

  return (
    <div className={styles.page}>
      <div className={styles.topBar}>
        <PageBackBar />
      </div>

      <div className={styles.card}>
        <div className={styles.cardHeader}>
          <div className={styles.fileIcon}>
            <Tags size={24} />
          </div>
          <div>
            <h1 className={styles.title}>Naujas įmonės tipas</h1>
            <p className={styles.subtitle}>
              Sukurkite naują tipą su trumpu ir pilnu pavadinimu bei vertimais.
            </p>
          </div>
        </div>

        <div className={styles.divider} />

        {!isAdmin ? (
          <p className={styles.hint}>
            Kurti įmonės tipą gali tik administratorius. Grįžkite į{" "}
            <Link href="/imones/tipai">įmonių tipų sąrašą</Link>.
          </p>
        ) : null}

        <div className={styles.form}>
          <div className={styles.langBlock}>
            <h2 className={styles.langTitle}>Lietuvių kalba</h2>
            <InputFieldText
              value={typeShort}
              onChange={setTypeShort}
              placeholder="Trumpas pavadinimas (pvz. UAB)"
              disabled={!isAdmin}
            />
            <InputFieldText
              value={type}
              onChange={setType}
              placeholder="Pilnas pavadinimas (pvz. Uždaroji akcinė bendrovė)"
              disabled={!isAdmin}
            />
          </div>

          <div className={styles.langBlock}>
            <h2 className={styles.langTitle}>Anglų kalba</h2>
            <div className={styles.row}>
              <InputFieldText
                value={typeShortEn}
                onChange={setTypeShortEn}
                placeholder="Trumpas (EN)"
                disabled={!isAdmin}
              />
              <InputFieldText
                value={typeEn}
                onChange={setTypeEn}
                placeholder="Pilnas pavadinimas (EN)"
                disabled={!isAdmin}
              />
            </div>
          </div>

          <div className={styles.langBlock}>
            <h2 className={styles.langTitle}>Rusų kalba</h2>
            <div className={styles.row}>
              <InputFieldText
                value={typeShortRu}
                onChange={setTypeShortRu}
                placeholder="Trumpas (RU)"
                disabled={!isAdmin}
              />
              <InputFieldText
                value={typeRu}
                onChange={setTypeRu}
                placeholder="Pilnas pavadinimas (RU)"
                disabled={!isAdmin}
              />
            </div>
          </div>
        </div>

        {isAdmin ? (
          <button
            type="button"
            className={styles.submitButton}
            onClick={handleSubmit}
            disabled={saving}
          >
            <Save size={18} />
            {saving ? "Saugoma..." : "Sukurti įmonės tipą"}
          </button>
        ) : null}
      </div>
    </div>
  );
}
