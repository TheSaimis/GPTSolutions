import { TemplateList } from "@/lib/types/TemplateList";

function replacePathPrefix(
  nodes: TemplateList[],
  oldPrefix: string,
  newPrefix: string,
): TemplateList[] {
  return nodes.map((node) => {
    const updatedPath = node.path?.startsWith(oldPrefix)
      ? node.path.replace(oldPrefix, newPrefix)
      : node.path;

    if (node.type === "directory") {
      return {
        ...node,
        path: updatedPath,
        children: node.children
          ? replacePathPrefix(node.children, oldPrefix, newPrefix)
          : [],
      };
    }

    return {
      ...node,
      path: updatedPath,
    };
  });
}

export function renameDirectoryInTree(
  nodes: TemplateList[],
  oldPath: string,
  newName: string,
): TemplateList[] {
  return nodes.map((node) => {
    if (node.type !== "directory") return node;

    if (node.path === oldPath) {
      const parentPath = oldPath.includes("/")
        ? oldPath.substring(0, oldPath.lastIndexOf("/"))
        : "";
      const newPath = parentPath ? `${parentPath}/${newName}` : newName;

      return {
        ...node,
        name: newName,
        path: newPath,
        children: node.children
          ? replacePathPrefix(node.children, oldPath, newPath)
          : [],
      };
    }

    return {
      ...node,
      children: node.children
        ? renameDirectoryInTree(node.children, oldPath, newName)
        : [],
    };
  });
}