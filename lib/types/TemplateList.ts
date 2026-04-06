import { CustomVariable } from "./Company";

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
    ".docx",
    ".xlsx",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
] as const;

export const FILE_TYPE_COLORS = {
    undefined: "#2563eb",
    // word
    ".docx": "#2563eb",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document": "#2563eb",

    // excel
    ".xlsx": "#16a34a",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": "#16a34a",
};

export type AcceptedFileType = typeof FILE_TYPES[number];