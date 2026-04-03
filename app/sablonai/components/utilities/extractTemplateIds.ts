import type { TemplateList } from "@/lib/types/TemplateList";

type TemplateLikeNode = {
  children?: TemplateLikeNode[];
  metadata?: {
    custom?: {
      templateId?: string;
      documentId?: string;
    };
  };
};

function normalizeToArray(
  input: TemplateList | TemplateList[] | TemplateLikeNode | TemplateLikeNode[] | null | undefined
): TemplateLikeNode[] {
  if (!input) return [];
  return Array.isArray(input) ? (input as TemplateLikeNode[]) : [input as TemplateLikeNode];
}

export function extractTemplateIds(
  input: TemplateList | TemplateList[] | TemplateLikeNode | TemplateLikeNode[] | null | undefined
): string[] {
  const roots = normalizeToArray(input);
  const found = new Set<string>();

  function visit(node: TemplateLikeNode): void {
    const templateId = (node.metadata?.custom?.templateId ?? "").trim();
    const documentId = (node.metadata?.custom?.documentId ?? "").trim();
    const id = templateId || documentId;

    if (id !== "") {
      found.add(id);
    }

    if (Array.isArray(node.children)) {
      node.children.forEach(visit);
    }
  }

  roots.forEach(visit);
  return Array.from(found);
}

export default extractTemplateIds;
