import { render, screen, fireEvent, act } from "@testing-library/react";
import Message from "@/components/messages/message";

function renderMessage(props: {
  title: string;
  message: string;
  backgroundColor?: string;
  autoCloseMs?: number;
}) {
  return render(<Message {...props} />);
}

describe("Message notification", () => {
  beforeEach(() => {
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it("renders title and message", () => {
    renderMessage({ title: "Klaida", message: "Kažkas nutiko" });
    expect(screen.getByText("Klaida")).toBeInTheDocument();
    expect(screen.getByText("Kažkas nutiko")).toBeInTheDocument();
  });

  it("hides after default 7.5 seconds", () => {
    renderMessage({ title: "Dingo", message: "Dingo" });
    expect(screen.getAllByText("Dingo")[0]).toBeInTheDocument();

    act(() => {
      jest.advanceTimersByTime(7500 + 320);
    });

    expect(screen.queryAllByText("Dingo").length).toBe(0);
  });

  it("hides when close button is clicked", () => {
    renderMessage({ title: "Uždaryk", message: "Uždaryk" });
    expect(screen.getAllByText("Uždaryk")[0]).toBeInTheDocument();

    fireEvent.click(screen.getByLabelText("Close"));

    act(() => {
      jest.advanceTimersByTime(320);
    });

    expect(screen.queryAllByText("Uždaryk").length).toBe(0);
  });

  it("renders close button", () => {
    renderMessage({ title: "Klaida", message: "msg" });
    const closeButton = screen.getByLabelText("Close");
    expect(closeButton.tagName).toBe("BUTTON");
  });

  it("is visible initially", () => {
    renderMessage({ title: "Should be visible", message: "Should be visible" });
    expect(screen.getAllByText("Should be visible")[0]).toBeInTheDocument();
  });

  it("does not hide before 7.5 seconds", () => {
    renderMessage({ title: "Still here", message: "Still here" });

    act(() => {
      jest.advanceTimersByTime(5000);
    });

    expect(screen.getAllByText("Still here")[0]).toBeInTheDocument();
  });

  it("applies custom background color", () => {
    renderMessage({
      title: "Info",
      message: "Info",
      backgroundColor: "#38a169",
    });
    const toast = screen.getAllByText("Info")[0].closest(".toast");
    expect(toast).toHaveStyle({ backgroundColor: "#38a169" });
  });
});
