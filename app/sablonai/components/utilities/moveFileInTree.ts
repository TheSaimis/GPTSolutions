import type { TemplateList } from "@/lib/types/TemplateList";
import { removeFileFromTree } from "@/app/sablonai/components/utilities/deleteFile";

/** Custom MIME for internal catalogue file drag (see {@link DropZone}). */
export const TEMPLATE_FILE_DRAG_MIME = "application/x-lpsk-template-file";

export type TemplateFileDragPayload = {
  path: string;
  fileType: string;
};

function findFileNode(nodes: TemplateList[], filePath: string): TemplateList | null {
  for (const node of nodes) {
    if (node.type === "file" && node.path === filePath) {
      return node;
    }
    if (node.type === "directory" && node.children?.length) {
      const found = findFileNode(node.children, filePath);
      if (found) {
        return found;
      }
    }
  }
  return null;
}

function normalizeDirPath(dir: string): string {
  return dir.replace(/^\/+|\/+$/g, "");
}

function insertFileUnderDirectory(
  nodes: TemplateList[],
  targetDirPath: string,
  file: TemplateList,
): TemplateList[] {
  const normalized = normalizeDirPath(targetDirPath);

  if (normalized === "") {
    return [...nodes, file];
  }

  return nodes.map((node) => {
    if (node.type === "directory" && normalizeDirPath(node.path ?? "") === normalized) {
      return {
        ...node,
        children: [...(node.children ?? []), file],
      };
    }
    if (node.type === "directory" && node.children?.length) {
      return {
        ...node,
        children: insertFileUnderDirectory(node.children, targetDirPath, file),
      };
    }
    return node;
  });
}

/**
 * Moves a file node to another folder in the in-memory catalogue tree (same shape as backend
 * {@link FilesApi.changeDirectory}: filename unchanged, `newDirectoryPath` is the folder path under the root).
 */
export function moveFileInTree(
  nodes: TemplateList[],
  oldPath: string,
  newDirectoryPath: string,
): TemplateList[] {
  const fileNode = findFileNode(nodes, oldPath);
  if (!fileNode || fileNode.type !== "file") {
    return nodes;
  }

  const normalizedNewDir = normalizeDirPath(newDirectoryPath);
  const oldParent = oldPath.includes("/")
    ? oldPath.slice(0, oldPath.lastIndexOf("/"))
    : "";
  if (normalizeDirPath(oldParent) === normalizedNewDir) {
    return nodes;
  }

  const fileName = fileNode.name;
  const newPath = normalizedNewDir ? `${normalizedNewDir}/${fileName}` : fileName;

  const without = removeFileFromTree(nodes, oldPath);
  const moved: TemplateList = {
    ...fileNode,
    path: newPath,
    name: fileName,
  };

  return insertFileUnderDirectory(without, newDirectoryPath, moved);
}
