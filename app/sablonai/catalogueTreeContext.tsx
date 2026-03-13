"use client";

import { createContext, useContext, useMemo, useState, ReactNode, useEffect } from "react";
import { TemplateList } from "@/lib/types/TemplateList";
import { filterCatalogueTree } from "./components/utilities/catalogueTreeFilter";

export type CatalogueFilters = {
  search: string;
  types: string[];
  companies: string[];
  createdBy: string[];
  userIds: string[];
  companyIds: string[];
  templateIds: string[];
  documentIds: string[];
  createdFrom: string;
  createdTo: string;
  showEmptyDirectories: boolean;
};

type CatalogueTreeType = {
  filters: CatalogueFilters;
  setFilters: React.Dispatch<React.SetStateAction<CatalogueFilters>>;
  catalogueTree: TemplateList[];
  setCatalogueTree: React.Dispatch<React.SetStateAction<TemplateList[]>>;
  filteredCatalogueTree: TemplateList[];
};

const CatalogueTreeContext = createContext<CatalogueTreeType | undefined>(undefined);

const defaultFilters: CatalogueFilters = {
  search: "",
  types: [],
  companies: [],
  createdBy: [],
  userIds: [],
  companyIds: [],
  templateIds: [],
  documentIds: [],
  createdFrom: "",
  createdTo: "",
  showEmptyDirectories: true,
};

export function CatalogueTreeProvider({
  children,
  initialTree,
}: {
  children: ReactNode;
  initialTree: TemplateList[];
}) {
  const [filters, setFilters] = useState<CatalogueFilters>(defaultFilters);
  const [catalogueTree, setCatalogueTree] = useState<TemplateList[]>(initialTree ?? []);

  useEffect(() => {
    setCatalogueTree(initialTree ?? []);
  }, [initialTree]);

  const filteredCatalogueTree = useMemo(() => {
    return filterCatalogueTree(catalogueTree ?? [], filters);
  }, [catalogueTree, filters]);

  return (
    <CatalogueTreeContext.Provider
      value={{
        filters,
        setFilters,
        catalogueTree,
        setCatalogueTree,
        filteredCatalogueTree,
      }}
    >
      {children}
    </CatalogueTreeContext.Provider>
  );
}

export function useCatalogueTree() {
  const context = useContext(CatalogueTreeContext);
  if (!context) {
    throw new Error("useCatalogueTree must be used inside CatalogueTreeProvider");
  }
  return context;
}