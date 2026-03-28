import type { BodyPart, BodyPartCategory } from "@/lib/types/AAP/BodyPart";
import type { RiskCategory, RiskGroup, RiskSubcategory } from "@/lib/types/AAP/Risk";

export type RiskHeaderGroup = {
  group: RiskGroup;
  categories: Array<{
    category: RiskCategory;
    subcategories: RiskSubcategory[];
  }>;
  directSubcategories: RiskSubcategory[];
  columns: Array<{
    subcategory: RiskSubcategory;
    category: RiskCategory | null;
  }>;
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

    const columns = [
      ...categories.flatMap(({ category, subcategories }) =>
        subcategories.map((subcategory) => ({ subcategory, category }))
      ),
      ...directSubcategories.map((subcategory) => ({ subcategory, category: null })),
    ].sort((a, b) => {
      if (a.subcategory.lineNumber !== b.subcategory.lineNumber) {
        return a.subcategory.lineNumber - b.subcategory.lineNumber;
      }
      return a.subcategory.id - b.subcategory.id;
    });

    return {
      group,
      categories,
      directSubcategories,
      columns,
    };
  });
}

export function flattenColumns(header: RiskHeaderGroup[]): RiskSubcategory[] {
  const cols: RiskSubcategory[] = [];
  header.forEach((group) => {
    cols.push(...group.columns.map((column) => column.subcategory));
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
