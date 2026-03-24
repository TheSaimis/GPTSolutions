import { TemplateList } from "../types/TemplateList";

const catalogueTreeCache = new Map<string, TemplateList[]>();

export function getCachedCatalogueTree(key: string) {
  return catalogueTreeCache.get(key);
}

export function setCachedCatalogueTree(key: string, tree: TemplateList[]) {
  catalogueTreeCache.set(key, tree);
}

export function clearTemplatesAndGeneratedCaches() {
  for (const key of catalogueTreeCache.keys()) {
    if (key.startsWith("templates") || key.startsWith("generated")) {
      catalogueTreeCache.delete(key);
    }
  }
}

export function clearDeletedCaches() {
  for (const key of catalogueTreeCache.keys()) {
    if (key.startsWith("deleted")) {
      catalogueTreeCache.delete(key);
    }
  }
}

export function clearCachedCatalogueTree(key?: string) {
  if (key) {
    catalogueTreeCache.delete(key);
    return;
  }
  catalogueTreeCache.clear();
}
