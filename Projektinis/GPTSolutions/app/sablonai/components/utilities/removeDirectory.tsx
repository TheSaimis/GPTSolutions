import { TemplateList } from "@/lib/types/TemplateList";

export function removeDirectoryFromTree(
  nodes: TemplateList[],
  directoryPath: string
): TemplateList[] {
  return nodes
    .map((node) => {
      if (node.type === "directory") {
        return {
          ...node,
          children: node.children
            ? removeDirectoryFromTree(node.children, directoryPath)
            : [],
        };
      }

      return node;
    })
    .filter(
      (node) => !(node.type === "directory" && node.path === directoryPath)
    );
}