export type Company = {
    id?: number;
    companyType?: string;
    companyName?: string;
    code?: string;
    address?: string;
    cityOrDistrict?: string;
    managerType?: string;
    managerFirstName?: string;
    managerLastName?: string;
    managerGender?: string;
    documentDate?: string;
    modifiedAt?: string;
    createdAt?: string;
    role?: string;
};

export const companyLabels: Record<keyof Company, string> = {
    id: "ID",
    companyType: "Įmonės tipas",
    companyName: "Įmonės pavadinimas",
    code: "Įmonės kodas",
    address: "Adresas",
    cityOrDistrict: "Miestas / rajonas",
    managerType: "Vadovo tipas",
    managerFirstName: "Vadovo vardas",
    managerLastName: "Vadovo pavardė",
    managerGender: "Vadovo lytis",
    documentDate: "Dokumento data",
    modifiedAt: "Redaguota",
    createdAt: "Sukurta",
    role: "Pareigos",
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
    "${tipas}",
    "${tipasPilnas}",
    "${adresas}",
    "${kodas}",
    "${data}",
    "${vadovas}",
    "${vadovo}",
    "${vardas}",
    "${vardo}",
    "${vardes}",
    "${pavarde}",
    "${pavardes}",
    "${pavardo}",
    "${role}",
] as const;