import { cp, mkdir } from 'node:fs/promises';
const source = new URL('../node_modules/leaflet/dist/', import.meta.url);
const target = new URL('../public/vendor/leaflet/', import.meta.url);
await mkdir(target, { recursive: true });
for (const name of ['leaflet.js', 'leaflet.css', 'images']) await cp(new URL(name, source), new URL(name, target), { recursive: true });
await cp(new URL('../node_modules/leaflet/LICENSE', import.meta.url), new URL('LICENSE', target));
console.log('Leaflet assets copied to public/vendor/leaflet');
