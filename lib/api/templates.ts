import { api } from "./api";

export const TemplateApi = {
    getAll: () => api.get<string[]>("/templates")
}