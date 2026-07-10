import { copyFile, mkdir, readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = fileURLToPath(new URL('..', import.meta.url));
const manifestPath = path.join(root, 'public', 'build', 'manifest.json');
const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
const cssEntry = manifest['resources/css/app.css']?.file
    ?? manifest['resources/js/app.js']?.css?.[0];

if (! cssEntry) {
    throw new Error('Vite manifest does not contain the ChatMe CSS entry.');
}

const destinationDir = path.join(root, 'public', 'css');
await mkdir(destinationDir, { recursive: true });
await copyFile(path.join(root, 'public', 'build', cssEntry), path.join(destinationDir, 'app.css'));
