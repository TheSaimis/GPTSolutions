import { render, screen, fireEvent } from "@testing-library/react";
import InputFieldPassword from "@/components/inputFields/inputFieldPassword";

describe("InputFieldPassword", () => {
  it("renders with placeholder label", () => {
    render(
      <InputFieldPassword value="" placeholder="Slaptažodis" onChange={() => {}} />
    );
    expect(screen.getByText("Slaptažodis")).toBeInTheDocument();
  });

  it("renders password input by default", () => {
    render(
      <InputFieldPassword value="" placeholder="Pass" onChange={() => {}} />
    );
    const input = screen.getByPlaceholderText("Pass");
    expect(input).toHaveAttribute("type", "password");
  });

  it("toggles to text type when visibility button clicked", () => {
    render(
      <InputFieldPassword value="" placeholder="Pass" onChange={() => {}} />
    );
    const buttons = screen.getAllByRole("button");
    fireEvent.click(buttons[0]);
    const input = screen.getByPlaceholderText("Pass");
    expect(input).toHaveAttribute("type", "text");
  });

  it("calls onChange when typing", () => {
    const handleChange = jest.fn();
    render(
      <InputFieldPassword
        value=""
        placeholder="Pass"
        onChange={handleChange}
      />
    );
    fireEvent.change(screen.getByPlaceholderText("Pass"), {
      target: { value: "secret" },
    });
    expect(handleChange).toHaveBeenCalledWith("secret");
  });

  it("displays value", () => {
    render(
      <InputFieldPassword
        value="mypass"
        placeholder="Pass"
        onChange={() => {}}
      />
    );
    expect(screen.getByDisplayValue("mypass")).toBeInTheDocument();
  });
});
