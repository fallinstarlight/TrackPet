import type { LostReport, Sighting } from './shared/models.js';
import { api, ApiFailure, busy, element, initNavigation, isoDate, link, localDate, loginUrl, message, pageUrl, renderReportHeader, requestId, showError } from './shared/ui.js';
import { coordinates, fillCoordinates, geolocation, PetMap } from './shared/map.js';
const reportId = new URLSearchParams(location.search).get('reportId') ?? '';
const feedback = document.getElementById('feedback');
const sightingFeedback = document.getElementById('sightingFeedback');
const form = document.getElementById('sightingForm') as HTMLFormElement;
const observedAt = document.getElementById('observedAt') as HTMLInputElement;
const accuracy = document.getElementById('accuracy') as HTMLInputElement;
let map: PetMap | undefined; let source: 'manual' | 'gps' = 'manual';
let pending: { signature: string; id: string } | undefined;
const viewerPromise = initNavigation();
observedAt.value = localDate();
function manual(): void { source = 'manual'; accuracy.value = ''; try { map?.selected(coordinates()); } catch { /* Validate the full pair on submit. */ } }
async function load(): Promise<void> {
    try {
        const [report, viewer] = await Promise.all([api<LostReport>('report', 'GET', undefined, { reportId }), viewerPromise]);
        renderReportHeader(document.getElementById('reportHeader')!, report); message(feedback, ''); document.getElementById('retryLoad')!.hidden = true;
        document.getElementById('sightingPanel')!.hidden = report.status !== 'open';
        document.getElementById('helpPanel')!.hidden = report.status !== 'open' || viewer.authenticated;
        (document.getElementById('loginToHelp') as HTMLAnchorElement).href = loginUrl();
        const closed = document.getElementById('closedNotice')!;
        closed.hidden = report.status === 'open';
        closed.replaceChildren(element('h2', report.status === 'recovered' ? 'La búsqueda terminó: mascota recuperada.' : 'Esta búsqueda está cerrada.'), element('p', 'No se aceptan nuevos avistamientos para este reporte. Gracias por ayudar.'));
        const actions = document.getElementById('ownerActions')!; actions.hidden = !report.canTrack;
        if (report.canTrack) actions.replaceChildren(link('Ver mi mapa e historial privado', pageUrl('tracking.html', { reportId })));
        if (report.status === 'open' && !map) {
            map = new PetMap('locationMap', document.getElementById('mapStatus'), point => { fillCoordinates(point); manual(); });
        }
    } catch (error) { showError(feedback, error); document.getElementById('retryLoad')!.hidden = error instanceof ApiFailure && error.statusCode > 0 && error.statusCode < 500; }
}
for (const key of ['latitude', 'longitude']) document.getElementById(key)!.addEventListener('input', manual);
const gps = document.getElementById('useLocation') as HTMLButtonElement;
gps.addEventListener('click', () => geolocation(gps, document.getElementById('mapStatus'), position => {
    const point = { latitude: position.coords.latitude, longitude: position.coords.longitude }; fillCoordinates(point);
    accuracy.value = position.coords.accuracy.toFixed(2); observedAt.value = localDate(new Date(position.timestamp)); source = 'gps'; map?.selected(point, position.coords.accuracy);
}));
form.addEventListener('submit', event => {
    event.preventDefault();
    void busy(form, async () => {
        const point = coordinates(true)!;
        const body = { reportId, latitude: point.latitude, longitude: point.longitude, observedAt: isoDate(observedAt.value), source,
            notes: (document.getElementById('notes') as HTMLTextAreaElement).value,
            ...(accuracy.value !== '' ? { accuracyMeters: accuracy.valueAsNumber } : {}) };
        const signature = JSON.stringify(body);
        if (pending?.signature !== signature) pending = { signature, id: requestId() };
        try {
            const sighting = await api<Sighting>('sighting', 'POST', { ...body, clientRequestId: pending.id });
            pending = undefined;
            form.reset(); observedAt.value = localDate(); source = 'manual'; map?.selected(null);
            message(sightingFeedback, sighting.reporterId === null
                ? 'Avistamiento anónimo enviado. El dueño podrá verlo en su mapa.'
                : 'Avistamiento enviado. Puedes revisarlo en Mis avistamientos.');
            await load();
        } catch (error) {
            if (error instanceof ApiFailure && error.statusCode === 401) { location.replace(loginUrl()); return; }
            if (error instanceof ApiFailure && error.statusCode === 409) await load();
            throw error;
        }
    }, sightingFeedback);
});
void load();

document.getElementById('retryLoad')!.addEventListener('click', () => { void load(); });
