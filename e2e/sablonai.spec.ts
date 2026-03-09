import { test, expect, Page } from "@playwright/test";

const MOCK_TEMPLATES = [
  {
    name: "Darbo sutartys",
    type: "directory",
    children: [
      { name: "sutartis_terminuota.docx", type: "file" },
      { name: "sutartis_neterminuota.docx", type: "file" },
    ],
  },
  {
    name: "Instruktažai",
    type: "directory",
    children: [
      { name: "instruktazas_bendra.docx", type: "file" },
      {
        name: "Saugumas",
        type: "directory",
        children: [{ name: "saugumas_darbe.docx", type: "file" }],
      },
    ],
  },
  { name: "prasymas.docx", type: "file" },
];

const MOCK_COMPANIES = [
  { id: 1, companyType: "UAB", companyName: "TestCompany" },
  { id: 2, companyType: "MB", companyName: "SmallFirm" },
];

const FAKE_JWT_PAYLOAD = btoa(
  JSON.stringify({ username: "testuser", roles: ["ROLE_ADMIN"] })
);
const FAKE_TOKEN = `header.${FAKE_JWT_PAYLOAD}.signature`;

async function setupApiMocks(page: Page) {
  await page.route("**/api/templates/all", (route) =>
    route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(MOCK_TEMPLATES) })
  );

  await page.route("**/api/templates/zip", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/zip",
      headers: {
        "Content-Disposition": 'attachment; filename="templates.zip"',
        "content-disposition": 'attachment; filename="templates.zip"',
      },
      body: Buffer.from("PK_FAKE_ZIP"),
    })
  );

  await page.route("**/api/templates/pdf/**", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/pdf",
      headers: {
        "Content-Disposition": 'inline; filename="preview.pdf"',
        "content-disposition": 'inline; filename="preview.pdf"',
      },
      body: Buffer.from("%PDF-1.4 fake"),
    })
  );

  await page.route("**/api/company/all", (route) =>
    route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(MOCK_COMPANIES) })
  );

  await page.route("**/api/template/fillFileBulk", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      headers: {
        "Content-Disposition": 'attachment; filename="document.docx"',
        "content-disposition": 'attachment; filename="document.docx"',
      },
      body: Buffer.from("DOCX_FAKE"),
    })
  );

  await page.route("**/api/template/create", (route) =>
    route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ status: "SUCCESS" }) })
  );
}

async function injectAuth(page: Page) {
  await page.addInitScript((token: string) => {
    localStorage.setItem("token", token);
    localStorage.setItem("username", "testuser");
    localStorage.setItem("role", "ROLE_ADMIN");
  }, FAKE_TOKEN);
}

