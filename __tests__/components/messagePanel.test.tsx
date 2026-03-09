import { render, screen } from "@testing-library/react";
import MessagePanel from "@/components/messages/messagePanel";

jest.mock("@/lib/globalVariables/messages", () => ({
  useMessageStore: jest.fn((selector: (s: { messages: unknown[]; remove: () => void }) => unknown) =>
    selector({
      messages: [],
      remove: jest.fn(),
    })
  ),
}));

const mockUseMessageStore = require("@/lib/globalVariables/messages").useMessageStore;

describe("MessagePanel", () => {
  it("renders nothing when no messages", () => {
    mockUseMessageStore.mockImplementation(
      (selector: (s: { messages: unknown[]; remove: () => void }) => unknown) =>
        selector({ messages: [], remove: jest.fn() })
    );
    const { container } = render(<MessagePanel />);
    expect(container.querySelector("[style*='fixed']")).toBeInTheDocument();
    expect(screen.queryByText("Test")).not.toBeInTheDocument();
  });

  it("renders messages from store", () => {
    mockUseMessageStore.mockImplementation(
      (selector: (s: { messages: { id: string; title: string; message: string }[]; remove: () => void }) => unknown) =>
        selector({
          messages: [
            { id: "1", title: "Klaida", message: "Kažkas nutiko" },
          ],
          remove: jest.fn(),
        })
    );
    render(<MessagePanel />);
    expect(screen.getByText("Klaida")).toBeInTheDocument();
    expect(screen.getByText("Kažkas nutiko")).toBeInTheDocument();
  });
});
