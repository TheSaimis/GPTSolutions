"use client";

import { useMemo } from "react";
import { useAAPTable } from "../../AAPTableContext";
import { buildRiskHeader, flattenColumns } from "../tableMatrix";
import styles from "../aapTable.module.scss";

export default function Risks() {
  const { riskGroups, riskCategories, riskSubCategories } = useAAPTable();

  const header = useMemo(
    () => buildRiskHeader(riskGroups, riskCategories, riskSubCategories),
    [riskGroups, riskCategories, riskSubCategories]
  );
  const columns = useMemo(() => flattenColumns(header), [header]);

  return (
    <thead>
      <tr>
        <th
          className={`${styles.th} ${styles.stickyCorner}`}
          rowSpan={3}
          colSpan={2}
        >
          Kūno dalys
        </th>
        {header.map((group) => {
          const span =
            group.directSubcategories.length +
            group.categories.reduce((sum, cat) => sum + cat.subcategories.length, 0);
          if (span === 0) return null;
          return (
            <th key={`g-${group.group.id}`} className={styles.th} colSpan={span}>
              {group.group.name}
            </th>
          );
        })}
      </tr>

      <tr>
        {header.flatMap((group) => {
          const rows = group.categories.map((category) => {
            if (category.subcategories.length === 0) return null;
            return (
              <th
                key={`c-${category.category.id}`}
                className={`${styles.th} ${styles.riskCategoryHeader}`}
                colSpan={category.subcategories.length}
              >
                {category.category.name}
              </th>
            );
          });

          // Keep structure aligned; direct subcategories are rendered in row 3.
          if (group.directSubcategories.length > 0) {
            rows.push(
              <th
                key={`gd-${group.group.id}`}
                className={styles.th}
                colSpan={group.directSubcategories.length}
              />
            );
          }

          return rows;
        })}
      </tr>

      <tr>
        {columns.map((subcategory, index) => (
          <th
            key={`s-${subcategory.id}`}
            className={`${styles.th} ${styles.rotate} ${styles.riskSubcategoryHeader} ${
              index % 2 === 1 ? styles.altColumn : ""
            }`}
          >
            {subcategory.name}
          </th>
        ))}
      </tr>
    </thead>
  );
}