test.describe("/sablonai page", () => {
  test.beforeEach(async ({ page }) => {
    await injectAuth(page);
    await setupApiMocks(page);
  });

  test("loads and shows page title", async ({ page }) => {
    await page.goto("/sablonai");
    await expect(page.locator("h1")).toContainText("Šablonai");
    await expect(page.locator("text=Pasirinkite šabloną dokumentui sukurti")).toBeVisible();
  });

  test("shows template list from API", async ({ page }) => {
    await page.goto("/sablonai");
    await expect(page.getByText("Darbo sutartys", { exact: true })).toBeVisible();
    await expect(page.getByText("Instruktažai", { exact: true })).toBeVisible();
    await expect(page.getByText("prasymas.docx")).toBeVisible();
  });

  test("expands and collapses directory", async ({ page }) => {
    await page.goto("/sablonai");

    const dirItem = page.getByText("Darbo sutartys", { exact: true });
    await expect(dirItem).toBeVisible();

    await expect(page.getByText("sutartis_terminuota.docx")).toBeVisible();

    await dirItem.click();

    const childContainer = page.locator('[class*="child"][class*="collapsed"]');
    await expect(childContainer.first()).toBeAttached({ timeout: 3000 });

    await dirItem.click();

    await expect(page.getByText("sutartis_terminuota.docx")).toBeVisible({ timeout: 3000 });
  });

  test("file click navigates to template detail page", async ({ page }) => {
    await page.goto("/sablonai");

    await page.getByText("prasymas.docx").click();
    await page.waitForURL("**/sablonai/prasymas.docx**");

    expect(page.url()).toContain("/sablonai/prasymas.docx");
  });

  test("nested file navigates with full path", async ({ page }) => {
    await page.goto("/sablonai");

    await page.getByText("sutartis_terminuota.docx").click();
    await page.waitForURL("**/sablonai/**");

    expect(page.url()).toContain("/sablonai/");
  });

  test("download templates ZIP button triggers download", async ({ page }) => {
    await page.goto("/sablonai");

    const downloadPromise = page.waitForEvent("download");
    await page.getByText("Atsiusti šablonų katalogą").click();
    const download = await downloadPromise;

    expect(download.suggestedFilename()).toBeTruthy();
  });

  test("PDF preview opens and closes", async ({ page }) => {
    await page.goto("/sablonai");

    const previewButtons = page.getByText("Peržiūrėti šabloną");
    await expect(previewButtons.first()).toBeVisible();

    await previewButtons.first().click();

    const iframe = page.locator('iframe[title="PDF preview"]');
    await expect(iframe).toBeVisible({ timeout: 5000 });

    await page.keyboard.press("Escape");
    await expect(iframe).toBeHidden({ timeout: 3000 });
  });

  test("PDF preview close button works", async ({ page }) => {
    await page.goto("/sablonai");

    await page.getByText("Peržiūrėti šabloną").first().click();

    const iframe = page.locator('iframe[title="PDF preview"]');
    await expect(iframe).toBeVisible({ timeout: 5000 });

    const closeButton = page.locator("button").filter({ has: page.locator("svg.lucide-x") });
    await closeButton.click();
    await expect(iframe).toBeHidden({ timeout: 3000 });
  });

  test("checkbox selection enables create documents button", async ({ page }) => {
    await page.goto("/sablonai");

    const createBtn = page.locator("button", { hasText: "Kurti dokumentus" });
    await expect(createBtn).toBeDisabled();

    const checkboxes = page.locator('[class*="checkBox"]');
    await checkboxes.first().click();

    await expect(createBtn).toBeEnabled();
  });

  test("clear selection button deselects checkboxes", async ({ page }) => {
    await page.goto("/sablonai");

    const checkboxes = page.locator('[class*="checkBox"]');
    await checkboxes.first().click();

    const createBtn = page.locator("button", { hasText: "Kurti dokumentus" });
    await expect(createBtn).toBeEnabled();

    await page.locator("button", { hasText: "Išvalyti pasirinkimą" }).click();
    await expect(createBtn).toBeDisabled();
  });

  test("selecting files and clicking create navigates to createBulk", async ({ page }) => {
    await page.goto("/sablonai");

    const checkboxes = page.locator('[class*="checkBox"]');
    await checkboxes.first().click();

    await page.locator("button", { hasText: "Kurti dokumentus" }).click();
    await page.waitForURL("**/sablonai/createBulk**");

    expect(page.url()).toContain("/sablonai/createBulk");
  });

  test("nested directory shows children", async ({ page }) => {
    await page.goto("/sablonai");

    await expect(page.getByText("Instruktažai", { exact: true })).toBeVisible();
    await expect(page.getByText("instruktazas_bendra.docx")).toBeVisible();
    await expect(page.getByText("Saugumas", { exact: true })).toBeVisible();
  });

  test("multiple checkboxes can be selected", async ({ page }) => {
    await page.goto("/sablonai");

    const checkboxes = page.locator('[class*="checkBox"]');
    const count = await checkboxes.count();

    if (count >= 2) {
      await checkboxes.nth(0).click();
      await checkboxes.nth(1).click();

      const createBtn = page.locator("button", { hasText: "Kurti dokumentus" });
      await expect(createBtn).toBeEnabled();
    }
  });
});

test.describe("/sablonai/[template] detail page", () => {
  test.beforeEach(async ({ page }) => {
    await injectAuth(page);
    await setupApiMocks(page);
  });

  test("shows template name and company selector", async ({ page }) => {
    await page.goto("/sablonai/prasymas.docx");

    await expect(page.locator("h1")).toContainText("prasymas.docx");
    await expect(page.getByRole("heading", { name: "Įmonė" })).toBeVisible();
    await expect(page.getByText("Sukurti dokumentą")).toBeVisible();
  });

  test("shows back link to templates", async ({ page }) => {
    await page.goto("/sablonai/prasymas.docx");

    const backLink = page.getByText("Grįžti į šablonus");
    await expect(backLink).toBeVisible();
    await backLink.click();
    await page.waitForURL("**/sablonai");
  });

  test("company selector shows companies from API", async ({ page }) => {
    await page.goto("/sablonai/prasymas.docx");

    await expect(page.getByText("UAB TestCompany")).toBeVisible();
    await expect(page.getByText("MB SmallFirm")).toBeVisible();
  });

  test("generate document triggers download", async ({ page }) => {
    await page.goto("/sablonai/prasymas.docx");

    const selectedArea = page.locator('[class*="selected"]').first();
    await selectedArea.click();

    await page.getByText("UAB TestCompany").click({ force: true });

    const downloadPromise = page.waitForEvent("download");
    await page.getByText("Sukurti dokumentą").click();
    const download = await downloadPromise;

    expect(download.suggestedFilename()).toBeTruthy();
  });
});

test.describe("/sablonai/createBulk page", () => {
  test.beforeEach(async ({ page }) => {
    await injectAuth(page);
    await setupApiMocks(page);
  });

  test("shows company selector and create button", async ({ page }) => {
    await page.addInitScript(() => {
      const storeData = JSON.stringify({
        state: { selected: ["Darbo sutartys/sutartis_terminuota.docx"] },
        version: 0,
      });
      localStorage.setItem("directoriesToSend", storeData);
    });

    await page.goto("/sablonai/createBulk");

    await expect(page.getByRole("heading", { name: "Įmonė" })).toBeVisible();
    await expect(page.getByText("Sukurti dokumentą")).toBeVisible();
  });

  test("back link navigates to templates list", async ({ page }) => {
    await page.goto("/sablonai/createBulk");

    const backLink = page.getByText("Grįžti į šablonus");
    await expect(backLink).toBeVisible();
    await backLink.click();
    await page.waitForURL("**/sablonai");
  });
});
