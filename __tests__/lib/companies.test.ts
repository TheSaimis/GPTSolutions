import { CompanyApi } from "@/lib/api/companies";

const mockGet = jest.fn();
const mockPost = jest.fn();
jest.mock("@/lib/api/api", () => ({
  api: {
    get: (...args: unknown[]) => mockGet(...args),
    post: (...args: unknown[]) => mockPost(...args),
  },
}));

describe("CompanyApi", () => {
  beforeEach(() => {
    mockGet.mockClear();
    mockPost.mockClear();
  });

  it("getAll calls GET /api/company/all", async () => {
    mockGet.mockResolvedValue([{ id: 1, companyType: "UAB", companyName: "Test" }]);
    await CompanyApi.getAll();
    expect(mockGet).toHaveBeenCalledWith("/api/company/all");
  });

  it("companyCreate sends company to POST /api/company/create", async () => {
    mockPost.mockResolvedValue({ id: 1, companyType: "UAB", companyName: "New" });
    const company = {
      id: 0,
      companyType: "UAB",
      companyName: "New",
      address: "Street 1",
      companyCode: "123",
      firstName: "John",
      lastName: "Doe",
      position: "Director",
    };
    await CompanyApi.companyCreate(company);
    expect(mockPost).toHaveBeenCalledWith("/api/company/create", company);
  });
});
