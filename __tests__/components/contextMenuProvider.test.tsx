import { render, screen, fireEvent } from "@testing-library/react";
import {
  ContextMenuProvider,
  useContextMenu,
} from "@/components/contextMenu/menuComponents/contextMenuProvider";

function TestConsumer() {
  const { openMenu, closeMenu, menu } = useContextMenu();
  return (
    <div onClick={(e) => e.stopPropagation()}>
      <button onClick={() => openMenu({ x: 10, y: 20, items: [{ id: "a", label: "Action" }] })}>
        Open
      </button>
      <button onClick={closeMenu}>Close</button>
      {menu.open ? <span data-testid="menu-open">Open</span> : null}
      {menu.items.map((i) => (
        <span key={i.id}>{i.label}</span>
      ))}
    </div>
  );
}

describe("ContextMenuProvider", () => {
  it("provides context to children", () => {
    render(
      <ContextMenuProvider>
        <TestConsumer />
      </ContextMenuProvider>
    );
    expect(screen.getByText("Open")).toBeInTheDocument();
  });

  it("opens menu with openMenu when click does not bubble to window", () => {
    render(
      <ContextMenuProvider>
        <TestConsumer />
      </ContextMenuProvider>
    );
    fireEvent.click(screen.getByText("Open"));
    expect(screen.getByTestId("menu-open")).toBeInTheDocument();
    expect(screen.getByText("Action")).toBeInTheDocument();
  });

  it("closes menu on closeMenu", () => {
    render(
      <ContextMenuProvider>
        <TestConsumer />
      </ContextMenuProvider>
    );
    fireEvent.click(screen.getByText("Open"));
    expect(screen.getByText("Action")).toBeInTheDocument();
    fireEvent.click(screen.getByText("Close"));
    expect(screen.queryByText("Action")).not.toBeInTheDocument();
  });

  it("throws when useContextMenu used outside provider", () => {
    const ConsoleSpy = jest.spyOn(console, "error").mockImplementation(() => {});
    expect(() => render(<TestConsumer />)).toThrow(
      "useContextMenu must be used inside ContextMenuProvider"
    );
    ConsoleSpy.mockRestore();
  });
});
