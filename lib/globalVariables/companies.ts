import { create } from "zustand";
import type { Company } from "../types/Company";
import { CompanyApi } from "../api/companies";

type CompanyStore = {
    companies: Company[];
    wasSet: boolean;
    push: (cmp: Omit<Company, "id">) => void;
    set: (companies: Company[]) => void;
    remove: (id: number) => void;
    clear: () => void;
};

export const useCompanyStore = create<CompanyStore>((set) => ({

    companies: [],
    wasSet: false,

    set: (companies) => set({ companies, wasSet: true }),

    push: (cmp) =>
        set((state) => ({
            companies: [
                ...state.companies,
                {
                    ...cmp,
                },
            ],
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

    remove: (id: number) =>
        useCompanyStore.getState().remove(id),

    clear: () =>
        useCompanyStore.getState().clear(),

};