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
    [key: string]: string | number | boolean | null | undefined;
};

export type Metadata = {
    core?: CoreMetadata;
    custom?: CustomMetadata;
};

export type TemplateList = {
    metadata: Metadata | undefined;
    name: string;
    type: "file" | "directory";
    createdAt?: string;
    modifiedAt?: string;
    children?: TemplateList[];
};

export type Document = {
    company: string;
    code: string;
    role: string;
    instructionDate: string;
    directory: string;
};