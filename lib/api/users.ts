import { api } from "./api";
import type { User } from "../types/User";

export const UsersApi = {
    getAll: () => api.get<User[]>("/users/all", { loadingMessage: "Kraunamos naudotojai..." }),
    getById: (id: number) => api.get<User>(`/users/${id}`),
    userCreate: (user: User, errorMessage?: string, errorTitle?: string) => api.post<User>("/users/create", user),
    userUpdate: (id: number, user: Partial<Pick<User, "email" | "firstName" | "lastName" | "role">>) =>
        api.post<User>(`/users/${id}/update`, user),
};