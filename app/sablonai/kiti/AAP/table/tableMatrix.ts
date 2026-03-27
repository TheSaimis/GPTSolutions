import type { BodyPart, BodyPartCategory } from "@/lib/types/AAP/BodyPart";
import type { RiskCategory, RiskGroup, RiskSubcategory } from "@/lib/types/AAP/Risk";

export type RiskHeaderGroup = {
  group: RiskGroup;
  categories: Array<{
    category: RiskCategory;
    subcategories: RiskSubcategory[];
  }>;
  directSubcategories: RiskSubcategory[];
};

export type BodyRow = {
  category: BodyPartCategory;
  parts: BodyPart[];
};

export function sortByLineAndId<T extends { lineNumber: number; id: number }>(items: T[]): T[] {
  return [...items].sort((a, b) => {
    if (a.lineNumber !== b.lineNumber) return a.lineNumber - b.lineNumber;
    return a.id - b.id;
  });
}

export function buildRiskHeader(
  riskGroups: RiskGroup[],
  riskCategories: RiskCategory[],
  riskSubcategories: RiskSubcategory[]
): RiskHeaderGroup[] {
  return sortByLineAndId(riskGroups).map((group) => {
    const categories = sortByLineAndId(
      riskCategories.filter((category) => category.group?.id === group.id)
    ).map((category) => ({
      category,
      subcategories: sortByLineAndId(
        riskSubcategories.filter((subcategory) => subcategory.category?.id === category.id)
      ),
    }));

    const directSubcategories = sortByLineAndId(
      riskSubcategories.filter(
        (subcategory) => subcategory.category === null && subcategory.group?.id === group.id
      )
    );

    return {
      group,
      categories,
      directSubcategories,
    };
  });
}

export function flattenColumns(header: RiskHeaderGroup[]): RiskSubcategory[] {
  const cols: RiskSubcategory[] = [];
  header.forEach((group) => {
    group.categories.forEach((category) => {
      cols.push(...category.subcategories);
    });
    cols.push(...group.directSubcategories);
  });
  return cols;
}

export function buildBodyRows(
  categories: BodyPartCategory[],
  parts: BodyPart[]
): BodyRow[] {
  return sortByLineAndId(categories).map((category) => ({
    category,
    parts: sortByLineAndId(parts.filter((part) => part.category?.id === category.id)),
  }));
}
