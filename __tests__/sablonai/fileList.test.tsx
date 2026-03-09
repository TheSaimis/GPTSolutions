import { render, screen } from "@testing-library/react";
import FileList from "@/app/sablonai/templateList/fileList";

jest.mock("@/app/sablonai/templateList/types/file/file", () => ({
  __esModule: true,
  default: ({ name }: { name: string }) => <div data-testid="file">File: {name}</div>,
}));
jest.mock("@/app/sablonai/templateList/types/directory/directory", () => ({
  __esModule: true,
  default: ({ name }: { name: string }) => <div data-testid="directory">Dir: {name}</div>,
}));

describe("FileList", () => {
  it("renders file type as Files component", () => {
    render(
      <FileList name="sutartis.docx" type="file" directory="" />
    );
    expect(screen.getByTestId("file")).toHaveTextContent("File: sutartis.docx");
  });

  it("renders directory type as Directory component", () => {
    render(
      <FileList
        name="Contracts"
        type="directory"
        directory="Contracts"
        children={[]}
      />
    );
    expect(screen.getByTestId("directory")).toHaveTextContent("Dir: Contracts");
  });
});
