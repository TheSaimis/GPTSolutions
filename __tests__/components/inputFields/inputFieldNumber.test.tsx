import { render, screen, fireEvent } from "@testing-library/react";
import InputFieldNumber from "@/components/inputFields/inputFieldNumber";

describe("InputFieldNumber", () => {
  it("renders with placeholder label", () => {
    render(
      <InputFieldNumber value="" placeholder="Kodas" onChange={() => {}} />
    );
    expect(screen.getByText("Kodas")).toBeInTheDocument();
  });

  it("renders input with correct value", () => {
    render(
      <InputFieldNumber
        value="12345"
        placeholder="Kodas"
        onChange={() => {}}
      />
    );
    const input = screen.getByPlaceholderText("Kodas") as HTMLInputElement;
    expect(input.value).toBe("12345");
  });

  it("calls onChange with valid input", () => {
    const handleChange = jest.fn();
    render(
      <InputFieldNumber value="" placeholder="Kodas" onChange={handleChange} />
    );
    const input = screen.getByPlaceholderText("Kodas");
    fireEvent.change(input, { target: { value: "123" } });
    expect(handleChange).toHaveBeenCalledWith("123");
  });

  it("allows empty input", () => {
    const handleChange = jest.fn();
    render(
      <InputFieldNumber value="x" placeholder="Kodas" onChange={handleChange} />
    );
    const input = screen.getByPlaceholderText("Kodas");
    fireEvent.change(input, { target: { value: "" } });
    expect(handleChange).toHaveBeenCalledWith("");
  });

  it("blocks input that does not match regex", () => {
    const handleChange = jest.fn();
    render(
      <InputFieldNumber
        value=""
        placeholder="Kodas"
        onChange={handleChange}
        regex={/^\d{1,9}$/}
      />
    );
    const input = screen.getByPlaceholderText("Kodas");
    fireEvent.change(input, { target: { value: "abc" } });
    expect(handleChange).not.toHaveBeenCalled();
  });

  it("allows input matching regex", () => {
    const handleChange = jest.fn();
    render(
      <InputFieldNumber
        value=""
        placeholder="Kodas"
        onChange={handleChange}
        regex={/^\d{1,9}$/}
      />
    );
    const input = screen.getByPlaceholderText("Kodas");
    fireEvent.change(input, { target: { value: "123456789" } });
    expect(handleChange).toHaveBeenCalledWith("123456789");
  });

  it("blocks input exceeding regex max length", () => {
    const handleChange = jest.fn();
    render(
      <InputFieldNumber
        value=""
        placeholder="Kodas"
        onChange={handleChange}
        regex={/^\d{1,9}$/}
      />
    );
    const input = screen.getByPlaceholderText("Kodas");
    fireEvent.change(input, { target: { value: "1234567890" } });
    expect(handleChange).not.toHaveBeenCalled();
  });
});
