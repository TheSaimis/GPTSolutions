import { render, screen, fireEvent } from "@testing-library/react";
import InputFieldText from "@/components/inputFields/inputFieldText";

describe("InputFieldText", () => {
  it("renders with placeholder label", () => {
    render(
      <InputFieldText value="" placeholder="Vardas" onChange={() => {}} />
    );
    expect(screen.getByText("Vardas")).toBeInTheDocument();
  });

  it("renders input with correct value", () => {
    render(
      <InputFieldText
        value="Jonas"
        placeholder="Vardas"
        onChange={() => {}}
      />
    );
    const input = screen.getByPlaceholderText("Vardas") as HTMLInputElement;
    expect(input.value).toBe("Jonas");
  });

  it("calls onChange when user types", () => {
    const handleChange = jest.fn();
    render(
      <InputFieldText value="" placeholder="Vardas" onChange={handleChange} />
    );
    const input = screen.getByPlaceholderText("Vardas");
    fireEvent.change(input, { target: { value: "Petras" } });
    expect(handleChange).toHaveBeenCalledWith("Petras");
  });

  it("renders input of type text by default", () => {
    render(
      <InputFieldText value="" placeholder="Vardas" onChange={() => {}} />
    );
    const input = screen.getByPlaceholderText("Vardas") as HTMLInputElement;
    expect(input.type).toBe("text");
  });

  it("allows empty input", () => {
    const handleChange = jest.fn();
    render(
      <InputFieldText value="x" placeholder="Test" onChange={handleChange} />
    );
    fireEvent.change(screen.getByPlaceholderText("Test"), {
      target: { value: "" },
    });
    expect(handleChange).toHaveBeenCalledWith("");
  });

  it("blocks input not matching regex", () => {
    const handleChange = jest.fn();
    render(
      <InputFieldText
        value=""
        placeholder="Name"
        onChange={handleChange}
        regex={/^[a-zA-Z]+$/}
      />
    );
    fireEvent.change(screen.getByPlaceholderText("Name"), {
      target: { value: "123" },
    });
    expect(handleChange).not.toHaveBeenCalled();
  });

  it("allows input matching regex", () => {
    const handleChange = jest.fn();
    render(
      <InputFieldText
        value=""
        placeholder="Name"
        onChange={handleChange}
        regex={/^[a-zA-Z]+$/}
      />
    );
    fireEvent.change(screen.getByPlaceholderText("Name"), {
      target: { value: "Jonas" },
    });
    expect(handleChange).toHaveBeenCalledWith("Jonas");
  });
});
