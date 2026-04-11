import type { Company, CompanyTypeRow } from "@/lib/types/Company";
import type { Json } from "@/lib/api/api";

function coercePositiveInt(v: unknown): number | null {
    if (v == null || v === "") return null;
    const n = typeof v === "number" ? v : parseInt(String(v), 10);
    if (!Number.isFinite(n) || n <= 0) return null;
    return n;
}

export function normalizeCompanyTypeRow(raw: unknown): CompanyTypeRow | null {
    if (!raw || typeof raw !== "object") return null;
    const r = raw as Record<string, unknown>;
    const id = Number(r.id);
    if (!Number.isFinite(id)) return null;
    const typeShort = String(r.typeShort ?? r.type_short ?? "").trim();
    const typeFull = String(r.type ?? "").trim();
    const short = typeShort || typeFull;
    const full = typeFull || short;
    return {
        id,
        typeShort: short,
        type: full,
        typeShortEn: (r.typeShortEn ?? r.type_short_en) as string | null | undefined,
        typeShortRu: (r.typeShortRu ?? r.type_short_ru) as string | null | undefined,
        typeEn: (r.typeEn ?? r.type_en) as string | null | undefined,
        typeRu: (r.typeRu ?? r.type_ru) as string | null | undefined,
    };
}

export function normalizeCompanyTypeRows(raw: unknown): CompanyTypeRow[] {
    if (!Array.isArray(raw)) return [];
    return raw.map(normalizeCompanyTypeRow).filter((x): x is CompanyTypeRow => x != null);
}

/** Backend (Symfony) dažnai grąžina snake_case; forma tikisi camelCase. */
export function normalizeCompanyFromApi(data: unknown): Company {
    if (!data || typeof data !== "object") return {} as Company;
    const r = data as Record<string, unknown>;
    const merged: Record<string, unknown> = { ...r };

    const ctId = r.companyTypeId ?? r.company_type_id;
    if (ctId !== undefined) merged.companyTypeId = coercePositiveInt(ctId);

    const ct = r.companyType ?? r.company_type;
    if (ct != null && ct !== "") merged.companyType = String(ct).trim();

    const ctr = r.companyTypeRow ?? r.company_type_row;
    if (ctr && typeof ctr === "object") {
        const row = normalizeCompanyTypeRow(ctr);
        if (row) {
            merged.companyTypeRow = row;
            if (merged.companyType == null || String(merged.companyType).trim() === "") {
                merged.companyType = row.typeShort;
            }
            if (merged.companyTypeId == null && row.id > 0) {
                merged.companyTypeId = row.id;
            }
        }
    }

    const akp = r.aapKortelesPagrindas ?? r.aap_korteles_pagrindas;
    if (akp !== undefined) {
        merged.aapKortelesPagrindas = akp == null || akp === "" ? null : String(akp);
    }

    return merged as Company;
}

/** Jei backend tikisi snake_case POST laukuose. */
export function withCompanyWriteAliases(body: Record<string, unknown>): Json {
    const p = { ...body };
    if ("companyTypeId" in p && p.companyTypeId != null) {
        p.company_type_id = p.companyTypeId;
    }
    if ("companyType" in p && p.companyType != null && String(p.companyType).trim() !== "") {
        p.company_type = p.companyType;
    }
    if ("categoryId" in p && p.categoryId != null) {
        p.category_id = p.categoryId;
    }
    return p as Json;
}
