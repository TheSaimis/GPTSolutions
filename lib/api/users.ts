import { api } from "./api";
import type { User } from "../types/User";

export const UsersApi = {

    getAll: () => api.get<User[]>("/users/all", { loadingMessage: "Kraunamos įmonės..." }),
    userCreate: (user: User, errorMessage?: string, errorTitle?: string) => api.post<User>("/users/create", user),

};