"use client";

import { useEffect, useState } from "react";
import { Building2, Save } from "lucide-react";
import PageBackBar from "@/components/navigation/PageBackBar";
import { CompanyApi } from "@/lib/api/companies";
import { CompanyTypeApi } from "@/lib/api/companyTypes";
import { MessageStore } from "@/lib/globalVariables/messages";
import type { CompanyCategory, CompanyTypeRow } from "@/lib/types/Company";
import {
    applyCompanyTypeSelection,
    buildCompanyTypeDropdownOptions,
} from "@/lib/companyTypeSelect";
import styles from "./page.module.scss";
import InputFieldText from "@/components/inputFields/inputFieldText";
import InputFieldNumber from "@/components/inputFields/inputFieldNumber";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import CompanyFormLocaleToggle, { type CompanyFormLocale } from "@/components/companyForm/CompanyFormLocaleToggle";

export default function ImonesPage() {
    const [companyTypeRows, setCompanyTypeRows] = useState<CompanyTypeRow[]>([]);
    const [companyTypeId, setCompanyTypeId] = useState<number | null>(null);
    const [companyTypeShort, setCompanyTypeShort] = useState("");
    const [companyName, setCompanyName] = useState("");
    const [companyNameEn, setCompanyNameEn] = useState("");
    const [companyNameRu, setCompanyNameRu] = useState("");
    const [address, setAddress] = useState("");
    const [addressEn, setAddressEn] = useState("");
    const [addressRu, setAddressRu] = useState("");
    const [cityOrDistrict, setCityOrDistrict] = useState("");
    const [cityOrDistrictEn, setCityOrDistrictEn] = useState("");
    const [cityOrDistrictRu, setCityOrDistrictRu] = useState("");
    const [code, setCode] = useState("");
    const [managerFirstName, setManagerFirstName] = useState("");
    const [managerLastName, setManagerLastName] = useState("");
    const [managerGender, setManagerGender] = useState("");
    const [role, setRole] = useState("");
    const [categories, setCategories] = useState<CompanyCategory[]>([]);
    const [categorySearch, setCategorySearch] = useState("");
    const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
    const [addingCategory, setAddingCategory] = useState(false);
    const [formLocale, setFormLocale] = useState<CompanyFormLocale>("lt");
    const [managerFirstNameEn, setManagerFirstNameEn] = useState("");
    const [managerFirstNameRu, setManagerFirstNameRu] = useState("");
    const [managerLastNameEn, setManagerLastNameEn] = useState("");
    const [managerLastNameRu, setManagerLastNameRu] = useState("");
    const [roleEn, setRoleEn] = useState("");
    const [roleRu, setRoleRu] = useState("");

    useEffect(() => {
        document.title = "Pridėti įmonę";
        CompanyApi.getAll();
        CompanyApi.getCategories()
            .then((items) => setCategories(items))
            .catch(() => setCategories([]));
        CompanyTypeApi.getAll()
            .then((rows) => setCompanyTypeRows(Array.isArray(rows) ? rows : []))
            .catch(() => setCompanyTypeRows([]));
    }, []);

    const { fromDatabase: companyTypesFromDb, options: companyTypeOptions } =
        buildCompanyTypeDropdownOptions(companyTypeRows);
    const selectedCompanyTypeLabel = companyTypesFromDb
        ? companyTypeRows.find((t) => Number(t.id) === Number(companyTypeId))?.typeShort ??
          ""
        : companyTypeShort;

    const filteredCategories = categories.filter((item) =>
        item.name.toLowerCase().includes(categorySearch.toLowerCase())
    );

    function handleFormLocaleChange(next: CompanyFormLocale) {
        if (next === "en") {
            setManagerFirstNameEn((p) => (p.trim() ? p : managerFirstName));
            setRoleEn((p) => (p.trim() ? p : role));
        } else if (next === "ru") {
            setManagerFirstNameRu((p) => (p.trim() ? p : managerFirstName));
            setRoleRu((p) => (p.trim() ? p : role));
        }
        setFormLocale(next);
    }

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
            const payload = {
                companyName,
                address,
                cityOrDistrict,
                code,
                managerFirstName,
                managerLastName,
                managerGender,
                role,
                categoryId: selectedCategoryId,
                ...(companyTypesFromDb && companyTypeId != null && companyTypeId > 0
                    ? { companyTypeId }
                    : !companyTypesFromDb && companyTypeShort.trim()
                      ? { companyType: companyTypeShort.trim() }
                      : {}),
            } as const;

            const optionalLocaleFields: Partial<Record<
                | "companyNameEn"
                | "companyNameRu"
                | "addressEn"
                | "addressRu"
                | "cityOrDistrictEn"
                | "cityOrDistrictRu"
                | "managerFirstNameEn"
                | "managerFirstNameRu"
                | "managerLastNameEn"
                | "managerLastNameRu"
                | "roleEn"
                | "roleRu",
                string
            >> = {};

            if (companyNameEn.trim()) optionalLocaleFields.companyNameEn = companyNameEn.trim();
            if (companyNameRu.trim()) optionalLocaleFields.companyNameRu = companyNameRu.trim();
            if (addressEn.trim()) optionalLocaleFields.addressEn = addressEn.trim();
            if (addressRu.trim()) optionalLocaleFields.addressRu = addressRu.trim();
            if (cityOrDistrictEn.trim()) optionalLocaleFields.cityOrDistrictEn = cityOrDistrictEn.trim();
            if (cityOrDistrictRu.trim()) optionalLocaleFields.cityOrDistrictRu = cityOrDistrictRu.trim();
            if (managerFirstNameEn.trim()) optionalLocaleFields.managerFirstNameEn = managerFirstNameEn.trim();
            if (managerFirstNameRu.trim()) optionalLocaleFields.managerFirstNameRu = managerFirstNameRu.trim();
            if (managerLastNameEn.trim()) optionalLocaleFields.managerLastNameEn = managerLastNameEn.trim();
            if (managerLastNameRu.trim()) optionalLocaleFields.managerLastNameRu = managerLastNameRu.trim();
            if (roleEn.trim()) optionalLocaleFields.roleEn = roleEn.trim();
            if (roleRu.trim()) optionalLocaleFields.roleRu = roleRu.trim();

            await CompanyApi.companyCreate({
                ...payload,
                ...optionalLocaleFields,
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

                    <CompanyFormLocaleToggle value={formLocale} onChange={handleFormLocaleChange} />

                    {formLocale === "lt" ? (
                        <>
                            <div className={styles.form}>
                                <div className={styles.row}>
                                    <InputFieldSelect
                                        label="Įmonės tipas"
                                        options={companyTypeOptions}
                                        selected={selectedCompanyTypeLabel}
                                        onChange={(valueStr) =>
                                            applyCompanyTypeSelection(
                                                companyTypesFromDb,
                                                valueStr,
                                                companyTypeRows,
                                                setCompanyTypeId,
                                                setCompanyTypeShort
                                            )
                                        }
                                        placeholder="Įmonės tipas"
                                        emptyMessage="Tipų nerasta"
                                    />
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
                        </>
                    ) : formLocale === "en" ? (
                        <>
                            <div className={styles.form}>
                                <div className={styles.localeNotice}>
                                    <h2>Anglų kalbos laukai</h2>
                                    <p>Šie duomenys naudojami anglų kalbos šablonams ir nėra privalomi.</p>
                                </div>
                                <InputFieldText value={companyNameEn} onChange={setCompanyNameEn} placeholder="Įmones pavadinimas" />
                                <InputFieldText value={addressEn} onChange={setAddressEn} placeholder="Adresas" />
                                <InputFieldText value={cityOrDistrictEn} onChange={setCityOrDistrictEn} placeholder="Miestas/Rajonas" />
                                <div className={styles.row}>
                                    <InputFieldText value={managerFirstNameEn} onChange={setManagerFirstNameEn} placeholder="Vardas" />
                                    <InputFieldText value={managerLastNameEn} onChange={setManagerLastNameEn} placeholder="Pavardė" />
                                </div>
                                <InputFieldText value={roleEn} onChange={setRoleEn} placeholder="Pareigos" />
                            </div>
                            <button className={styles.submitButton} onClick={handleSubmit}>
                                <Save size={18} />
                                Išsaugoti
                            </button>
                        </>
                    ) : (
                        <>
                            <div className={styles.form}>
                                <div className={styles.localeNotice}>
                                    <h2>Rusų kalbos laukai</h2>
                                    <p>Šie duomenys naudojami rusų kalbos šablonams ir nėra privalomi.</p>
                                </div>
                                <InputFieldText value={companyNameRu} onChange={setCompanyNameRu} placeholder="Įmones pavadinimas" />
                                <InputFieldText value={addressRu} onChange={setAddressRu} placeholder="Adresas" />
                                <InputFieldText value={cityOrDistrictRu} onChange={setCityOrDistrictRu} placeholder="Miestas/Rajonas" />
                                <div className={styles.row}>
                                    <InputFieldText value={managerFirstNameRu} onChange={setManagerFirstNameRu} placeholder="Vardas" />
                                    <InputFieldText value={managerLastNameRu} onChange={setManagerLastNameRu} placeholder="Pavardė" />
                                </div>
                                <InputFieldText value={roleRu} onChange={setRoleRu} placeholder="Pareigos" />
                            </div>
                            <button className={styles.submitButton} onClick={handleSubmit}>
                                <Save size={18} />
                                Išsaugoti
                            </button>
                        </>
                    )}
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