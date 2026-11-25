const { test, expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');

const png = execFileSync('php', ['-r', '$i=imagecreatetruecolor(20,20); imagepng($i); imagedestroy($i);']);
const jpg = execFileSync('php', ['-r', '$i=imagecreatetruecolor(20,20); imagejpeg($i); imagedestroy($i);']);
const image = (name) => ({ name, mimeType: name.endsWith('.jpg') ? 'image/jpeg' : 'image/png', buffer: name.endsWith('.jpg') ? jpg : png });

for (const theme of ['tailwind', 'bootstrap']) {
  test(`${theme}: keyboard selection, progress, reordering, preview and deletion`, async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`/${theme}`);
    await page.evaluate(() => {
      window.uploadProgressEvents = [];
      document.addEventListener('livewire-upload-progress', event => window.uploadProgressEvents.push(event.detail.progress));
    });
    const left = page.getByTestId('left');
    const right = page.getByTestId('right');
    const choose = left.locator('label[role="button"]');
    await choose.focus();
    const picker = page.waitForEvent('filechooser');
    await choose.press('Enter');
    await (await picker).setFiles([image('one.png'), image('two.png')]);
    await expect(left.locator('[draggable="true"]')).toHaveCount(2);
    await expect.poll(() => page.evaluate(() => window.uploadProgressEvents.length)).toBeGreaterThan(0);
    const progress = left.getByRole('progressbar');
    await left.locator('[x-on\\:livewire-upload-progress]').evaluate(el => el.dispatchEvent(new CustomEvent('livewire-upload-progress', { detail: { progress: 50 } })));
    await expect(progress).toHaveAttribute('aria-valuenow', '50');

    const queue = left.locator('[draggable="true"]');
    await queue.nth(1).dragTo(queue.nth(0), { targetPosition: { x: 10, y: 5 } });
    await expect(queue.first()).toContainText('two.png');
    await left.getByRole('button', { name: 'Upload', exact: true }).click();
    const attached = left.locator('li[draggable="true"]');
    await expect(attached).toHaveCount(2);
    await expect(right.locator('li[draggable="true"]')).toHaveCount(0);

    await attached.nth(1).dragTo(attached.nth(0), { targetPosition: { x: 10, y: 5 } });
    await expect(attached.first()).toContainText('one');
    await attached.first().locator('[title^="Preview"]').click();
    const preview = left.getByRole('dialog', { name: 'Image preview' });
    await expect(preview).toBeVisible();
    await expect(preview.getByRole('button', { name: 'Close preview' })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(preview).toBeHidden();

    await attached.first().getByRole('button', { name: 'Edit', exact: true }).click();
    await attached.first().locator('input[type="text"]').first().fill('Browser caption');
    await attached.first().getByRole('button', { name: 'Save', exact: true }).click();
    await expect(attached.first()).toContainText('Browser caption');
    await attached.first().getByRole('button', { name: 'Delete', exact: true }).click();
    const dialog = left.getByRole('dialog', { name: 'Delete this file?' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(attached).toHaveCount(1);
    expect(errors).toEqual([]);
  });

  test(`${theme}: dropped files can be removed from the queue`, async ({ page }) => {
    await page.goto(`/${theme}`);
    const left = page.getByTestId('left');
    const transfer = await page.evaluateHandle(bytes => {
      const data = new DataTransfer();
      data.items.add(new File([new Uint8Array(bytes)], 'dropped.png', { type: 'image/png' }));
      return data;
    }, [...png]);
    await left.locator('[data-media-dropzone]').dispatchEvent('drop', { dataTransfer: transfer });
    await expect(left.locator('[draggable="true"]')).toHaveCount(1);
    await left.getByRole('button', { name: 'Remove', exact: true }).click();
    await expect(left.locator('[draggable="true"]')).toHaveCount(0);
  });

  test(`${theme}: deferred partial failures retain only unfinished files and complete on retry`, async ({ page }) => {
    await page.goto(`/${theme}`);
    const form = page.getByTestId('create');
    await form.locator('input[type="file"]').setInputFiles([image('accepted.jpg'), image('rejected.png'), image('later.jpg')]);
    await expect(form.locator('[draggable="true"]')).toHaveCount(3);
    await form.getByRole('button', { name: 'Save form', exact: true }).click();
    await expect(form.getByText('This file could not be uploaded.', { exact: false })).toBeVisible();
    await expect(form.getByText('Deferred attachment complete')).toHaveCount(0);
    await expect(form.locator('div[draggable="true"]')).toHaveCount(2);
    await form.locator('div[draggable="true"]').filter({ hasText: 'rejected.png' }).getByRole('button', { name: 'Remove', exact: true }).click();
    await form.getByRole('button', { name: 'Upload', exact: true }).click();
    await expect(form.getByText('Deferred attachment complete')).toBeVisible();
    await expect(form.locator('li[draggable="true"]')).toHaveCount(2);
    await expect(form.locator('div[draggable="true"]')).toHaveCount(0);
  });
}
