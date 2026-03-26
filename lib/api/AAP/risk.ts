import { api } from "../api";
import type {
  RiskGroup,
  RiskCategory,
  RiskList,
  RiskSubcategory,
} from "@/lib/types/AAP/Risk";

import { getAAPCache, setAAPCache, clearAAPCache } from "@/lib/cache/AAP/cache";

export const RiskApi = {
  async getRiskCategories(): Promise<RiskCategory[]> {
    const key = "risk-categories";

    const cached = getAAPCache<RiskCategory[]>(key);
    if (cached) return cached;

    const data = await api.get<RiskCategory[]>("/api/risk-categories/");
    setAAPCache(key, data);

    return data;
  },

  async getRiskGroups(): Promise<RiskGroup[]> {
    const key = "risk-groups";

    const cached = getAAPCache<RiskGroup[]>(key);
    if (cached) return cached;

    const data = await api.get<RiskGroup[]>("/api/risk-groups/");
    setAAPCache(key, data);

    return data;
  },

  async getRiskSubcategories(): Promise<RiskSubcategory[]> {
    const key = "risk-subcategories";

    const cached = getAAPCache<RiskSubcategory[]>(key);
    if (cached) return cached;

    const data = await api.get<RiskSubcategory[]>("/api/risk-subcategories/");
    setAAPCache(key, data);

    return data;
  },

  async getRiskLists(): Promise<RiskList[]> {
    const key = "risk-lists";

    const cached = getAAPCache<RiskList[]>(key);
    if (cached) return cached;

    const data = await api.get<RiskList[]>("/api/risk-lists/");
    setAAPCache(key, data);

    return data;
  },

  clearCache(): void {
    clearAAPCache("risk-categories");
    clearAAPCache("risk-groups");
    clearAAPCache("risk-subcategories");
    clearAAPCache("risk-lists");
  },
};