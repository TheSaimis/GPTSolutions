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
    created?: string;
    createdBy?: string;
    userId?: string;
    type?: string;
    company?: string;
    companyId?: string;
    templateId?: string;
    documentId?: string;
    [key: string]: string | number | boolean | null | undefined;
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

export type TemplateList = {
    name: string;
    type: "file" | "directory";
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