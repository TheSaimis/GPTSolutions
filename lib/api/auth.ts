import { api } from "./api";

type Token = { token: string };

export const AuthApi = {
    login: async (email: string, password: string): Promise<string> => {
      
        const res = await api.post<Token>("/api/login", { email, password });
        const decoded = parseJwt(res.token);

        localStorage.setItem("token", res.token);
        localStorage.setItem("name", decoded.firstName);
        localStorage.setItem("lastName", decoded.lastName);
        localStorage.setItem("email", decoded.email);
        localStorage.setItem("role", decoded.roles[0]);

        return res.token;
      },
}

function parseJwt(token: string) {
  const base64 = token.split(".")[1];
  const json = atob(base64);
  return JSON.parse(json);
}