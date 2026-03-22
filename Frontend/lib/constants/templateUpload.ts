/** Word / Excel šablonų įkėlimas — sutampa su Backend (AddWordDocument, FileController). */
export const TEMPLATE_FILE_ACCEPT = ".doc,.docx,.xls,.xlsx";

const ALLOWED_EXTENSIONS = [".doc", ".docx", ".xls", ".xlsx"] as const;

export function isAllowedTemplateUpload(file: File | null | undefined): boolean {
  if (!file?.name) return false;
  const lower = file.name.toLowerCase();
  return ALLOWED_EXTENSIONS.some((ext) => lower.endsWith(ext));
}
