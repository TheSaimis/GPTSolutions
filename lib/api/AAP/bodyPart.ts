import { api } from "../api";
import type { BodyPart, BodyPartCategory } from "@/lib/types/AAP/BodyPart";
import { getAAPCache, setAAPCache, clearAAPCache } from "@/lib/cache/AAP/cache";

export const bodyPartApi = {
  async getAllCategories(): Promise<BodyPartCategory[]> {
    const cacheKey = "body-part-categories";
    const cached = getAAPCache<BodyPartCategory[]>(cacheKey);
    if (cached) {
      return cached;
    }
    const data = await api.get<BodyPartCategory[]>("/api/body-part-categories");
    setAAPCache(cacheKey, data);
    return data;
  },

  async getAllParts(): Promise<BodyPart[]> {
    const cacheKey = "body-parts";
    const cached = getAAPCache<BodyPart[]>(cacheKey);
    if (cached) {
      return cached;
    }
    const data = await api.get<BodyPart[]>("/api/body-parts");
    setAAPCache(cacheKey, data);
    return data;
  },

  async createCategory(input: Pick<BodyPartCategory, "name" | "lineNumber">): Promise<BodyPartCategory> {
    const data = await api.post<BodyPartCategory>("/api/body-part-categories", input);
    clearAAPCache("body-part-categories");
    return data;
  },

  async updateCategory(
    id: number,
    input: Partial<Pick<BodyPartCategory, "name" | "lineNumber">>
  ): Promise<BodyPartCategory> {
    const data = await api.put<BodyPartCategory>(`/api/body-part-categories/${id}`, input);
    clearAAPCache("body-part-categories");
    return data;
  },

  async deleteCategory(id: number): Promise<{ message: string }> {
    const data = await api.delete<{ message: string }>(`/api/body-part-categories/${id}`);
    clearAAPCache("body-part-categories");
    clearAAPCache("body-parts");
    return data;
  },

  async createPart(input: {
    name: string;
    lineNumber: number;
    categoryId: number;
  }): Promise<BodyPart> {
    const data = await api.post<BodyPart>("/api/body-parts", input);
    clearAAPCache("body-parts");
    return data;
  },

  async updatePart(
    id: number,
    input: Partial<{ name: string; lineNumber: number; categoryId: number }>
  ): Promise<BodyPart> {
    const data = await api.put<BodyPart>(`/api/body-parts/${id}`, input);
    clearAAPCache("body-parts");
    return data;
  },

  async deletePart(id: number): Promise<{ message: string }> {
    const data = await api.delete<{ message: string }>(`/api/body-parts/${id}`);
    clearAAPCache("body-parts");
    return data;
  },

  clearCache(): void {
    clearAAPCache("body-part-categories");
    clearAAPCache("body-parts");
  },
};