import { TemplateList } from "@/lib/types/TemplateList";

export function addFileToTree(
  nodes: TemplateList[],
  parentPath: string,
  fileNode: TemplateList,
): TemplateList[] {
  const normalizedFile: TemplateList = {
    ...fileNode,
    type: "file",
    children: undefined,
  };

  return nodes.map((node) => {
    if (node.type !== "directory") return node;

    if (node.path === parentPath) {
      const alreadyExists = node.children?.some(
        (child) => child.type === "file" && child.path === normalizedFile.path,
      );

      if (alreadyExists) return node;

      return {
        ...node,
        children: [...(node.children ?? []), normalizedFile],
      };
    }

    return {
      ...node,
      children: node.children
        ? addFileToTree(node.children, parentPath, normalizedFile)
        : [],
    };
  });
}