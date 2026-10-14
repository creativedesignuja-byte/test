import { test, expect } from "@playwright/test";
import fs from "node:fs";
const errors = [];
test("Persian workflow: project, goal, stages, task, subtask, label, comment and attachment", async ({
    page,
}) => {
    page.on("pageerror", (e) => errors.push(e.message));
    await page.goto("/register");
    await page.locator("[name=name]").fill("کاربر آزمون مرورگر");
    await page.locator("[name=email]").fill(`smoke-${Date.now()}@browser.test`);
    await page.locator("[name=password]").fill("Browser-only-test-9841");
    await page
        .locator("[name=password_confirmation]")
        .fill("Browser-only-test-9841");
    await page.getByRole("button", { name: "ساخت حساب" }).click();
    await expect(page).toHaveURL(/dashboard/);
    expect(
        (await page.request.post("/api/projects", { data: {} })).status(),
    ).toBe(419);
    await expect(
        page
            .locator(".empty-state")
            .filter({ hasText: "اولین مسیر را بسازید" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "شروع یک پروژه جدید" }).click();
    const modal = page.getByRole("dialog");
    await modal
        .locator('input[x-model="projectForm.name"]')
        .fill("پروژه آزمون مرورگر");
    await modal.getByRole("button", { name: "ادامه", exact: true }).click();
    await modal
        .locator('input[x-model="projectForm.goal.title"]')
        .fill("راه‌اندازی نسخه آزمایشی");
    await modal
        .locator('textarea[x-model="projectForm.goal.success_criteria"]')
        .fill("کاربر بتواند یک تسک کامل بسازد.");
    await modal.getByRole("button", { name: "ادامه", exact: true }).click();
    await modal
        .getByRole("button", { name: "ساخت پروژه", exact: true })
        .click();
    await expect(
        page.getByRole("heading", { name: "پروژه آزمون مرورگر", exact: true }),
    ).toBeVisible();
    await expect(modal).toBeHidden();
    await page
        .locator(".page-heading")
        .getByRole("button", { name: "افزودن تسک", exact: true })
        .click();
    await modal
        .getByRole("textbox", { name: "عنوان تسک", exact: true })
        .fill("تسک آزمون مرورگر");
    await modal
        .getByRole("textbox", { name: "توضیحات تسک", exact: true })
        .fill("متن امن <script>alert(1)</script>");
    await modal.getByRole("button", { name: "افزودن زیرتسک" }).click();
    await modal.getByPlaceholder("عنوان زیرتسک").fill("بررسی نتیجه");
    await modal
        .getByRole("button", { name: "ساخت مرحله جدید", exact: true })
        .click();
    await modal
        .locator('input[x-model="inlineStageForm.title"]')
        .fill("بازبینی نهایی");
    await modal.getByRole("button", { name: "ساخت و انتخاب مرحله" }).click();
    await expect(
        modal.locator('select[x-model\\.number="taskForm.stage_id"]'),
    ).not.toHaveValue("new");
    await modal
        .getByPlaceholder("جست‌وجو یا ساخت برچسب...")
        .fill("برچسب آزمون");
    await modal.getByRole("button", { name: "ساخت «برچسب آزمون»" }).click();
    await modal
        .getByRole("button", { name: "تاریخ شروع تسک", exact: true })
        .click();
    await expect(modal.locator(".calendar-pop:visible")).toContainText("۱۴۰۵");
    await page.keyboard.press("Escape");
    await modal
        .locator("input[type=file]")
        .setInputFiles({
            name: "test-notes.txt",
            mimeType: "text/plain",
            buffer: Buffer.from("Browser upload check"),
        });
    await modal
        .getByPlaceholder("نظرتان را بنویسید...")
        .fill("نظر اولیه از مرورگر");
    await modal.getByRole("button", { name: "ساخت تسک", exact: true }).click();
    await expect(modal).toBeHidden();
    await page
        .getByRole("button", { name: "تسک آزمون مرورگر", exact: true })
        .click();
    await expect(
        modal.getByText("نظر اولیه از مرورگر", { exact: true }),
    ).toBeVisible();
    await expect(
        modal.getByRole("link", { name: "test-notes.txt" }),
    ).toBeVisible();
    await expect(modal.getByPlaceholder("عنوان زیرتسک")).toHaveValue(
        "بررسی نتیجه",
    );
    await modal
        .getByRole("textbox", { name: "عنوان تسک", exact: true })
        .fill("ویرایش تسک آزمون");
    await modal.getByRole("button", { name: "بستن فرم" }).click();
    await expect(page.getByRole("alertdialog")).toBeVisible();
    await page
        .getByRole("alertdialog")
        .getByRole("button", { name: "انصراف" })
        .click();
    await modal.getByRole("button", { name: "ذخیره تغییرات" }).click();
    await expect(modal).toBeHidden();
    await page
        .getByRole("button", { name: "تکمیل ویرایش تسک آزمون", exact: true })
        .click();
    await expect(
        page.getByRole("button", { name: "ویرایش تسک آزمون", exact: true }),
    ).toBeHidden();
    await page
        .getByRole("combobox", { name: "فیلتر تسک‌های پروژه" })
        .selectOption("done");
    await expect(
        page.getByRole("button", { name: "ویرایش تسک آزمون", exact: true }),
    ).toBeVisible();
    await page
        .getByRole("button", { name: "باز کردن ویرایش تسک آزمون", exact: true })
        .click();
    await page
        .getByRole("combobox", { name: "فیلتر تسک‌های پروژه" })
        .selectOption("active");
    await expect(
        page.getByRole("button", { name: "ویرایش تسک آزمون", exact: true }),
    ).toBeVisible();
    const columns = page.locator(".product-board .stage-column-head");
    await columns.nth(0).dragTo(columns.nth(1));
    await expect(columns.nth(0)).toContainText("طراحی");
    await page.reload();
    await expect(
        page.locator(".product-board .stage-column-head").nth(0),
    ).toContainText("طراحی");
    expect(errors).toEqual([]);
});
test("Desktop and mobile visual checks on seeded workspace", async ({
    page,
}) => {
    const pageErrors = [];
    page.on("pageerror", (e) => pageErrors.push(e.message));
    const password = fs
        .readFileSync(".env", "utf8")
        .match(/^DEMO_PASSWORD=(.+)$/m)[1];
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto("/login");
    await page.locator("[name=email]").fill("sara@example.test");
    await page.locator("[name=password]").fill(password);
    await page.getByRole("button", { name: "ورود به فضای کار" }).click();
    await expect(page.locator(".project-card").first()).toBeVisible();
    expect(await page.locator("html").getAttribute("dir")).toBe("rtl");
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({
        animations: "disabled",
        path: "/workspace/artifacts/rasta-dashboard.png",
        fullPage: true,
    });
    await page
        .getByRole("link", { name: "سامانه رزرو هتل", exact: true })
        .click();
    await expect(page.locator(".goal-card")).toBeVisible();
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({
        animations: "disabled",
        path: "/workspace/artifacts/rasta-project.png",
        fullPage: true,
    });
    await page
        .getByRole("button", { name: "طراحی صفحه جزئیات هتل", exact: true })
        .click();
    await expect(page.getByRole("dialog")).toBeVisible();
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({
        animations: "disabled",
        path: "/workspace/artifacts/rasta-task.png",
    });
    await page.getByRole("button", { name: "بستن فرم" }).click();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/dashboard");
    await expect(page.locator(".project-card").first()).toBeVisible();
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({
        animations: "disabled",
        path: "/workspace/artifacts/rasta-mobile.png",
        fullPage: true,
    });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    ).toBe(true);
    await page.getByRole("button", { name: "باز کردن منو" }).click();
    await page.getByRole("link", { name: /تسک‌های من/ }).click();
    await expect(page.locator(".task-project-section").first()).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    ).toBe(true);
    expect(pageErrors).toEqual([]);
});
