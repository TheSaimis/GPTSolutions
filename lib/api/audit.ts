import { api } from "./api";
import type { AuditLog } from "../types/AuditLog";

export const AuditApi = {
    list: async (limit: number, offset: number) => {
        const params = new URLSearchParams({
            limit: String(limit),
            offset: String(offset),
        });

        return api.get<AuditLog[]>(`/api/audit?${params.toString()}`, {
            loadingMessage: "Loading audit logs...",
        });
    },
};

