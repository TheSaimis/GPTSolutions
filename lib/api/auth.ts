import { api } from "./api";
import { parseJwtToken } from "../functions/jwt";

type Token = { token: string };

export const AuthApi = {
    login: async (email: string, password: string): Promise<string> => {
      
        const res = await api.post<Token>("/api/login", { email, password }, {errorTitle: "Prisijungimas nesėkmingas", errorMessage: "Klaidingi prisijungimo duomenys arba serverio klaida.", loadingMessage: "Jungiamasi..." });
        const decoded = parseJwtToken(res.token);

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