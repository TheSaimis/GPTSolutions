"use client";

import { createContext, useContext, useState, ReactNode } from "react";
import { TemplateList } from "@/lib/types/TemplateList";

type CatalogueTreeType = {
    search: string;
    setSearch: (v: string) => void;
    typeFilter: string[];
    setTypeFilter: (v: string[]) => void;
    companyFilter: string[];
    setCompanyFilter: (v: string[]) => void;
    catalogueTree: TemplateList[]
    setCatalogueTree: (v: TemplateList[]) => void
};

const CatalogueTreeContext = createContext<CatalogueTreeType | undefined>(undefined);

export function CatalogueTreeProvider({ children }: { children: ReactNode }) {

    const [search, setSearch] = useState<string>("");
    const [typeFilter, setTypeFilter] = useState<string[]>([]);
    const [companyFilter, setCompanyFilter] = useState<string[]>([]);
    const [catalogueTree, setCatalogueTree] = useState<TemplateList[]>([]);

    return (
        <CatalogueTreeContext.Provider value={{ search, setSearch, typeFilter, setTypeFilter, companyFilter, setCompanyFilter, catalogueTree: [], setCatalogueTree: () => { } }}>
            {children}
        </CatalogueTreeContext.Provider>
    );
}

export function useCatalogueTree() {
    const context = useContext(CatalogueTreeContext);
    if (!context) {
        throw new Error("useCatalogueTree must be used inside AppProvider");
    }
    return context;
}