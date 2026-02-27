import { api } from "./api";

type Token = { token: string };

export const AuthApi = {
    login: async (username: string, password: string): Promise<string> => {
      
        const res = await api.post<Token>("/api/login", { username, password });
        const decoded = parseJwt(res.token);

        localStorage.setItem("token", res.token);
        localStorage.setItem("username", decoded.username);
        localStorage.setItem("role", decoded.roles[0]);

        return res.token;
      },
}

function parseJwt(token: string) {
  const base64 = token.split(".")[1];
  const json = atob(base64);
  return JSON.parse(json);
}