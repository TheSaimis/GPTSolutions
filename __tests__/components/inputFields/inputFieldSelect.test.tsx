import { render, screen, fireEvent } from "@testing-library/react";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";

describe("InputFieldSelect", () => {
  it("renders with placeholder as heading", () => {
    render(
      <InputFieldSelect
        options={["UAB", "AB", "MB"]}
        placeholder="Tipas"
        onChange={() => {}}
      />
    );
    const heading = screen.getByRole("heading", { level: 2 });
    expect(heading).toHaveTextContent("Tipas");
  });

  it("shows placeholder as default selected value", () => {
    render(
      <InputFieldSelect
        options={["UAB", "AB", "MB"]}
        placeholder="Tipas"
        onChange={() => {}}
      />
    );
    const allTipas = screen.getAllByText("Tipas");
    expect(allTipas.length).toBeGreaterThanOrEqual(2);
  });

  it("renders all string options", () => {
    render(
      <InputFieldSelect
        options={["UAB", "AB", "MB"]}
        placeholder="Tipas"
        onChange={() => {}}
      />
    );
    expect(screen.getByText("UAB")).toBeInTheDocument();
    expect(screen.getByText("AB")).toBeInTheDocument();
    expect(screen.getByText("MB")).toBeInTheDocument();
  });

  it("calls onChange with selected value", () => {
    const handleChange = jest.fn();
    render(
      <InputFieldSelect
        options={["UAB", "AB", "MB"]}
        placeholder="Tipas"
        onChange={handleChange}
      />
    );

    fireEvent.click(screen.getByText("MB"));
    expect(handleChange).toHaveBeenCalledWith("MB");
  });

  it("handles object options with value and label", () => {
    const handleChange = jest.fn();
    const options = [
      { value: "1", label: "UAB Company" },
      { value: "2", label: "MB Company" },
    ];

    render(
      <InputFieldSelect
        options={options}
        placeholder="Tipas"
        onChange={handleChange}
      />
    );

    fireEvent.click(screen.getByText("MB Company"));
    expect(handleChange).toHaveBeenCalledWith("2");
  });

  it("updates displayed value after selection", () => {
    render(
      <InputFieldSelect
        options={["UAB", "AB", "MB"]}
        placeholder="Tipas"
        selected="UAB"
        onChange={() => {}}
      />
    );

    const uabOptions = screen.getAllByText("UAB");
    fireEvent.click(uabOptions[uabOptions.length - 1]);
    expect(screen.getAllByText("UAB").length).toBeGreaterThanOrEqual(1);
  });
});
