import { render, screen, fireEvent } from "@testing-library/react";
import DirectoryMenu from "@/app/sablonai/menus/directoryMenu/directoryMenu";

const mockPush = jest.fn();
jest.mock("next/navigation", () => ({
  useRouter: () => ({ push: mockPush }),
}));

jest.mock("@/lib/globalVariables/directoriesToSend", () => ({
  useDirectoryStore: jest.fn((selector: (s: { selected: string[] }) => unknown) =>
    selector({ selected: [] })
  ),
  DirectoryStore: { clear: jest.fn() },
}));

const mockUseDirectoryStore = require("@/lib/globalVariables/directoriesToSend").useDirectoryStore;
const mockClear = require("@/lib/globalVariables/directoriesToSend").DirectoryStore.clear;

describe("DirectoryMenu", () => {
  beforeEach(() => {
    mockPush.mockClear();
    mockClear.mockClear();
    mockUseDirectoryStore.mockImplementation(
      (selector: (s: { selected: string[] }) => unknown) =>
        selector({ selected: [] })
    );
  });

  it("renders Kurti dokumentus button disabled when no selection", () => {
    render(<DirectoryMenu />);
    const btn = screen.getByRole("button", { name: "Kurti dokumentus" });
    expect(btn).toBeDisabled();
  });

  it("renders Išvalyti pasirinkimą button", () => {
    render(<DirectoryMenu />);
    expect(
      screen.getByRole("button", { name: "Išvalyti pasirinkimą" })
    ).toBeInTheDocument();
  });

  it("Kurti dokumentus enabled when has selection", () => {
    mockUseDirectoryStore.mockImplementation(
      (selector: (s: { selected: string[] }) => unknown) =>
        selector({ selected: ["folder/doc.docx"] })
    );
    render(<DirectoryMenu />);
    expect(screen.getByRole("button", { name: "Kurti dokumentus" })).not.toBeDisabled();
  });

  it("navigates to createBulk when Kurti dokumentus clicked and has selection", () => {
    mockUseDirectoryStore.mockImplementation(
      (selector: (s: { selected: string[] }) => unknown) =>
        selector({ selected: ["a/b.docx"] })
    );
    render(<DirectoryMenu />);
    fireEvent.click(screen.getByRole("button", { name: "Kurti dokumentus" }));
    expect(mockPush).toHaveBeenCalledWith("/sablonai/createBulk");
  });

  it("calls DirectoryStore.clear when Išvalyti pasirinkimą clicked", () => {
    render(<DirectoryMenu />);
    fireEvent.click(screen.getByRole("button", { name: "Išvalyti pasirinkimą" }));
    expect(mockClear).toHaveBeenCalled();
  });
});
