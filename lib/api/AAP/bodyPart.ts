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


  clearCache(): void {
    clearAAPCache("body-part-categories");
    clearAAPCache("body-parts");
  },
};