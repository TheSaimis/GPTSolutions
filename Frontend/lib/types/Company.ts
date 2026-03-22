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
    "${companyName}",
  
    "${tipas}",
    "${tipasPilnas}",
    "${TIPASPILNAS}",
  
    "${adresas}",
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

// this is not exactly related to companies but its used everywhere where companies are involved
export type CustomVariable = Record<string, string>;
