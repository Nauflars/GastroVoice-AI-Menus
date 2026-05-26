import { test, expect } from '@playwright/test';

/**
 * E2E Voice Simulation:
 * 5-turn reservation conversation via text simulator completes successfully.
 */

test.describe('Voice Simulation - Reservation Flow', () => {
  test('5-turn reservation via text simulator', async ({ page }) => {
    // Login first
    await page.goto('/login');
    await page.fill('input[name="email"], input[type="email"]', 'admin@gastrovoice.test');
    await page.fill('input[name="password"], input[type="password"]', 'password123');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/(dashboard)?$/);

    // Navigate to voice simulator
    await page.goto('/voice');

    // Ensure text mode is selected
    const textModeBtn = page.locator('button', { hasText: /texto/i });
    if (await textModeBtn.isVisible()) {
      await textModeBtn.click();
    }

    // Turn 1: Greeting + intent
    const input = page.locator('input[placeholder*="cliente"], input[type="text"]');
    await input.fill('Hola, quiero hacer una reserva para 4 personas');
    await input.press('Enter');

    // Wait for assistant reply
    const assistantBubble = page.locator('[class*="bg-gray-100"]');
    await expect(assistantBubble.first()).toBeVisible({ timeout: 15000 });

    // Turn 2: Date
    await input.fill('Para el viernes que viene');
    await input.press('Enter');
    await page.waitForTimeout(2000);
    await expect(assistantBubble.nth(1)).toBeVisible({ timeout: 15000 });

    // Turn 3: Time
    await input.fill('A las 21:00');
    await input.press('Enter');
    await page.waitForTimeout(2000);
    await expect(assistantBubble.nth(2)).toBeVisible({ timeout: 15000 });

    // Turn 4: Name
    await input.fill('A nombre de García');
    await input.press('Enter');
    await page.waitForTimeout(2000);
    await expect(assistantBubble.nth(3)).toBeVisible({ timeout: 15000 });

    // Turn 5: Confirm
    await input.fill('Sí, confirmo la reserva');
    await input.press('Enter');
    await page.waitForTimeout(2000);

    // Check final reply contains confirmation keywords
    const allBubbles = await assistantBubble.allTextContents();
    const lastReply = allBubbles[allBubbles.length - 1] ?? '';
    const hasConfirmation = /reserv|confirm|perfect|listo|registr/i.test(lastReply);
    expect(hasConfirmation).toBeTruthy();

    // Session ID should be visible
    const sessionIndicator = page.locator('text=/Session/i');
    await expect(sessionIndicator).toBeVisible();
  });

  test('voice selector changes voice', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"], input[type="email"]', 'admin@gastrovoice.test');
    await page.fill('input[name="password"], input[type="password"]', 'password123');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/(dashboard)?$/);

    await page.goto('/voice');

    // Voice selector should be visible
    const voiceSelect = page.locator('#voice-select, select');
    await expect(voiceSelect.first()).toBeVisible();

    // Change voice to Nova
    await voiceSelect.first().selectOption('nova');
    const selectedValue = await voiceSelect.first().inputValue();
    expect(selectedValue).toBe('nova');
  });

  test('reset button clears conversation', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"], input[type="email"]', 'admin@gastrovoice.test');
    await page.fill('input[name="password"], input[type="password"]', 'password123');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/(dashboard)?$/);

    await page.goto('/voice');

    // Send a message
    const input = page.locator('input[placeholder*="cliente"], input[type="text"]');
    await input.fill('Hola');
    await input.press('Enter');

    // Wait for response
    await page.waitForTimeout(3000);

    // Click reset
    const resetBtn = page.locator('button, a', { hasText: /reset/i });
    await resetBtn.click();

    // Conversation should be cleared — placeholder text visible again
    const placeholder = page.locator('text=/escribe.*mensaje|simular/i');
    await expect(placeholder).toBeVisible({ timeout: 3000 });
  });
});
