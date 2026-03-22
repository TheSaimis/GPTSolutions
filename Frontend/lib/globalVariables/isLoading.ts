import { create } from "zustand";

type LoadingStore = {
  loading: boolean;
  message: string | null;
  setLoading: (loading: boolean, message?: string) => void;
  clear: () => void;
};

export const useLoadingStore = create<LoadingStore>((set) => ({
  loading: false,
  message: null,

  setLoading: (loading, message) =>
    set({
      loading,
      message: message ?? null,
    }),

  clear: () =>
    set({
      loading: false,
      message: null,
    }),
}));

export const LoadingStore = {
  set: (loading: boolean, message?: string) =>
    useLoadingStore.getState().setLoading(loading, message),

  clear: () =>
    useLoadingStore.getState().clear(),
};