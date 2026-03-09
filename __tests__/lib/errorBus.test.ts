import { useMessageStore, MessageStore } from "@/lib/globalVariables/messages";

describe("Message store (Zustand)", () => {
  beforeEach(() => {
    useMessageStore.setState({ messages: [] });
  });

  it("starts with empty messages", () => {
    expect(useMessageStore.getState().messages).toEqual([]);
  });

  it("pushes a message with auto-generated id", () => {
    MessageStore.push({ title: "Klaida", message: "Kažkas nutiko" });

    const messages = useMessageStore.getState().messages;
    expect(messages).toHaveLength(1);
    expect(messages[0].title).toBe("Klaida");
    expect(messages[0].message).toBe("Kažkas nutiko");
    expect(messages[0].id).toBeDefined();
  });

  it("pushes multiple messages", () => {
    MessageStore.push({ title: "A", message: "First" });
    MessageStore.push({ title: "B", message: "Second" });

    const messages = useMessageStore.getState().messages;
    expect(messages).toHaveLength(2);
    expect(messages[0].title).toBe("A");
    expect(messages[1].title).toBe("B");
  });

  it("removes a message by id", () => {
    MessageStore.push({ title: "Remove me", message: "Bye" });

    const id = useMessageStore.getState().messages[0].id;
    MessageStore.remove(id);

    expect(useMessageStore.getState().messages).toHaveLength(0);
  });

  it("only removes the targeted message", () => {
    MessageStore.push({ title: "Keep", message: "Stay" });
    MessageStore.push({ title: "Remove", message: "Go" });

    const messages = useMessageStore.getState().messages;
    const removeId = messages[1].id;
    MessageStore.remove(removeId);

    const remaining = useMessageStore.getState().messages;
    expect(remaining).toHaveLength(1);
    expect(remaining[0].title).toBe("Keep");
  });

  it("clears all messages", () => {
    MessageStore.push({ title: "A", message: "1" });
    MessageStore.push({ title: "B", message: "2" });

    MessageStore.clear();
    expect(useMessageStore.getState().messages).toHaveLength(0);
  });

  it("supports optional backgroundColor", () => {
    MessageStore.push({ title: "Info", message: "OK", backgroundColor: "#38a169" });

    const msg = useMessageStore.getState().messages[0];
    expect(msg.backgroundColor).toBe("#38a169");
  });
});
