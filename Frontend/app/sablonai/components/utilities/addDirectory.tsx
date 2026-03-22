import { TemplateList } from "@/lib/types/TemplateList";

export function addDirectoryToTree(
  nodes: TemplateList[],
  parentPath: string,
  newDirectoryName: string,
  fileType?: string,
): TemplateList[] {
  if (parentPath === "") {
    const alreadyExists = nodes.some(
      (node) => node.type === "directory" && node.name === newDirectoryName,
    );

    if (alreadyExists) return nodes;

    return [
      ...nodes,
      {
        name: newDirectoryName,
        fileType,
        type: "directory",
        path: newDirectoryName,
        children: [],
      },
    ];
  }

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
            fileType,
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
        ? addDirectoryToTree(node.children, parentPath, newDirectoryName, fileType)
        : [],
    };
  });
}