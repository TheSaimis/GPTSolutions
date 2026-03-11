export type Company = {
    id?: number;
    company_type?: string;
    company_name?: string;
    code?: string;
    address?: string;
    city_or_district?: string;
    manager_type?: string;
    manager_first_name?: string;
    manager_last_name?: string;
    manager_gender?: string;
    document_date?: string;
    role?: string;
};

export const COMPANY_TYPES = [
    "UAB",
    "AB",
    "MB",
    "VŠĮ",
    "IĮ",
    "IND V."
  ] as const;