import { TemplateList } from "@/lib/types/TemplateList";

function normalizeTreePath(p: string): string {
  return p.replace(/\\/g, "/").replace(/^\/+|\/+$/g, "");
}

/**
 * Insert a file using its full `path` (e.g. `A/B/doc.docx`), creating directory nodes as needed.
 * Use for ZIP imports; {@link addFileToTree} only attaches under a single existing folder.
 */
export function addFileToTreeByFullPath(
  nodes: TemplateList[],
  fileNode: TemplateList,
): TemplateList[] {
  const fullPath = normalizeTreePath(fileNode.path);
  if (!fullPath) return nodes;

  const parts = fullPath.split("/").filter(Boolean);
  if (parts.length === 0) return nodes;

  const dirParts = parts.slice(0, -1);

  const normalizedFile: TemplateList = {
    ...fileNode,
    type: "file",
    path: fullPath,
    children: undefined,
  };

  function addAtDepth(children: TemplateList[], depth: number): TemplateList[] {
    if (depth === dirParts.length) {
      const exists = children.some(
        (n) =>
          n.type === "file" && normalizeTreePath(n.path) === fullPath,
      );
      if (exists) return children;
      return [...children, normalizedFile];
    }

    const segment = dirParts[depth];
    const dirPath = dirParts.slice(0, depth + 1).join("/");

    const idx = children.findIndex(
      (n) =>
        n.type === "directory" && normalizeTreePath(n.path) === dirPath,
    );

    if (idx >= 0) {
      const dir = children[idx];
      if (dir.type !== "directory") return children;
      const nextChildren = addAtDepth(dir.children ?? [], depth + 1);
      return children.map((n, i) =>
        i === idx ? { ...dir, children: nextChildren } : n,
      );
    }

    const newDir: TemplateList = {
      name: segment,
      type: "directory",
      path: dirPath,
      children: addAtDepth([], depth + 1),
    };
    return [...children, newDir];
  }

  return addAtDepth(nodes, 0);
}

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

  if (parentPath === "") {
    const alreadyExists = nodes.some(
      (node) => node.type === "file" && node.path === normalizedFile.path,
    );

    if (alreadyExists) return nodes;

    return [...nodes, normalizedFile];
  }

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