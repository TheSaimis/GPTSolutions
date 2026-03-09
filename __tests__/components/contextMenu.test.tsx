import { render, screen, fireEvent } from "@testing-library/react";
import ContextMenu from "@/components/contextMenu/contextMenu";

const mockCloseMenu = jest.fn();
const mockMenu = {
  open: true,
  x: 100,
  y: 100,
  items: [
    { id: "1", label: "New folder", onClick: jest.fn() },
    { id: "2", label: "Rename", onClick: jest.fn(), disabled: false },
    { id: "3", label: "Delete", disabled: true },
  ],
};

jest.mock("@/components/contextMenu/menuComponents/contextMenuProvider", () => ({
  useContextMenu: () => ({ menu: mockMenu, closeMenu: mockCloseMenu }),
}));

describe("ContextMenu", () => {
  beforeEach(() => {
    mockCloseMenu.mockClear();
    mockMenu.items.forEach((i) => i.onClick && (i.onClick as jest.Mock).mockClear());
  });

  it("renders menu items when open", () => {
    render(<ContextMenu />);
    expect(screen.getByText("New folder")).toBeInTheDocument();
    expect(screen.getByText("Rename")).toBeInTheDocument();
    expect(screen.getByText("Delete")).toBeInTheDocument();
  });

  it("calls onClick when item clicked", () => {
    render(<ContextMenu />);
    fireEvent.click(screen.getByText("New folder"));
    expect(mockMenu.items[0].onClick).toHaveBeenCalled();
    expect(mockCloseMenu).toHaveBeenCalled();
  });

  it("disables disabled item", () => {
    render(<ContextMenu />);
    const deleteBtn = screen.getByText("Delete").closest("button");
    expect(deleteBtn).toBeDisabled();
  });
});
