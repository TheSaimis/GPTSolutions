// directoriesToSend.ts
import { create } from "zustand";

type DirectoryStoreState = {
  selected: string[];
  add: (path: string) => void;
  remove: (path: string) => void;
  clear: () => void;
  isSelected: (path: string) => boolean;
};

export const useDirectoryStore = create<DirectoryStoreState>((set, get) => ({
  selected: [],
  add: (path) =>
    set((s) => ({ selected: s.selected.includes(path) ? s.selected : [...s.selected, path] })),
  remove: (path) =>
    set((s) => ({ selected: s.selected.filter((p) => p !== path) })),
  clear: () => set({ selected: [] }),
  isSelected: (path) => get().selected.includes(path),
}));

export const DirectoryStore = {
  add: (path: string) => useDirectoryStore.getState().add(path),
  remove: (path: string) => useDirectoryStore.getState().remove(path),
  clear: () => useDirectoryStore.getState().clear(),
};