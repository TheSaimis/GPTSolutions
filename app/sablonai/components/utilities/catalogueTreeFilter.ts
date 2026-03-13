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
    matchesTextFilter(filters.types, custom?.type) &&
    matchesTextFilter(filters.companies, custom?.company) &&
    matchesTextFilter(filters.createdBy, custom?.createdBy) &&
    matchesTextFilter(filters.userIds, custom?.userId) &&
    matchesTextFilter(filters.companyIds, custom?.companyId) &&
    matchesTextFilter(filters.templateIds, custom?.templateId) &&
    matchesTextFilter(filters.documentIds, custom?.documentId) &&
    isWithinDateRange(custom?.created, filters.createdFrom, filters.createdTo)
  );
}

function hasActiveFilters(filters: CatalogueFilters) {
  return Boolean(
    normalize(filters.search) ||
      filters.types.length ||
      filters.companies.length ||
      filters.createdBy.length ||
      filters.userIds.length ||
      filters.companyIds.length ||
      filters.templateIds.length ||
      filters.documentIds.length ||
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