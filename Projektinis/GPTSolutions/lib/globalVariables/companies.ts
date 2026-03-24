import { create } from "zustand";
import type { Company } from "../types/Company";
import { CompanyApi } from "../api/companies";

type CompanyStore = {
    companies: Company[];
    wasSet: boolean;
    push: (cmp: Omit<Company, "id">) => void;
    set: (companies: Company[]) => void;
    update: (id: number, cmp: Company) => void;
    remove: (id: number) => void;
    clear: () => void;
};

export const useCompanyStore = create<CompanyStore>((set) => ({

    companies: [],
    wasSet: false,

    set: (companies) => set({ companies, wasSet: true }),

    push: (company: Company) =>
        set((state) => {
            const exists = state.companies.some((c) => c.id === company.id);
            if (exists) {
                return {
                    companies: state.companies.map((c) =>
                        c.id === company.id ? { ...c, ...company } : c
                    ),
                };
            }
            return {
                companies: [...state.companies, company],
            };
        }),

    update: (id, cmp) =>
        set((state) => ({
            companies: state.companies.map((m) => (m.id === id ? { ...m, ...cmp, id } : m)),
        })),

    remove: (id) =>
        set((state) => ({
            companies: state.companies.filter((m) => m.id !== id),
        })),

    clear: () =>
        set({
            companies: [],
            wasSet: false
        }),
}));


export const CompanyStore = {
    push: (cmp: Omit<Company, "id">) =>
        useCompanyStore.getState().push(cmp),

    set: (companies: Company[]) =>
        useCompanyStore.getState().set(companies),

    update: (id: number, cmp: Company) =>
        useCompanyStore.getState().update(id, cmp),

    remove: (id: number) =>
        useCompanyStore.getState().remove(id),

    clear: () =>
        useCompanyStore.getState().clear(),

};