import { TemplateList } from "@/lib/types/TemplateList";
import { CatalogueFilters } from "@/app/sablonai/catalogueTreeContext";

function normalize(value?: string | number | null) {
  return String(value ?? "").trim().toLowerCase();
}

function matchesTextFilter(filters: string[], value?: string) {
  if (filters.length === 0) return true;

  const normalizedValue = normalize(value);

  return filters.some((filterValue) =>
    normalizedValue.includes(normalize(filterValue))
  );
}

function matchesExactFilter(filters: string[], value?: string) {
  if (filters.length === 0) return true;

  const normalizedValue = normalize(value);

  return filters.some((filterValue) => normalizedValue === normalize(filterValue));
}

function detectTemplateLanguage(node: TemplateList): string {
  const custom: any = node.metadata?.custom ?? {};
  const raw =
    custom.language ??
    custom.lang ??
    custom.Language ??
    custom.Lang ??
    "";

  if (typeof raw === "string") {
    const v = raw.trim().toUpperCase();
    if (v === "LT" || v === "RU" || v === "EN") return v;
  }

  const text = `${node.name ?? ""} ${node.path ?? ""}`.toUpperCase();

  // Fallback: ieskom "LT/RU/EN" pagal zodzius (atskiriant pagal kelio/siuksniu simbolius).
  // Tai saugiau nei sudetingi regex su charakteriu klases.
  const normalized = text.replace(/[\\/_\-.\s]+/g, " ").trim();
  const tokens = normalized.split(" ").filter(Boolean);
  if (tokens.includes("RU")) return "RU";
  if (tokens.includes("LT")) return "LT";
  if (tokens.includes("EN")) return "EN";

  // Tavo taisykle: jei nera nei RU nei EN, laikom lietuviu (LT).
  return "LT";
}

function matchesLanguageFilter(filters: string[], node: TemplateList) {
  if (filters.length === 0) return true;
  const lang = detectTemplateLanguage(node);
  return matchesTextFilter(filters, lang);
}

function isWithinDateRange(dateValue?: string, from?: string, to?: string) {
  if (!dateValue) return true;

  const date = new Date(dateValue);
  if (Number.isNaN(date.getTime())) return true;

  if (from) {
    const fromDate = new Date(from);
    if (!Number.isNaN(fromDate.getTime()) && date < fromDate) return false;
  }

  if (to) {
    const toDate = new Date(to);
    if (!Number.isNaN(toDate.getTime())) {
      toDate.setHours(23, 59, 59, 999);
      if (date > toDate) return false;
    }
  }

  return true;
}

function matchesSearch(node: TemplateList, search: string) {
  const q = normalize(search);
  if (!q) return true;

  const values = [
    node.name,
    node.path,
    node.metadata?.custom?.type,
    node.metadata?.custom?.company,
    node.metadata?.custom?.createdBy,
    node.metadata?.custom?.userId,
    node.metadata?.custom?.companyId,
    node.metadata?.custom?.templateId,
    node.metadata?.custom?.documentId,
    node.metadata?.core?.title,
    node.metadata?.core?.subject,
    node.metadata?.core?.description,
    node.metadata?.core?.creator,
  ];

  return values.some((value) => normalize(value).includes(q));
}

function matchesFile(node: TemplateList, filters: CatalogueFilters): boolean {
  const custom = node.metadata?.custom;

  return (
    matchesSearch(node, filters.search) &&
    matchesExactFilter(filters.types, custom?.type) &&
    matchesTextFilter(filters.companies, custom?.company) &&
    matchesLanguageFilter(filters.languages, node) &&
    matchesTextFilter(filters.createdBy, custom?.createdBy) &&
    matchesTextFilter(filters.userIds, custom?.userId) &&
    matchesTextFilter(filters.companyIds, custom?.companyId) &&
    matchesTextFilter(filters.templateIds, custom?.templateId) &&
    matchesTextFilter(filters.documentIds, custom?.documentId) &&
    matchesTextFilter(filters.mimeTypes, custom?.mimeType) &&
    isWithinDateRange(custom?.created, filters.createdFrom, filters.createdTo)
  );
}

function hasActiveFilters(filters: CatalogueFilters) {
  return Boolean(
    normalize(filters.search) ||
    filters.types.length ||
    filters.companies.length ||
    filters.languages.length ||
    filters.createdBy.length ||
    filters.userIds.length ||
    filters.companyIds.length ||
    filters.templateIds.length ||
    filters.documentIds.length ||
    filters.mimeTypes.length ||
    filters.createdFrom ||
    filters.createdTo
  );
}

export function filterCatalogueTree(
  nodes: TemplateList[] = [],
  filters: CatalogueFilters
): TemplateList[] {
  const active = hasActiveFilters(filters);

  if (!active && filters.showEmptyDirectories) {
    return nodes;
  }

  return nodes.reduce<TemplateList[]>((acc, node) => {
    if (node.type === "file") {
      if (matchesFile(node, filters)) {
        acc.push(node);
      }
      return acc;
    }

    const filteredChildren = filterCatalogueTree(node.children ?? [], filters);

    if (filteredChildren.length > 0 || (!active && filters.showEmptyDirectories)) {
      acc.push({
        ...node,
        children: filteredChildren.length > 0 ? filteredChildren : node.children ?? [],
      });
    }

    return acc;
  }, []);
}