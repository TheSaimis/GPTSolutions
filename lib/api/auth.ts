import { api } from "./api";

type Token = { token: string };

export const AuthApi = {
    login: async (username: string, password: string): Promise<string> => {
        const res = await api.post<Token>("/api/login", { username, password });
        localStorage.setItem("token", res.token);
        return res.token;
      },
}