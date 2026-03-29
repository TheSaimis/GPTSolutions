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
  const columnIndexById = useMemo(
    () => new Map(columns.map((column, index) => [column.id, index])),
    [columns]
  );

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
          const cells = [];
          let i = 0;
          while (i < group.columns.length) {
            const current = group.columns[i];
            if (current.category === null) {
              cells.push(
                <th
                  key={`d-${group.group.id}-${current.subcategory.id}`}
                  className={`${styles.th} ${styles.riskSubcategoryVertical} ${styles.riskHeaderLeaf} ${styles.riskCol}`}
                  rowSpan={2}
                >
                  {current.subcategory.name}
                </th>
              );
              i++;
              continue;
            }

            const categoryId = current.category.id;
            let span = 1;
            while (
              i + span < group.columns.length &&
              group.columns[i + span].category?.id === categoryId
            ) {
              span++;
            }

            cells.push(
              <th key={`c-${categoryId}-${i}`} className={styles.th} colSpan={span}>
                {current.category.name}
              </th>
            );
            i += span;
          }

          return cells;
        })}
      </tr>

      <tr>
        {header.flatMap((group) =>
          group.columns.map((column) => {
            if (column.category === null) {
              return null;
            }
            const globalIndex = columnIndexById.get(column.subcategory.id) ?? 0;
            return (
              <th
                key={`s-${column.subcategory.id}`}
                className={`${styles.th} ${styles.riskSubcategoryVertical} ${styles.riskHeaderLeaf} ${styles.riskCol} ${
                  globalIndex % 2 === 1 ? styles.altColumn : ""
                }`}
              >
                {column.subcategory.name}
              </th>
            );
          })
        )}
      </tr>
    </thead>
  );
}