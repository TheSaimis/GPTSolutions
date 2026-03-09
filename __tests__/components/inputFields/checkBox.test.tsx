import { render, fireEvent } from "@testing-library/react";
import CheckBox from "@/components/inputFields/checkBox";

describe("CheckBox", () => {
  it("renders without crashing", () => {
    const { container } = render(
      <CheckBox value={false} onChange={() => {}} />
    );
    expect(container.firstChild).toBeInTheDocument();
  });

  it("calls onChange with true when unchecked and clicked", () => {
    const handleChange = jest.fn();
    const { container } = render(
      <CheckBox value={false} onChange={handleChange} />
    );
    fireEvent.click(container.firstChild!);
    expect(handleChange).toHaveBeenCalledWith(true);
  });

  it("calls onChange with false when checked and clicked", () => {
    const handleChange = jest.fn();
    const { container } = render(
      <CheckBox value={true} onChange={handleChange} />
    );
    fireEvent.click(container.firstChild!);
    expect(handleChange).toHaveBeenCalledWith(false);
  });

  it("toggles on each click", () => {
    const handleChange = jest.fn();
    const { container } = render(
      <CheckBox value={false} onChange={handleChange} />
    );

    fireEvent.click(container.firstChild!);
    expect(handleChange).toHaveBeenCalledWith(true);

    handleChange.mockClear();
    const { container: c2 } = render(
      <CheckBox value={true} onChange={handleChange} />
    );
    fireEvent.click(c2.firstChild!);
    expect(handleChange).toHaveBeenCalledWith(false);
  });
});
