import type { Worker } from "./Worker";

export type CompanyTypeRow = {
    id: number;
    typeShort: string;
    typeShortEn?: string | null;
    typeShortRu?: string | null;
    type: string;
    typeEn?: string | null;
    typeRu?: string | null;
};

export type Company = {
    id?: number;
    companyType?: string;
    companyTypeId?: number | null;
    companyTypeRow?: CompanyTypeRow | null;
    companyName?: string;
    companyNameEn?: string | null;
    companyNameRu?: string | null;
    code?: string;
    categoryId?: number | null;
    categoryName?: string | null;
    address?: string;
    addressEn?: string | null;
    addressRu?: string | null;
    cityOrDistrict?: string;
    cityOrDistrictEn?: string | null;
    cityOrDistrictRu?: string | null;
    managerType?: string;
    managerFirstName?: string;
    managerFirstNameEn?: string | null;
    managerFirstNameRu?: string | null;
    managerLastName?: string;
    managerLastNameEn?: string | null;
    managerLastNameRu?: string | null;
    managerGender?: string;
    documentDate?: string;
    modifiedAt?: string;
    createdAt?: string;
    deleted?: boolean;
    deletedDate?: string;
    role?: string;
    roleEn?: string | null;
    roleRu?: string | null;
};

export const companyLabels: Record<keyof Company, string> = {
    id: "ID",
    companyType: "Įmonės tipas",
    companyTypeId: "Įmonės tipo ID",
    companyTypeRow: "Įmonės tipas (eilutė)",
    companyName: "Įmonės pavadinimas",
    companyNameEn: "Įmonės pavadinimas (EN)",
    companyNameRu: "Įmonės pavadinimas (RU)",
    code: "Įmonės kodas",
    categoryId: "Kategorijos ID",
    categoryName: "Kategorija",
    address: "Adresas",
    addressEn: "Adresas (EN)",
    addressRu: "Adresas (RU)",
    cityOrDistrict: "Miestas / rajonas",
    cityOrDistrictEn: "Miestas / rajonas (EN)",
    cityOrDistrictRu: "Miestas / rajonas (RU)",
    managerType: "Vadovo tipas",
    managerFirstName: "Vadovo vardas",
    managerFirstNameEn: "Vadovo vardas (EN)",
    managerFirstNameRu: "Vadovo vardas (RU)",
    managerLastName: "Vadovo pavardė",
    managerLastNameEn: "Vadovo pavardė (EN)",
    managerLastNameRu: "Vadovo pavardė (RU)",
    managerGender: "Vadovo lytis",
    documentDate: "Dokumento data",
    modifiedAt: "Redaguota",
    createdAt: "Sukurta",
    role: "Pareigos",
    roleEn: "Pareigos (EN)",
    roleRu: "Pareigos (RU)",
    deleted: "Ištrinta",
    deletedDate: "Ištrinimo data",
};
export const COMPANY_TYPES = [
    "UAB",
    "AB",
    "MB",
    "VŠĮ",
    "IĮ",
    "IND V."
] as const;

export const wordVariables = [
    "${kompanija}",
    "${companyName}",
    "${companyDirectory}",
    "${atliktiDarbai}",

    "${tipas}",
    "${tipasPilnas}",
    "${TIPASPILNAS}",
    "${tipasKompaktiskas}",
    "${TIPASKOMPAKTISKAS}",

    "${adresas}",
    "${Miestas}",
    "${kodas}",
    "${code}",

    "${data}",
    "${documentDate}",
    "${dataSkaitmenimis}",

    "${role}",
    "${lytis}",

    "${vadovas}",
    "${vadovo}",
    "${vadovui}",
    "${vadovą}",
    "${vadovu}",
    "${vadove}",
    "${vadovėje}",
    "${vadovei}",
    "${vadovę}",
    "${vadovasNom}",
    "${vadovasKreip}",
    "${vadoves}",
    "${vadovai}",

    "${vardas}",
    "${vardo}",
    "${vardui}",
    "${vardą}",
    "${vardu}",
    "${vardviet}",
    "${varde}",
    "${vardes}",

    "${pavarde}",
    "${pavardes}",
    "${pavardui}",
    "${pavardą}",
    "${pavardu}",
    "${pavardviet}",
    "${pavardeS}",
    "${pavardo}",
] as const;


export interface CompanyWorker {
    id: number;
    company: Company | null;
    worker: Worker | null;
}

export type CompanyCategory = {
    id: number;
    name: string;
};

// this is not exactly related to companies but its used everywhere where companies are involved
export type CustomVariable = Record<string, string>;
