import { TemplateList } from "@/lib/types/TemplateList";

export function removeFileFromTree(
  nodes: TemplateList[],
  filePath: string
): TemplateList[] {
  return nodes
    .map((node) => {
      if (node.type === "directory") {
        return {
          ...node,
          children: node.children
            ? removeFileFromTree(node.children, filePath)
            : [],
        };
      }

      return node;
    })
    .filter((node) => !(node.type === "file" && node.path === filePath));
}