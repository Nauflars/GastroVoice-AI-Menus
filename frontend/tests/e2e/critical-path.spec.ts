import { test, expect } from '@playwright/test';

/**
 * E2E Critical Path:
 * Login → Configure restaurant → Create category → Add item →
 * Create reservation (accepted) → Fill capacity → Create reservation (rejected)
 */

const API_URL = process.env.VITE_API_URL ?? 'http://localhost:8080';

test.describe('Critical Path', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('login with valid credentials', async ({ page }) => {
    await page.goto('/login');

    await page.fill('input[name="email"], input[type="email"]', 'admin@gastrovoice.test');
    await page.fill('input[name="password"], input[type="password"]', 'password123');
    await page.click('button[type="submit"]');

    // Should redirect to dashboard
    await expect(page).toHaveURL(/\/(dashboard)?$/);
    // JWT should be stored
    const token = await page.evaluate(() => localStorage.getItem('token'));
    expect(token).toBeTruthy();
  });

  test('configure restaurant settings', async ({ page }) => {
    await loginAs(page);
    await page.goto('/restaurant');

    // Fill restaurant form
    await page.fill('input[name="name"]', 'GastroVoice Test Restaurant');
    await page.fill('input[name="address"]', 'Calle Test 123, Madrid');
    await page.fill('input[name="phone"]', '+34600000000');

    const seatInput = page.locator('input[name="seatCapacity"]');
    if (await seatInput.isVisible()) {
      await seatInput.fill('40');
    }

    await page.click('button[type="submit"]');

    // Wait for success feedback
    await expect(page.locator('[role="alert"], .toast, [class*="success"]')).toBeVisible({
      timeout: 5000,
    });
  });

  test('create menu category and add item', async ({ page }) => {
    await loginAs(page);
    await page.goto('/menu');

    // Create category
    const addCategoryBtn = page.locator('button', { hasText: /add|crear|nueva.*categor/i });
    if (await addCategoryBtn.isVisible()) {
      await addCategoryBtn.click();
    }

    await page.fill('input[name="name"], input[placeholder*="ategor"]', 'Entrantes');
    const saveCategoryBtn = page.locator('button[type="submit"], button:has-text("Guardar"), button:has-text("Save")');
    await saveCategoryBtn.first().click();

    await expect(page.locator('text=Entrantes')).toBeVisible({ timeout: 5000 });

    // Add menu item
    const addItemBtn = page.locator('button', { hasText: /add|crear|nuevo.*item|nuevo.*plato/i });
    if (await addItemBtn.isVisible()) {
      await addItemBtn.click();
    }

    await page.fill('input[name="name"]', 'Gazpacho Andaluz');
    const descInput = page.locator('input[name="description"], textarea[name="description"]');
    if (await descInput.isVisible()) {
      await descInput.fill('Sopa fría de tomate tradicional');
    }
    await page.fill('input[name="price"]', '8.50');

    const saveItemBtn = page.locator('button[type="submit"], button:has-text("Guardar"), button:has-text("Save")');
    await saveItemBtn.first().click();

    await expect(page.locator('text=Gazpacho')).toBeVisible({ timeout: 5000 });
  });

  test('create reservation (accepted)', async ({ page }) => {
    await loginAs(page);
    await page.goto('/reservations');

    const createBtn = page.locator('button', { hasText: /nueva|crear|reserv/i });
    if (await createBtn.isVisible()) {
      await createBtn.click();
    }

    await page.fill('input[name="customerName"]', 'Carlos Pérez');
    await page.fill('input[name="date"], input[type="date"]', '2026-07-20');

    const timeInput = page.locator('input[name="timeSlot"], input[name="time"], select[name="timeSlot"]');
    if (await timeInput.isVisible()) {
      await timeInput.fill('14:00');
    }

    const peopleInput = page.locator('input[name="numPeople"], input[name="guests"]');
    if (await peopleInput.isVisible()) {
      await peopleInput.fill('4');
    }

    const submitBtn = page.locator('button[type="submit"], button:has-text("Reservar"), button:has-text("Create")');
    await submitBtn.first().click();

    // Should see success or the reservation in the list
    await expect(
      page.locator('text=Carlos Pérez, [role="alert"]:has-text("creada"), .toast:has-text("éxito")')
    ).toBeVisible({ timeout: 5000 });
  });

  test('reservation rejected when capacity full', async ({ page }) => {
    await loginAs(page);

    // Create many reservations via API to fill capacity
    const token = await getApiToken();
    const date = '2026-12-25';
    const timeSlot = '20:00';

    // Fill all tables (default 10 tables based on ReservationAvailabilityChecker MAX_TABLES)
    for (let i = 0; i < 10; i++) {
      await fetch(`${API_URL}/api/reservations`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          date,
          timeSlot,
          numPeople: 2,
          customerName: `Guest ${i + 1}`,
        }),
      });
    }

    // Now try via UI — should be rejected
    await page.goto('/reservations');

    const createBtn = page.locator('button', { hasText: /nueva|crear|reserv/i });
    if (await createBtn.isVisible()) {
      await createBtn.click();
    }

    await page.fill('input[name="customerName"]', 'Overflow Guest');
    await page.fill('input[name="date"], input[type="date"]', date);

    const timeInput = page.locator('input[name="timeSlot"], input[name="time"], select[name="timeSlot"]');
    if (await timeInput.isVisible()) {
      await timeInput.fill(timeSlot);
    }

    const peopleInput = page.locator('input[name="numPeople"], input[name="guests"]');
    if (await peopleInput.isVisible()) {
      await peopleInput.fill('2');
    }

    const submitBtn = page.locator('button[type="submit"], button:has-text("Reservar")');
    await submitBtn.first().click();

    // Should see rejection message (409 Conflict)
    await expect(
      page.locator('[role="alert"]:has-text("disponib"), .toast:has-text("disponib"), text=/no.*disponib/i')
    ).toBeVisible({ timeout: 5000 });
  });
});

async function loginAs(page: import('@playwright/test').Page) {
  await page.goto('/login');
  await page.fill('input[name="email"], input[type="email"]', 'admin@gastrovoice.test');
  await page.fill('input[name="password"], input[type="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/(dashboard)?$/);
}

async function getApiToken(): Promise<string> {
  const res = await fetch(`${API_URL}/api/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: 'admin@gastrovoice.test', password: 'password123' }),
  });
  const data = await res.json();
  return data.token ?? '';
}
