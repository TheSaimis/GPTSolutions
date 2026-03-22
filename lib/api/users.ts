import { api } from "./api";
import type { User } from "../types/User";

export const UsersApi = {
    getAll: () => api.get<User[]>("/users/all", { loadingMessage: "Kraunamos naudotojai..." }),
    getById: (id: number) => api.get<User>(`/users/${id}`),
    userCreate: (user: User, errorMessage?: string, errorTitle?: string) => api.post<User>("/users/create", user),
    userUpdate: (id: number, user: Partial<Pick<User, "email" | "firstName" | "lastName" | "role" | "password">>) =>
        api.post<User>(`/users/${id}/update`, user),
    userRestore: (id: number) => api.post(`/api/restore/${id}/user`),
    userDelete: (id: number) => api.delete(`/api/delete/${id}/user`),
};