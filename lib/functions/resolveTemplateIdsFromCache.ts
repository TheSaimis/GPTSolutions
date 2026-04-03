import { getCachedCatalogueTree } from "../cache/catalogueTreeCache";
import type { TemplateId, TemplateList } from "../types/TemplateList";

const TEMPLATE_CACHE_KEY = "templates";

export function collectTemplateIdsFromNode(
  node: TemplateList,
  requestedIds: Set<string>,
  resultById: Map<string, TemplateId>
) {
  const metadataId = (node.metadata?.custom?.templateId ?? node.metadata?.custom?.documentId ?? "").trim();
  if (metadataId !== "" && requestedIds.has(metadataId) && !resultById.has(metadataId)) {
    resultById.set(metadataId, { id: metadataId, path: node.path });
  }

  if (Array.isArray(node.children)) {
    node.children.forEach((child) => collectTemplateIdsFromNode(child, requestedIds, resultById));
  }
}

export function resolveTemplateIdsFromCache(ids: string[]): TemplateId[] {
  const requestedIds = new Set(ids);
  const resultById = new Map<string, TemplateId>();

  const tree = getCachedCatalogueTree(TEMPLATE_CACHE_KEY);
  if (tree) {
    tree.forEach((node) => collectTemplateIdsFromNode(node, requestedIds, resultById));
  }

  return ids
    .map((id) => resultById.get(id))
    .filter((entry): entry is TemplateId => Boolean(entry));
}
