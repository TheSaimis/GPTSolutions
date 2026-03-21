import { TemplateList } from "../types/TemplateList";

const catalogueTreeCache = new Map<string, TemplateList[]>();

export function getCachedCatalogueTree(key: string) {
  return catalogueTreeCache.get(key);
}

export function setCachedCatalogueTree(key: string, tree: TemplateList[]) {
  catalogueTreeCache.set(key, tree);
}

export function pushCachedCatalogueTree(key: string, tree: TemplateList[]) {
  const oldTree = getCachedCatalogueTree(key) ?? [];
  setCachedCatalogueTree(key, [...oldTree, ...tree]);
}

export function clearCachedCatalogueTree(key?: string) {
  if (key) {
    catalogueTreeCache.delete(key);
    return;
  }

  catalogueTreeCache.clear();
}