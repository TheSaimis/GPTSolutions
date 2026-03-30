"use client";

import { useEffect, useState } from "react";
import { Building2, Save } from "lucide-react";
import PageBackBar from "@/components/navigation/PageBackBar";
import { CompanyApi } from "@/lib/api/companies";
import { MessageStore } from "@/lib/globalVariables/messages";
import { COMPANY_TYPES, type CompanyCategory } from "@/lib/types/Company";
import styles from "./page.module.scss";
import InputFieldText from "@/components/inputFields/inputFieldText";
import InputFieldNumber from "@/components/inputFields/inputFieldNumber";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";

export default function ImonesPage() {
    const [companyType, setCompanyType] = useState("");
    const [companyName, setCompanyName] = useState("");
    const [address, setAddress] = useState("");
    const [cityOrDistrict, setCityOrDistrict] = useState("");
    const [code, setCode] = useState("");
    const [managerFirstName, setManagerFirstName] = useState("");
    const [managerLastName, setManagerLastName] = useState("");
    const [managerGender, setManagerGender] = useState("");
    const [role, setRole] = useState("");
    const [categories, setCategories] = useState<CompanyCategory[]>([]);
    const [categorySearch, setCategorySearch] = useState("");
    const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
    const [addingCategory, setAddingCategory] = useState(false);

    useEffect(() => {
        document.title = "Pridėti įmonę";
        CompanyApi.getAll();
        CompanyApi.getCategories()
            .then((items) => setCategories(items))
            .catch(() => setCategories([]));
    }, []);

    const filteredCategories = categories.filter((item) =>
        item.name.toLowerCase().includes(categorySearch.toLowerCase())
    );

    async function handleAddCategory() {
        const name = categorySearch;
        if (name.trim() === "") return;

        setAddingCategory(true);
        try {
            const result = await CompanyApi.createCategory(name);
            setCategories((prev) => {
                const exists = prev.some((item) => item.id === result.data.id);
                if (exists) return prev;
                return [...prev, result.data].sort((a, b) => a.name.localeCompare(b.name, "lt"));
            });
            setSelectedCategoryId(result.data.id);
        } catch {
            // API message is shown globally
        } finally {
            setAddingCategory(false);
        }
    }

    async function handleSubmit() {
        try {
            await CompanyApi.companyCreate({
                companyType,
                companyName,
                address,
                cityOrDistrict,
                code,
                managerFirstName,
                managerLastName,
                managerGender,
                role,
                categoryId: selectedCategoryId,
            });
            MessageStore.push({ title: "Sėkmingai", message: "įmonė sukurta", backgroundColor: "#22C55E" });
        } catch (e) {
            MessageStore.push({ title: "Klaida", message: (e as Error)?.message ?? "Nepavyko sukurti įmonės", backgroundColor: "#e53e3e" });
        }
    }

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <PageBackBar />
            </div>

            <div className={styles.layout}>
                <div className={styles.card}>
                    <div className={styles.cardHeader}>
                        <div className={styles.fileIcon}>
                            <Building2 size={24} />
                        </div>
                        <div>
                            <h1 className={styles.title}>Pridėti įmonę</h1>
                            <p className={styles.subtitle}>Užpildykite įmonės duomenis</p>
                        </div>
                    </div>

                    <div className={styles.divider} />

                    <div className={styles.form}>
                        <div className={styles.row}>
                            <InputFieldSelect options={[...COMPANY_TYPES]} onChange={setCompanyType} placeholder="Įmonės tipas" />
                            <InputFieldText value={companyName} onChange={setCompanyName} placeholder="Įmones pavadinimas" />
                        </div>

                        <InputFieldSelect options={["Vyras", "Moteris"]} onChange={setManagerGender} placeholder="Vadovo lytis" />

                        <InputFieldText value={address} onChange={setAddress} placeholder="Adresas" />
                        <InputFieldText value={cityOrDistrict} onChange={setCityOrDistrict} placeholder="Miestas/Rajonas" />
                        <InputFieldNumber regex={/^\d{0,9}$/} value={code} onChange={setCode} placeholder="Įmonės kodas" />

                        <div className={styles.row}>
                            <InputFieldText regex={/^[A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]+$/} value={managerFirstName} onChange={setManagerFirstName} placeholder="Vardas" />
                            <InputFieldText regex={/^[A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]+$/} value={managerLastName} onChange={setManagerLastName} placeholder="Pavardė" />
                        </div>

                        <InputFieldText value={role} onChange={setRole} placeholder="Pareigos" />
                    </div>

                    <button className={styles.submitButton} onClick={handleSubmit}>
                        <Save size={18} />
                        Išsaugoti
                    </button>
                </div>

                <aside className={styles.categoryPanel}>
                    <h2 className={styles.categoryTitle}>Kategorijos</h2>
                    <input
                        className={styles.categorySearch}
                        value={categorySearch}
                        onChange={(event) => setCategorySearch(event.target.value)}
                        placeholder="Paieška / nauja kategorija"
                    />
                    <div className={styles.categoryList}>
                        {filteredCategories.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                className={`${styles.categoryRow} ${selectedCategoryId === item.id ? styles.categoryRowActive : ""}`}
                                onClick={() => setSelectedCategoryId(item.id)}
                            >
                                {item.name}
                            </button>
                        ))}
                    </div>
                    <button
                        type="button"
                        className={styles.addCategoryButton}
                        disabled={addingCategory || categorySearch.trim() === ""}
                        onClick={handleAddCategory}
                    >
                        {addingCategory ? "Pridedama..." : "Pridėti"}
                    </button>
                </aside>
            </div>
        </div>
    );
}