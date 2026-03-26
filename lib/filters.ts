import type { User } from "./types/User";
import type { Company } from "./types/Company";

export type SelectOption = { value: string; label: string };

// ── Role options (user list) ──

export const ROLE_OPTIONS: SelectOption[] = [
    { value: "all", label: "Visi" },
    { value: "ROLE_ADMIN", label: "Administratoriai" },
    { value: "ROLE_USER", label: "Naudotojai" },
];

export function normalizeRole(role?: string | string[] | null): string {
    const rawRole = Array.isArray(role) ? role[0] : role;
    const value = typeof rawRole === "string" ? rawRole.toUpperCase() : "";
    if (value.includes("ROLE_ADMIN") || value.includes("ADMIN")) return "ROLE_ADMIN";
    if (value.includes("ROLE_USER") || value.includes("USER")) return "ROLE_USER";
    return "";
}

export function roleLabel(role?: string): string {
    const normalized = normalizeRole(role);
    if (normalized === "ROLE_ADMIN") return "Administratorius";
    if (normalized === "ROLE_USER") return "Naudotojas";
    return "Nenurodyta";
}

// ── User sort ──

export const USER_SORT_OPTIONS: SelectOption[] = [
    { value: "name-asc", label: "Vardas (A-Z)" },
    { value: "name-desc", label: "Vardas (Z-A)" },
    { value: "email-asc", label: "El. paštas (A-Z)" },
    { value: "email-desc", label: "El. paštas (Z-A)" },
];

export function sortUsers(users: User[], sortBy: string): User[] {
    const sorted = [...users];
    sorted.sort((a, b) => {
        const aName = `${a.firstName ?? ""} ${a.lastName ?? ""}`.trim();
        const bName = `${b.firstName ?? ""} ${b.lastName ?? ""}`.trim();
        if (sortBy === "name-asc") return aName.localeCompare(bName, "lt");
        if (sortBy === "name-desc") return bName.localeCompare(aName, "lt");
        if (sortBy === "email-asc") return (a.email ?? "").localeCompare(b.email ?? "", "lt");
        if (sortBy === "email-desc") return (b.email ?? "").localeCompare(a.email ?? "", "lt");
        return 0;
    });
    return sorted;
}

// ── User view mode ──

export const USER_VIEW_OPTIONS: SelectOption[] = [
    { value: "compact", label: "Eilutėmis" },
    { value: "large", label: "Kortelėmis" },
    { value: "mini", label: "Kompaktiškas" },
];

// ── Company sort ──

export const COMPANY_SORT_OPTIONS: SelectOption[] = [
    { value: "name-asc", label: "Pagal pavadinimą (A-Z)" },
    { value: "name-desc", label: "Pagal pavadinimą (Z-A)" },
    { value: "code-asc", label: "Pagal kodą (A-Z)" },
    { value: "code-desc", label: "Pagal kodą (Z-A)" },
    { value: "created-newest", label: "Pagal sukūrimo datą (nuo naujausių)" },
    { value: "created-oldest", label: "Pagal sukūrimo datą (nuo seniausių)" },
];

export function sortCompanies(companies: Company[], sortBy: string): Company[] {
    const sorted = [...companies];
    sorted.sort((a, b) => {
        if (sortBy === "name-asc") return (a.companyName ?? "").localeCompare(b.companyName ?? "", "lt");
        if (sortBy === "name-desc") return (b.companyName ?? "").localeCompare(a.companyName ?? "", "lt");
        if (sortBy === "code-asc") return (a.code ?? "").localeCompare(b.code ?? "", "lt");
        if (sortBy === "code-desc") return (b.code ?? "").localeCompare(a.code ?? "", "lt");
        if (sortBy === "created-newest") return new Date(b.createdAt ?? 0).getTime() - new Date(a.createdAt ?? 0).getTime();
        if (sortBy === "created-oldest") return new Date(a.createdAt ?? 0).getTime() - new Date(b.createdAt ?? 0).getTime();
        return 0;
    });
    return sorted;
}

// ── Helpers for building dropdown options from live data ──

export function buildCompanyOptions(companies: Company[]): SelectOption[] {
    const names = Array.from(
        new Set(
            companies
                .map((c) => c.companyName?.trim())
                .filter((v): v is string => Boolean(v))
        )
    ).sort((a, b) => a.localeCompare(b, "lt"));

    return [{ value: "all", label: "Visos įmonės" }, ...names.map((n) => ({ value: n, label: n }))];
}

export function buildCompanyTypeOptions(companies: Company[]): SelectOption[] {
    const types = Array.from(
        new Set(
            companies
                .map((c) => c.companyType?.trim())
                .filter((v): v is string => Boolean(v))
        )
    ).sort((a, b) => a.localeCompare(b, "lt"));

    return [{ value: "all", label: "Visi tipai" }, ...types.map((t) => ({ value: t, label: t }))];
}

export function buildUserOptions(users: User[]): SelectOption[] {
    const names = Array.from(
        new Set(
            users
                .map((u) => {
                    const full = `${u.firstName ?? ""} ${u.lastName ?? ""}`.trim();
                    return full || u.email;
                })
                .filter((v): v is string => Boolean(v))
        )
    ).sort((a, b) => a.localeCompare(b, "lt"));

    return [{ value: "all", label: "Visi vartotojai" }, ...names.map((n) => ({ value: n, label: n }))];
}

// ── Deleted status filter ──

export type DeletedFilter = "active" | "deleted" | "all";

export const DELETED_STATUS_OPTIONS: SelectOption[] = [
    { value: "active", label: "Aktyvūs" },
    { value: "deleted", label: "Ištrinti" },
    { value: "all", label: "Visi" },
];

export function matchesDeletedFilter<T extends { deleted?: boolean }>(
    item: T,
    filter: DeletedFilter
): boolean {
    if (filter === "all") return true;
    if (filter === "deleted") return item.deleted === true;
    return !item.deleted;
}

export function toggleArrayValue(arr: string[], value: string, checked: boolean): string[] {
    if (checked) return [...arr, value];
    return arr.filter((v) => v !== value);
}
