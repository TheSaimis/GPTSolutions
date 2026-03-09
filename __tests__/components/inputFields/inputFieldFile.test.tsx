import { render, screen, fireEvent } from "@testing-library/react";
import InputFieldFile from "@/components/inputFields/inputFieldFile";

describe("InputFieldFile", () => {
  it("renders with placeholder label", () => {
    render(
      <InputFieldFile value={null} placeholder="Pasirinkite failą" onChange={() => {}} />
    );
    expect(screen.getByText("Pasirinkite failą")).toBeInTheDocument();
  });

  it("renders file input", () => {
    render(
      <InputFieldFile value={null} placeholder="File" onChange={() => {}} />
    );
    const input = document.querySelector('input[type="file"]');
    expect(input).toBeInTheDocument();
  });

  it("shows file name when value is set", () => {
    const file = new File(["content"], "document.docx", {
      type: "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    });
    render(
      <InputFieldFile value={file} placeholder="File" onChange={() => {}} />
    );
    expect(screen.getByText("document.docx")).toBeInTheDocument();
  });

  it("calls onChange when file selected", () => {
    const handleChange = jest.fn();
    render(
      <InputFieldFile value={null} placeholder="File" onChange={handleChange} />
    );
    const input = document.querySelector('input[type="file"]')!;
    const file = new File(["x"], "test.txt", { type: "text/plain" });
    fireEvent.change(input, { target: { files: [file] } });
    expect(handleChange).toHaveBeenCalledWith(file);
  });

  it("accepts accept prop", () => {
    render(
      <InputFieldFile
        value={null}
        placeholder="File"
        onChange={() => {}}
        accept=".docx,.pdf"
      />
    );
    const input = document.querySelector('input[type="file"]');
    expect(input).toHaveAttribute("accept", ".docx,.pdf");
  });
});
