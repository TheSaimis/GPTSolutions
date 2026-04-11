import { CustomVariable } from "./Company";

/**
 * Values written into OOXML custom properties by specialized generators (`documentData`, `templateType`, etc.).
 * Prefer setting these in the service that creates the document, not inferring from path alone.
 */
export const DOCUMENT_TYPES = {
    healthCertificate: "healthCertificate",
    standard: "standard",
    workerEquipment: "workerEquipment",
    aapTable: "aapTable",
} as const;

export type DocumentTypeId = (typeof DOCUMENT_TYPES)[keyof typeof DOCUMENT_TYPES];

type CoreMetadata = {
    title?: string | null;
    subject?: string | null;
    creator?: string | null;
    description?: string | null;
    lastModifiedBy?: string | null;
    revision?: string | null;
    created?: string | null;
    modified?: string | null;
    [key: string]: string | number | boolean | null | undefined;
};

type CustomMetadata = {
    mimeType?: string;
    linkUrl?: string;
    created?: string;
    createdBy?: string;
    userId?: string;
    type?: string;
    company?: string;
    companyId?: string;
    templateId?: string;
    documentId?: string;
    modifiedAt?: string;
    customVariables?: CustomVariable | undefined;
    documentData?: string;
    /** Pažyma / health-certificate flow: `DOCUMENT_TYPES.healthCertificate` in its own property (not inside `documentData`). */
    templateType?: DocumentTypeId | string;
    /** Resolved `.docx` path under `templates/` for replay (not inside `documentData`). */
    templatePath?: string;
    /** Coarse kind of generator / workflow; see `DOCUMENT_TYPES`. */
    documentType?: DocumentTypeId | string;
    [key: string]: string | number | boolean | null | undefined | CustomVariable;
};

export type TemplateId = {
    id: string;
    path: string;
};

export type Metadata = {
    core?: CoreMetadata;
    custom?: CustomMetadata;
};

export type CreateFileResponse = {
  status: "SUCCESS" | "FAIL";
  file?: TemplateList;
  error?: string;
};

/** POST /api/files/create — batch upload (always includes `results`; may be one item). */
export type CreateFilesResponse = {
  status: "SUCCESS" | "PARTIAL" | "FAIL";
  results: CreateFileResponse[];
};

/** POST /api/files/create-from-zip — ZIP su Word/Excel šablonais. */
export type ZipImportRow = {
  source?: string;
  status: "SUCCESS" | "FAIL";
  file?: TemplateList;
  error?: string;
};

export type CreateFromZipResponse = {
  status: "SUCCESS" | "PARTIAL" | "FAIL";
  results: ZipImportRow[];
  skipped?: { name: string; reason: string }[];
  error?: string;
};

export type TemplateList = {
    name: string;
    type: "file" | "directory";
    fileType?: string;
    path: string;
    size?: number;
    children?: TemplateList[];
    metadata?: Metadata;
    createdAt?: string;
    modifiedAt?: string;
};

export type Document = {
    company: string;
    code: string;
    role: string;
    instructionDate: string;
    directory: string;
};

export const FILE_TYPES = [
    ".doc",
    ".docx",
    ".xlsx",
    ".zip",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    "application/msword",
    "application/zip",
    "application/x-zip-compressed",
] as const;

export const FILE_TYPE_COLORS = {
    undefined: "#2563eb",
    // word
    ".docx": "#2563eb",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document": "#2563eb",

    // excel
    ".xlsx": "#16a34a",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": "#16a34a",

    // link
    "application/internet-shortcut": "#7c3aed",
};

export type AcceptedFileType = typeof FILE_TYPES[number];