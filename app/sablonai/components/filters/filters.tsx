"use client";

import styles from "./filters.module.scss";
import { useCatalogueTree } from "../../catalogueTreeContext";
import CheckBox from "@/components/inputFields/checkBox";
import InputFieldText from "@/components/inputFields/inputFieldText";
import InputFieldDate from "@/components/inputFields/inputFieldDate";
import { COMPANY_TYPES } from "@/lib/types/Company";
import { ListFilter } from "lucide-react";

export default function Filters() {

  const { filters, setFilters } = useCatalogueTree();

  function toggleArrayValue(arr: string[], value: string, checked: boolean) {
    if (checked) return [...arr, value];
    return arr.filter(v => v !== value);
  }

  return (
    <div className={styles.container}>
      <div className={styles.filters}>

        <h1>
          <ListFilter size={20} />
          Filtrai
        </h1>

        {/* TYPE FILTER */}
        <div className={`${styles.filter} ${styles.sectionCard}`}>
          <h2>Tipas</h2>

          <div className={styles.checkboxGroup}>
            {COMPANY_TYPES.map((type) => (
              <div key={type} className={styles.types}>
                <CheckBox
                  value={filters.types.includes(type)}
                  onChange={(checked) =>
                    setFilters(prev => ({
                      ...prev,
                      types: toggleArrayValue(prev.types, type, checked)
                    }))
                  }
                />
                <span>{type}</span>
              </div>
            ))}
          </div>
        </div>

        {/* COMPANY FILTER */}
        <div className={`${styles.filter} ${styles.sectionCard}`}>
          <InputFieldText
            value={filters.companies[0] ?? ""}
            placeholder="Įmonė"
            onChange={(value) =>
              setFilters(prev => ({
                ...prev,
                companies: value ? [value] : []
              }))
            }
          />
        </div>

        {/* CREATED BY FILTER */}
        <div className={`${styles.filter} ${styles.sectionCard}`}>
          <InputFieldText
            value={filters.createdBy[0] ?? ""}
            placeholder="Vartotojas"
            onChange={(value) =>
              setFilters(prev => ({
                ...prev,
                createdBy: value ? [value] : []
              }))
            }
          />
        </div>

        {/* DATE FILTER */}
        <div className={`${styles.filter} ${styles.sectionCard}`}>

          <InputFieldDate
            value={filters.createdFrom}
            placeholder="Data nuo"
            onChange={(value) =>
              setFilters(prev => ({
                ...prev,
                createdFrom: value
              }))
            }
          />

          <InputFieldDate
            value={filters.createdTo}
            placeholder="Data iki"
            onChange={(value) =>
              setFilters(prev => ({
                ...prev,
                createdTo: value
              }))
            }
          />

        </div>

        {/* SHOW EMPTY FOLDERS */}
        <div className={`${styles.filter} ${styles.sectionCard}`}>
          <div className={styles.toggleRow}>
            <CheckBox
              value={filters.showEmptyDirectories}
              onChange={(checked) =>
                setFilters(prev => ({
                  ...prev,
                  showEmptyDirectories: checked
                }))
              }
            />
            <span>Rodyti tuščius aplankus</span>
          </div>
        </div>

      </div>
    </div>
  );
}