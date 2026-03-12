import { TemplateList } from "@/lib/types/TemplateList";

export function addFileToTree(
  nodes: TemplateList[],
  parentPath: string,
  fileName: string,
): TemplateList[] {
  return nodes.map((node) => {
    if (node.type !== "directory") return node;

    if (node.path === parentPath) {
      const newPath = `${parentPath}/${fileName}`;

      const alreadyExists = node.children?.some(
        (child) => child.type === "file" && child.name === fileName,
      );

      if (alreadyExists) return node;

      return {
        ...node,
        children: [
          ...(node.children ?? []),
          {
            name: fileName,
            type: "file",
            path: newPath,
            metadata: undefined,
          },
        ],
      };
    }

    return {
      ...node,
      children: node.children
        ? addFileToTree(node.children, parentPath, fileName)
        : [],
    };
  });
}