"use client";

import { useMemo } from "react";
import styles from "./filters.module.scss";
import { useCatalogueTree } from "../../catalogueTreeContext";
import CheckBox from "@/components/inputFields/checkBox";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import InputFieldDate from "@/components/inputFields/inputFieldDate";
import { COMPANY_TYPES } from "@/lib/types/Company";
import { ListFilter } from "lucide-react";
import { TemplateList } from "@/lib/types/TemplateList";
import { toggleArrayValue, type SelectOption } from "@/lib/filters";

type AvailableMeta = {
  hasTypes: boolean;
  hasCompanies: boolean;
  hasMimeVariety: boolean;
  hasLanguageVariety: boolean;
  hasCreatedBy: boolean;
  hasCreatedDate: boolean;
  companyNames: string[];
  userNames: string[];
};

function detectLang(node: TemplateList): string {
  const custom: Record<string, unknown> = (node.metadata?.custom as Record<string, unknown>) ?? {};
  const raw = custom.language ?? custom.lang ?? custom.Language ?? custom.Lang ?? "";
  if (typeof raw === "string") {
    const v = raw.trim().toUpperCase();
    if (v === "LT" || v === "RU" || v === "EN") return v;
  }
  const text = `${node.name ?? ""} ${node.path ?? ""}`.toUpperCase();
  const tokens = text.replace(/[\\/_\-.\s]+/g, " ").trim().split(" ").filter(Boolean);
  if (tokens.includes("RU")) return "RU";
  if (tokens.includes("EN")) return "EN";
  return "LT";
}

function collectMetadata(nodes: TemplateList[]): AvailableMeta {
  const mimeTypes = new Set<string>();
  const languages = new Set<string>();
  const companyNameSet = new Set<string>();
  const userNameSet = new Set<string>();
  let hasTypes = false;
  let hasCompanies = false;
  let hasCreatedBy = false;
  let hasCreatedDate = false;

  function walk(items: TemplateList[]) {
    for (const node of items) {
      if (node.type === "directory") {
        if (node.children) walk(node.children);
        continue;
      }
      const custom = node.metadata?.custom;
      if (custom?.type) hasTypes = true;
      if (custom?.company) {
        hasCompanies = true;
        const name = String(custom.company).trim();
        if (name) companyNameSet.add(name);
      }
      if (custom?.createdBy) {
        hasCreatedBy = true;
        const name = String(custom.createdBy).trim();
        if (name) userNameSet.add(name);
      }
      if (custom?.created) hasCreatedDate = true;
      if (custom?.mimeType) mimeTypes.add(String(custom.mimeType));
      languages.add(detectLang(node));
    }
  }

  walk(nodes);

  return {
    hasTypes,
    hasCompanies,
    hasMimeVariety: mimeTypes.size > 1,
    hasLanguageVariety: languages.size > 1,
    hasCreatedBy,
    hasCreatedDate,
    companyNames: Array.from(companyNameSet).sort((a, b) => a.localeCompare(b, "lt")),
    userNames: Array.from(userNameSet).sort((a, b) => a.localeCompare(b, "lt")),
  };
}

export default function Filters() {
  const { catalogueTree, filters, setFilters } = useCatalogueTree();

  const available = useMemo(
    () => collectMetadata(catalogueTree ?? []),
    [catalogueTree]
  );

  const companyOptions: SelectOption[] = useMemo(() => [
    { value: "all", label: "Visos įmonės" },
    ...available.companyNames.map((n) => ({ value: n, label: n })),
  ], [available.companyNames]);

  const userOptions: SelectOption[] = useMemo(() => [
    { value: "all", label: "Visi vartotojai" },
    ...available.userNames.map((n) => ({ value: n, label: n })),
  ], [available.userNames]);

  const showCompanyTypeFilter = available.hasTypes || available.hasCompanies;

  const hasAnyFilter =
    showCompanyTypeFilter ||
    available.hasCompanies ||
    available.hasMimeVariety ||
    available.hasLanguageVariety ||
    available.hasCreatedBy ||
    available.hasCreatedDate;

  return (
    <div className={styles.container}>
      <div className={styles.filters}>

        <h1>
          <ListFilter size={20} />
          Filtrai
        </h1>

        {showCompanyTypeFilter && (
          <div className={`${styles.filter} ${styles.sectionCard}`}>
            <h2>Įmonės tipas</h2>
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
        )}

        {available.hasCompanies && (
          <div className={`${styles.filter} ${styles.sectionCard}`}>
            <h2>Įmonė</h2>
            <InputFieldSelect
              options={companyOptions}
              selected={filters.companies[0] ?? "Visos įmonės"}
              onChange={(value) =>
                setFilters(prev => ({
                  ...prev,
                  companies: value === "all" ? [] : [value]
                }))
              }
            />
          </div>
        )}

        {available.hasMimeVariety && (
          <div className={`${styles.filter} ${styles.sectionCard}`}>
            <h2>Failo formatas</h2>
            <div className={styles.checkboxGroup}>
              {[
                {
                  value: "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                  label: "Word",
                },
                {
                  value: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                  label: "Excel",
                },
              ].map((mime) => (
                <div key={mime.value} className={styles.types}>
                  <CheckBox
                    value={filters.mimeTypes.includes(mime.value)}
                    onChange={(checked) =>
                      setFilters((prev) => ({
                        ...prev,
                        mimeTypes: toggleArrayValue(prev.mimeTypes, mime.value, checked),
                      }))
                    }
                  />
                  <span>{mime.label}</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {available.hasLanguageVariety && (
          <div className={`${styles.filter} ${styles.sectionCard}`}>
            <h2>Kalba</h2>
            <div className={styles.checkboxGroup}>
              {[
                { code: "LT", label: "Lietuviu" },
                { code: "RU", label: "Rusu" },
                { code: "EN", label: "Anglu" },
              ].map((lang) => (
                <div key={lang.code} className={styles.types}>
                  <CheckBox
                    value={filters.languages.includes(lang.code)}
                    onChange={(checked) =>
                      setFilters(prev => ({
                        ...prev,
                        languages: toggleArrayValue(prev.languages, lang.code, checked),
                      }))
                    }
                  />
                  <span>{lang.label}</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {available.hasCreatedBy && (
          <div className={`${styles.filter} ${styles.sectionCard}`}>
            <h2>Vartotojas</h2>
            <InputFieldSelect
              options={userOptions}
              selected={filters.createdBy[0] ?? "Visi vartotojai"}
              onChange={(value) =>
                setFilters(prev => ({
                  ...prev,
                  createdBy: value === "all" ? [] : [value]
                }))
              }
            />
          </div>
        )}

        {available.hasCreatedDate && (
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
        )}

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

        {!hasAnyFilter && (
          <div className={styles.helperText}>
            Failai neturi metaduomenų filtravimui.
          </div>
        )}

      </div>
    </div>
  );
}
