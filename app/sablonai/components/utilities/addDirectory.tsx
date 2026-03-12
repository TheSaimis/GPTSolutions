import { TemplateList } from "@/lib/types/TemplateList";

export function addDirectoryToTree(
  nodes: TemplateList[],
  parentPath: string,
  newDirectoryName: string,
): TemplateList[] {
  return nodes.map((node) => {
    if (node.type !== "directory") return node;

    if (node.path === parentPath) {
      const newPath = `${parentPath}/${newDirectoryName}`;

      const alreadyExists = node.children?.some(
        (child) => child.name === newDirectoryName && child.type === "directory",
      );

      if (alreadyExists) return node;

      return {
        ...node,
        children: [
          ...(node.children ?? []),
          {
            name: newDirectoryName,
            type: "directory",
            path: newPath,
            children: [],
          },
        ],
      };
    }

    return {
      ...node,
      children: node.children
        ? addDirectoryToTree(node.children, parentPath, newDirectoryName)
        : [],
    };
  });
}