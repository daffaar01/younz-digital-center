import {expect, test} from '@playwright/test';

test('homepage exposes local SEO and an honest order funnel', async ({page}) => {
    await page.goto('/');

    await expect(page).toHaveTitle(/Younz Digital Center/);
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', /127\.0\.0\.1:8081/);
    expect(await page.locator('script[type="application/ld+json"]').textContent()).toContain('LocalBusiness');
    await expect(page.getByRole('heading', {level: 1})).toContainText('Satu tempat.');

    const firstOrderLink = page.locator('a[href*="/pesan?"][href*="source=catalog"]').first();
    await expect(firstOrderLink).toBeVisible();
    await firstOrderLink.click();

    await expect(page).toHaveURL(/\/pesan\?.*source=catalog/);
    await expect(page.locator('input[name="source"]')).toHaveValue('catalog');
    await expect(page.locator('[data-order-service]')).not.toHaveValue('');
    await expect(page.locator('[data-order-type]')).not.toHaveValue('');
});

test('guest can submit and track a service order', async ({page}) => {
    await page.goto('/pesan?source=direct');

    await page.locator('input[name="customer_name"]').fill('Browser E2E');
    await page.locator('input[name="customer_phone"]').fill('081234567890');
    await page.locator('[data-order-type]').selectOption('print');
    await page.locator('textarea[name="notes"]').fill('Pesanan otomatis untuk pengujian alur browser.');
    await page.getByRole('button', {name: 'Kirim pesanan untuk diperiksa'}).click();

    await expect(page).toHaveURL(/\/cek-pesanan\/[0-9a-f-]+$/);
    await expect(page.getByText('Pesanan diterima. Simpan tautan ini')).toBeVisible();
    await expect(page.getByText('Browser E2E')).toHaveCount(0);
    await expect(page.getByText(/Menunggu pemeriksaan/i)).toBeVisible();
});

test('mobile sticky CTA appears without horizontal overflow', async ({page, isMobile}) => {
    test.skip(!isMobile, 'Pemeriksaan CTA ini khusus viewport mobile.');
    await page.goto('/');

    await page.getByRole('button', {name: 'Hanya esensial'}).click();
    await page.evaluate(() => window.scrollTo(0, 900));
    const stickyCta = page.locator('[data-sticky-order-cta]');
    await expect(stickyCta).toHaveAttribute('aria-hidden', 'false');
    await expect(stickyCta.getByRole('link', {name: 'Mulai pesan'})).toBeVisible();

    const widths = await page.evaluate(() => ({
        document: document.documentElement.scrollWidth,
        viewport: window.innerWidth,
    }));
    expect(widths.document).toBeLessThanOrEqual(widths.viewport + 1);
});

test('Younz AI responds through the streaming endpoint', async ({page}) => {
    await page.goto('/');
    await page.locator('input[name="message"]').fill('Halo');
    await page.locator('input[name="ai_consent"]').check();
    await page.locator('[data-ai-chat] button[type="submit"]').click();

    const answer = page.locator('[data-ai-assistant-row] .whitespace-pre-wrap').last();
    await expect(answer).not.toContainText('Sedang menyiapkan jawaban', {timeout: 15_000});
    await expect(answer).toContainText(/Halo|Younz|bantu/i);
});
