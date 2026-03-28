import { api } from "../api";
import type {
  RiskGroup,
  RiskCategory,
  RiskList,
  RiskSubcategory,
} from "@/lib/types/AAP/Risk";

import { clearAAPCache } from "@/lib/cache/AAP/cache";

export const RiskApi = {
  async getRiskCategories(): Promise<RiskCategory[]> {
    return api.get<RiskCategory[]>("/api/risk-categories/");
  },

  async getRiskGroups(): Promise<RiskGroup[]> {
    return api.get<RiskGroup[]>("/api/risk-groups/");
  },

  async getRiskSubcategories(): Promise<RiskSubcategory[]> {
    return api.get<RiskSubcategory[]>("/api/risk-subcategories/");
  },

  async getRiskLists(): Promise<RiskList[]> {
    return api.get<RiskList[]>("/api/risk-lists/");
  },

  async createRiskGroup(input: Pick<RiskGroup, "name" | "lineNumber">): Promise<RiskGroup> {
    const data = await api.post<RiskGroup>("/api/risk-groups", input);
    clearAAPCache("risk-groups");
    return data;
  },

  async updateRiskGroup(
    id: number,
    input: Partial<Pick<RiskGroup, "name" | "lineNumber">>
  ): Promise<RiskGroup> {
    const data = await api.put<RiskGroup>(`/api/risk-groups/${id}`, input);
    clearAAPCache("risk-groups");
    return data;
  },

  async deleteRiskGroup(id: number): Promise<{ message: string }> {
    const data = await api.delete<{ message: string }>(`/api/risk-groups/${id}`);
    clearAAPCache();
    return data;
  },

  async createRiskCategory(input: {
    name: string;
    lineNumber: number;
    groupId: number;
  }): Promise<RiskCategory> {
    const data = await api.post<RiskCategory>("/api/risk-categories", input);
    clearAAPCache("risk-categories");
    return data;
  },

  async updateRiskCategory(
    id: number,
    input: Partial<{ name: string; lineNumber: number; groupId: number }>
  ): Promise<RiskCategory> {
    const data = await api.put<RiskCategory>(`/api/risk-categories/${id}`, input);
    clearAAPCache("risk-categories");
    return data;
  },

  async deleteRiskCategory(id: number): Promise<{ message: string }> {
    const data = await api.delete<{ message: string }>(`/api/risk-categories/${id}`);
    clearAAPCache();
    return data;
  },

  async createRiskSubcategory(input: {
    name: string;
    lineNumber: number;
    categoryId?: number | null;
    groupId?: number | null;
  }): Promise<RiskSubcategory> {
    const data = await api.post<RiskSubcategory>("/api/risk-subcategories", input);
    clearAAPCache("risk-subcategories");
    return data;
  },

  async updateRiskSubcategory(
    id: number,
    input: Partial<{ name: string; lineNumber: number; categoryId: number | null; groupId: number | null }>
  ): Promise<RiskSubcategory> {
    const data = await api.put<RiskSubcategory>(`/api/risk-subcategories/${id}`, input);
    clearAAPCache("risk-subcategories");
    return data;
  },

  async deleteRiskSubcategory(id: number): Promise<{ message: string }> {
    const data = await api.delete<{ message: string }>(`/api/risk-subcategories/${id}`);
    clearAAPCache();
    return data;
  },

  async createRiskList(input: {
    bodyPartId: number;
    riskSubcategoryId: number;
    workerId: number;
  }): Promise<RiskList> {
    const data = await api.post<RiskList>("/api/risk-lists", input);
    clearAAPCache("risk-lists");
    return data;
  },

  async deleteRiskList(id: number): Promise<{ message: string }> {
    const data = await api.delete<{ message: string }>(`/api/risk-lists/${id}`);
    clearAAPCache("risk-lists");
    return data;
  },

  clearCache(): void {
    clearAAPCache("risk-categories");
    clearAAPCache("risk-groups");
    clearAAPCache("risk-subcategories");
    clearAAPCache("risk-lists");
  },
};