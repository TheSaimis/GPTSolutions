import { api } from "./api";
import { TemplateList, Document } from "../types/TemplateList";

export const TemplateApi = {
    getAll: () => api.get<TemplateList[]>("/api/templates/all"),
    createDocument: (document: Document) => api.postBlob("/api/template/create", document),
}