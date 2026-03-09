import { create } from "zustand";

export interface Message {
  id: string;
  title: string;
  message: string;
  backgroundColor?: string;
}

type MessageStore = {
  messages: Message[];
  push: (msg: Omit<Message, "id">) => void;
  remove: (id: string) => void;
  clear: () => void;
};

export const useMessageStore = create<MessageStore>((set) => ({
  messages: [],

  push: (msg) =>
    set((state) => ({
      messages: [
        ...state.messages,
        {
          id: crypto.randomUUID(),
          ...msg,
        },
      ],
    })),

  remove: (id) =>
    set((state) => ({
      messages: state.messages.filter((m) => m.id !== id),
    })),
  clear: () => set({ messages: [] }),
}));


export const MessageStore = {
  push: (msg: Omit<Message, "id">) =>
    useMessageStore.getState().push(msg),

  remove: (id: string) =>
    useMessageStore.getState().remove(id),

  clear: () =>
    useMessageStore.getState().clear(),
};