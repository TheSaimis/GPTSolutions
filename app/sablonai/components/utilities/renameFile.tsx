import { TemplateList } from "@/lib/types/TemplateList";

export function renameFileInTree(
  nodes: TemplateList[],
  oldPath: string,
  newName: string,
): TemplateList[] {
  return nodes.map((node) => {
    if (node.type === "file" && node.path === oldPath) {
      const parentPath = oldPath.includes("/")
        ? oldPath.substring(0, oldPath.lastIndexOf("/"))
        : "";

      const newPath = parentPath ? `${parentPath}/${newName}` : newName;

      return {
        ...node,
        name: newName,
        path: newPath,
      };
    }

    if (node.type === "directory") {
      return {
        ...node,
        children: node.children
          ? renameFileInTree(node.children, oldPath, newName)
          : [],
      };
    }

    return node;
  });
}