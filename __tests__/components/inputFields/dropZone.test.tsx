import { render, screen, fireEvent } from "@testing-library/react";
import DropZone from "@/components/inputFields/dropZone";

jest.mock("@/lib/globalVariables/messages", () => ({
  MessageStore: { push: jest.fn() },
}));

describe("DropZone", () => {
  it("renders children", () => {
    render(
      <DropZone onFile={() => {}}>
        <span>Drop files here</span>
      </DropZone>
    );
    expect(screen.getByText("Drop files here")).toBeInTheDocument();
  });

  it("calls onFile when valid file dropped", () => {
    const onFile = jest.fn();
    render(
      <DropZone onFile={onFile}>
        <span>Drop</span>
      </DropZone>
    );
    const container = screen.getByText("Drop").closest("div")!;
    const file = new File(["content"], "doc.pdf", { type: "application/pdf" });
    const dataTransfer = { files: [file] };
    fireEvent.drop(container, { dataTransfer });
    expect(onFile).toHaveBeenCalledWith(file);
  });

  it("accepts only specified extensions when accept prop set", () => {
    const { MessageStore } = require("@/lib/globalVariables/messages");
    const onFile = jest.fn();
    render(
      <DropZone onFile={onFile} accept=".pdf,.docx">
        <span>Drop</span>
      </DropZone>
    );
    const container = screen.getByText("Drop").closest("div")!;
    const file = new File(["x"], "image.png", { type: "image/png" });
    fireEvent.drop(container, { dataTransfer: { files: [file] } });
    expect(onFile).not.toHaveBeenCalled();
    expect(MessageStore.push).toHaveBeenCalled();
  });

  it("applies dragOver class when dragging over", () => {
    render(
      <DropZone onFile={() => {}}>
        <span>Drop</span>
      </DropZone>
    );
    const container = screen.getByText("Drop").closest("div")!;
    fireEvent.dragOver(container, { preventDefault: () => {} });
    expect(container.className).toMatch(/dragOver/);
  });

  it("does not call onFile when disabled and file dropped", () => {
    const onFile = jest.fn();
    render(
      <DropZone onFile={onFile} disabled>
        <span>Drop</span>
      </DropZone>
    );
    const container = screen.getByText("Drop").closest("div")!;
    const file = new File(["x"], "a.pdf", { type: "application/pdf" });
    fireEvent.drop(container, { dataTransfer: { files: [file] } });
    expect(onFile).not.toHaveBeenCalled();
  });
});
