import type { Profile, Pet } from './shared/models.js';
import { api, ApiFailure, busy, initNavigation, isoDate, link, localDate, message, pageUrl, requireLogin, requestId, showError } from './shared/ui.js';
import { coordinates, fillCoordinates, geolocation, PetMap } from './shared/map.js';
const feedback = document.getElementById('feedback');
const form = document.getElementById('reportForm') as HTMLFormElement;
const petSelect = document.getElementById('petId') as HTMLSelectElement;
const existing = document.getElementById('existingReport')!;
let pets: Pet[] = []; let map: PetMap;
let pending: { signature: string; id: string } | undefined;
void initNavigation();
(document.getElementById('lostAt') as HTMLInputElement).value = localDate();
function selected(): void {
    existing.replaceChildren();
    const pet = pets.find(pet => pet.petId === petSelect.value);
    const button = document.getElementById('openReport') as HTMLButtonElement;
    button.disabled = !pet || Boolean(pet.reportId);
    document.getElementById('petStatus')!.textContent = pet?.reportId ? 'Esta mascota ya tiene una búsqueda activa.' : '';
    if (pet?.reportId) existing.append(link('Abrir búsqueda existente', pageUrl('tracking.html', { reportId: pet.reportId })));
}
async function load(): Promise<void> {
    try {
        const profile = await api<Profile>('session'); pets = profile.pets; petSelect.replaceChildren(); document.getElementById('retryLoad')!.hidden = true;
        if (!pets.length) { message(feedback, 'Registra una mascota antes de abrir una búsqueda.'); existing.append(link('Agregar mi mascota', 'profile.html')); document.getElementById('reportContent')!.hidden = false; form.hidden = true; return; }
        for (const pet of pets) { const option = document.createElement('option'); option.value = pet.petId; option.textContent = pet.petName; petSelect.append(option); }
        const requested = new URLSearchParams(location.search).get('petId');
        if (requested && pets.some(pet => pet.petId === requested)) petSelect.value = requested;
        document.getElementById('reportContent')!.hidden = false; message(feedback, ''); selected();
        map = new PetMap('locationMap', document.getElementById('mapStatus'), point => { fillCoordinates(point); map.selected(point); });
    } catch (error) { if (!requireLogin(error)) { showError(feedback, error); document.getElementById('retryLoad')!.hidden = error instanceof ApiFailure && error.statusCode > 0 && error.statusCode < 500; } }
}
petSelect.addEventListener('change', selected);
for (const key of ['latitude', 'longitude']) document.getElementById(key)!.addEventListener('change', () => { try { map?.selected(coordinates()); } catch { /* Partial coordinates are checked on submit. */ } });
document.getElementById('clearLocation')!.addEventListener('click', () => { for (const key of ['latitude', 'longitude']) (document.getElementById(key) as HTMLInputElement).value = ''; map?.selected(null); });
const gps = document.getElementById('useLocation') as HTMLButtonElement;
gps.addEventListener('click', () => geolocation(gps, document.getElementById('mapStatus'), position => { const point = { latitude: position.coords.latitude, longitude: position.coords.longitude }; fillCoordinates(point); map?.selected(point, position.coords.accuracy); }));
form.addEventListener('submit', event => {
    event.preventDefault();
    void busy(form, async () => {
        const point = coordinates();
        const body = { petId: petSelect.value, lostAt: isoDate((document.getElementById('lostAt') as HTMLInputElement).value),
            lastSeenArea: (document.getElementById('lastSeenArea') as HTMLInputElement).value,
            details: (document.getElementById('details') as HTMLTextAreaElement).value,
            ...(point ? { lastSeenLatitude: point.latitude, lastSeenLongitude: point.longitude } : {}) };
        const signature = JSON.stringify(body);
        if (pending?.signature !== signature) pending = { signature, id: requestId() };
        try {
            const report = await api<{ reportId: string }>('report', 'POST', { ...body, clientRequestId: pending.id }); location.replace(pageUrl('tracking.html', { reportId: report.reportId }));
        } catch (error) {
            if (requireLogin(error)) return;
            if (error instanceof ApiFailure && error.statusCode === 409 && error.data && typeof error.data === 'object' && 'reportId' in error.data && typeof error.data.reportId === 'string') {
                const pet = pets.find(item => item.petId === petSelect.value);
                if (pet) pet.reportId = error.data.reportId;
                existing.replaceChildren(link('Abrir búsqueda existente', pageUrl('tracking.html', { reportId: error.data.reportId })));
            }
            throw error;
        }
    }, feedback).then(selected);
});
void load();

document.getElementById('retryLoad')!.addEventListener('click', () => { void load(); });
