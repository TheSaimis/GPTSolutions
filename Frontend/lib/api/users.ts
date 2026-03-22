import { api } from "./api";
import type { User } from "../types/User";

export const UsersApi = {
    getAll: () => api.get<User[]>("/api/users/all", { loadingMessage: "Kraunamos naudotojai..." }),
    getById: (id: number) => api.get<User>(`/api/users/${id}`),
    userCreate: (user: User, errorMessage?: string, errorTitle?: string) =>
        api.post<User>("/api/users/create", user),
    userUpdate: (id: number, user: Partial<Pick<User, "email" | "firstName" | "lastName" | "role">>) =>
        api.post<User>(`/api/users/${id}/update`, user),
};