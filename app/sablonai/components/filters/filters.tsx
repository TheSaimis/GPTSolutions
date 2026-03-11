"use client";

import styles from "./filters.module.scss";
import { CatalogueTreeProvider, useCatalogueTree } from "../../catalogueTreeContext";
import CheckBox from "@/components/inputFields/checkBox";
import { COMPANY_TYPES } from "@/lib/types/Company";
import { ListFilter } from "lucide-react";

export default function Filters() {

  const { typeFilter, setTypeFilter } = useCatalogueTree();
  const { companyFilter, setCompanyFilter } = useCatalogueTree();



  return (
    <div className={styles.filters}>
      <h1><ListFilter /> Filtrai</h1>

      <div className={styles.filter}>
        <h2>Tipas</h2>
        {COMPANY_TYPES.map((type) => (
          <div key={type} className={`${styles.types}`}>
            <CheckBox key={type} value={typeFilter.includes(type)} onChange={(value) => setTypeFilter(value ? [...typeFilter, type] : typeFilter.filter((t) => t !== type))} />
              {type}
          </div>
        ))}
      </div>


    </div>
  );
}