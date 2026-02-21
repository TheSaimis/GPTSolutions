export type TemplateList = {
    name: string;
    type: "file" | "directory";
    children?: TemplateList[];
};

export type Document = {
    company: string;
    code: string;
    role: string;
    instructionDate: string;
    directory: string;
};