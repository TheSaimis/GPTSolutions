import { render, screen, fireEvent } from "@testing-library/react";
import InputFieldDate from "@/components/inputFields/inputFieldDate";

describe("InputFieldDate", () => {
  it("renders with placeholder label", () => {
    render(
      <InputFieldDate value="" placeholder="Data" onChange={() => {}} />
    );
    expect(screen.getByText("Data")).toBeInTheDocument();
  });

  it("renders date input type", () => {
    render(
      <InputFieldDate value="" placeholder="Data" onChange={() => {}} />
    );
    const input = screen.getByDisplayValue("") as HTMLInputElement;
    expect(input.type).toBe("date");
  });

  it("renders with correct value", () => {
    render(
      <InputFieldDate
        value="2026-02-24"
        placeholder="Data"
        onChange={() => {}}
      />
    );
    const input = screen.getByDisplayValue("2026-02-24") as HTMLInputElement;
    expect(input.value).toBe("2026-02-24");
  });

  it("calls onChange when date is selected", () => {
    const handleChange = jest.fn();
    render(
      <InputFieldDate value="" placeholder="Data" onChange={handleChange} />
    );
    const input = screen.getByDisplayValue("");
    fireEvent.change(input, { target: { value: "2026-03-15" } });
    expect(handleChange).toHaveBeenCalledWith("2026-03-15");
  });
});
