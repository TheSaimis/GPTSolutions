import { COMPANY_TYPES } from "@/lib/types/Company";
import type { CompanyTypeRow } from "@/lib/types/Company";

/** Kai DB lentelė company_types turi įrašų — pasirinkimas pagal id; kitaip — fiksuotas sąrašas ir companyType tekstas. */
export function buildCompanyTypeDropdownOptions(rows: CompanyTypeRow[]) {
    const fromDatabase = rows.length > 0;
    const options = fromDatabase
        ? rows.map((t) => ({ value: String(t.id), label: t.typeShort }))
        : [...COMPANY_TYPES].map((s) => ({ value: s, label: s }));
    return { fromDatabase, options };
}

export function applyCompanyTypeSelection(
    fromDatabase: boolean,
    valueStr: string,
    rows: CompanyTypeRow[],
    setCompanyTypeId: (id: number | null) => void,
    setCompanyTypeShort: (s: string) => void
) {
    if (fromDatabase) {
        const n = Number(valueStr);
        const id = Number.isFinite(n) && n > 0 ? n : null;
        setCompanyTypeId(id);
        const row =
            id == null ? undefined : rows.find((t) => Number(t.id) === id);
        setCompanyTypeShort(row?.typeShort ?? "");
    } else {
        setCompanyTypeId(null);
        setCompanyTypeShort(valueStr);
    }
}
