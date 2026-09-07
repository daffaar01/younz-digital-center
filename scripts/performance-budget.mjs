import {readFile, stat} from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const manifestPath = path.join(root, 'public', 'build', 'manifest.json');
const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
const entries = ['resources/js/app.js', 'resources/css/app.css'];
const budgets = {
    '.js': 90 * 1024,
    '.css': 180 * 1024,
};
let failed = false;

for (const entryName of entries) {
    const entry = manifest[entryName];
    if (!entry?.file) throw new Error(`Entry ${entryName} tidak ditemukan di manifest Vite.`);

    const filePath = path.join(root, 'public', 'build', entry.file);
    const bytes = (await stat(filePath)).size;
    const extension = path.extname(entry.file);
    const budget = budgets[extension];
    const withinBudget = budget === undefined || bytes <= budget;
    failed ||= !withinBudget;
    process.stdout.write(`${withinBudget ? 'PASS' : 'FAIL'} ${entry.file}: ${(bytes / 1024).toFixed(1)} KiB / ${(budget / 1024).toFixed(0)} KiB\n`);
}

if (failed) {
    process.stderr.write('Bundle awal melewati performance budget.\n');
    process.exitCode = 1;
} else {
    process.stdout.write('Semua bundle awal berada dalam performance budget.\n');
}
