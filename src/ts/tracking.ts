import type { LostReport, Sighting, Tracking } from './shared/models.js';
import { api, ApiFailure, busy, confirmAction, element, formatDate, initNavigation, isoDate, localDate, message, pageUrl, renderReportHeader, requireLogin, showError, statusLabel } from './shared/ui.js';
import { PetMap } from './shared/map.js';
const reportId = new URLSearchParams(location.search).get('reportId') ?? '';
const feedback = document.getElementById('feedback');
const content = document.getElementById('trackingContent')!;
const refreshButton = document.getElementById('refresh') as HTMLButtonElement;
const previous = document.getElementById('previous') as HTMLButtonElement;
const next = document.getElementById('next') as HTMLButtonElement;
const auto = document.getElementById('autoRefresh') as HTMLInputElement;
const sync = document.getElementById('syncStatus');
let snapshot = '';
let editDirty = false;
let map: PetMap | undefined; let state: Tracking | undefined; let offset = 0; let failures = 0;
let timer: ReturnType<typeof setTimeout> | undefined; let inFlight: Promise<void> | undefined;
void initNavigation();
function cancelTimer(): void { if (timer) clearTimeout(timer); timer = undefined; }
function schedule(): void {
    cancelTimer();
    if (state?.report.status === 'open' && auto.checked && offset === 0 && !document.hidden) {
        timer = setTimeout(() => { void refresh(); }, Math.min(60000, 10000 * 2 ** failures));
    }
}
function sightingInfo(sighting: Sighting): HTMLElement {
    const copy = element('div');
    const sender = sighting.reporterId === null ? 'Anónimo' : `@${sighting.reporterUsername ?? 'colaborador'}`;
    copy.append(element('strong', formatDate(sighting.observedAt, true)), element('p', `${sighting.latitude.toFixed(6)}, ${sighting.longitude.toFixed(6)}`, 'small'),
        element('p', sighting.accuracyMeters === null ? 'Precisión no especificada' : `Precisión aproximada: ${sighting.accuracyMeters} m`, 'small'),
        element('p', sighting.notes || 'Sin notas adicionales.', 'preserve-lines'),
        element('p', { manual: 'Ubicación seleccionada manualmente', gps: 'Ubicación declarada mediante GPS', ble: 'Detección BLE declarada por el cliente' }[sighting.source], 'small muted'),
        element('p', `Recibido: ${formatDate(sighting.createdAt, true)} · ${sender}`, 'small muted'));
    return copy;
}
function render(data: Tracking): void {
    state = data; content.hidden = false;
    auto.disabled = data.report.status !== 'open';
    message(sync, `${formatDate(data.fetchedAt, true)} · ${data.report.status !== 'open' ? statusLabel(data.report.status) : offset > 0 ? 'Actualización pausada mientras consultas el historial' : auto.checked ? 'Actualización automática activa' : 'Actualización manual'}`);
    const warning = document.getElementById('staleWarning')!;
    warning.hidden = !data.latestSighting || Date.now() - new Date(data.latestSighting.observedAt).getTime() <= 3600000;
    warning.textContent = 'Este avistamiento tiene más de una hora. La mascota podría haberse desplazado.';
    previous.disabled = offset === 0; next.disabled = offset + 50 >= data.sightings.total;
    const signature = JSON.stringify([data.report, data.latestSighting, data.sightings]);
    if (signature === snapshot) return;
    snapshot = signature;
    renderReportHeader(document.getElementById('reportHeader')!, data.report);
    if (!map) map = new PetMap('trackingMap', document.getElementById('mapStatus'));
    map.tracking(data.report, data.sightings.items, data.latestSighting);
    const latest = document.getElementById('latest')!;
    latest.replaceChildren(data.latestSighting ? sightingInfo(data.latestSighting) : element('p', 'Todavía no se han recibido avistamientos. Comparte el reporte para que más personas puedan ayudarte.'));
    const timeline = document.getElementById('timeline')!; timeline.replaceChildren();
    for (const sighting of data.sightings.items) {
        const row = element('li', '', sighting.sightingId === data.latestSighting?.sightingId ? 'latest' : '');
        row.append(sightingInfo(sighting));
        const remove = element('button', 'Retirar dato incorrecto', 'btn btn--outline'); remove.type = 'button';
        remove.addEventListener('click', async () => {
            if (!await confirmAction('¿Retirar este avistamiento del mapa? Se conservará su registro como retirado.', 'Retirar avistamiento')) return;
            remove.disabled = true;
            try { await api('sighting', 'DELETE', undefined, { sightingId: sighting.sightingId }); await afterMutation(); }
            catch (error) { if (!requireLogin(error)) showError(feedback, error); }
            finally { remove.disabled = false; }
        });
        row.append(remove); timeline.append(row);
    }
    if (!data.sightings.items.length) timeline.append(element('li', 'No hay avistamientos en esta página.'));
    const total = data.sightings.total;
    document.getElementById('historySummary')!.textContent = total ? `${offset + 1}–${Math.min(offset + 50, total)} de ${total}` : '0 avistamientos';
    previous.disabled = offset === 0; next.disabled = offset + 50 >= total;
    document.getElementById('closePanel')!.hidden = data.report.status !== 'open';
    document.getElementById('editPanel')!.hidden = data.report.status !== 'open';
    if (!editDirty) {
        (document.getElementById('editArea') as HTMLInputElement).value = data.report.lastSeenArea;
        (document.getElementById('editDetails') as HTMLTextAreaElement).value = data.report.details;
        (document.getElementById('editLostAt') as HTMLInputElement).value = localDate(new Date(data.report.lostAt));
        (document.getElementById('editLatitude') as HTMLInputElement).value = data.report.lastSeenLatitude == null ? '' : String(data.report.lastSeenLatitude);
        (document.getElementById('editLongitude') as HTMLInputElement).value = data.report.lastSeenLongitude == null ? '' : String(data.report.lastSeenLongitude);
    }
    auto.disabled = data.report.status !== 'open';
    const url = pageUrl('report.html', { reportId }); (document.getElementById('publicReport') as HTMLAnchorElement).href = url;

}
function refresh(): Promise<void> {
    if (inFlight) return inFlight;
    cancelTimer(); refreshButton.disabled = true; previous.disabled = true; next.disabled = true;
    inFlight = (async () => {
        try {
            const data = await api<Tracking>('tracking', 'GET', undefined, { reportId, limit: '50', offset: String(offset) });
            if (offset >= data.sightings.total && offset > 0) {
                offset = Math.max(0, Math.floor((data.sightings.total - 1) / 50) * 50);
                render(await api<Tracking>('tracking', 'GET', undefined, { reportId, limit: '50', offset: String(offset) }));
            } else render(data);
            failures = 0; message(feedback, ''); document.getElementById('retryLoad')!.hidden = true;
        } catch (error) {
            failures = Math.min(3, failures + 1);
            document.getElementById('retryLoad')!.hidden = error instanceof ApiFailure && error.statusCode > 0 && error.statusCode < 500;
            if (error instanceof ApiFailure && [401, 403, 404].includes(error.statusCode)) {
                content.hidden = true; document.getElementById('reportHeader')!.hidden = true; state = undefined; snapshot = '';
            }
            if (!requireLogin(error)) showError(feedback, error);
            message(sync, 'No se pudieron actualizar los datos. Se conserva la última consulta disponible.');
            if (state) { offset = state.sightings.offset; previous.disabled = offset === 0; next.disabled = offset + 50 >= state.sightings.total; }
        } finally { refreshButton.disabled = false; inFlight = undefined; schedule(); }
    })();
    return inFlight;
}
async function afterMutation(): Promise<void> { if (inFlight) await inFlight; await refresh(); }
refreshButton.addEventListener('click', () => { void refresh(); });
document.getElementById('fitMap')!.addEventListener('click', () => map?.fit());
auto.addEventListener('change', () => { if (auto.checked) void refresh(); else { cancelTimer(); if (state) render(state); } });
previous.addEventListener('click', () => { offset = Math.max(0, offset - 50); void refresh(); });
next.addEventListener('click', () => { offset += 50; void refresh(); });
document.addEventListener('visibilitychange', () => { if (document.hidden) cancelTimer(); else void refresh(); });
window.addEventListener('pagehide', cancelTimer);
document.getElementById('copyReport')!.addEventListener('click', async () => {
    const url = new URL(pageUrl('report.html', { reportId }), location.href).href;
    try { await navigator.clipboard.writeText(url); message(feedback, 'Enlace del reporte público copiado.'); }
    catch { message(feedback, `Puedes copiar este enlace: ${url}`); }
});
const closeForm = document.getElementById('closeForm') as HTMLFormElement;
closeForm.addEventListener('submit', event => event.preventDefault());
for (const button of closeForm.querySelectorAll<HTMLButtonElement>('button[name="status"]')) {
button.addEventListener('click', async () => {
    const status = button.value; if (status !== 'recovered' && status !== 'cancelled') return;
    if (!await confirmAction(status === 'recovered' ? '¿Confirmas que tu mascota fue recuperada? Se cerrará el reporte.' : '¿Cancelar esta búsqueda? Dejará de recibir avistamientos.', status === 'recovered' ? 'Confirmar recuperación' : 'Confirmar cancelación')) return;
    cancelTimer();
    void busy(closeForm, async () => {
        await api<LostReport>('report', 'PATCH', { status, closureReason: (document.getElementById('closureReason') as HTMLTextAreaElement).value }, { reportId });
        await afterMutation(); message(feedback, status === 'recovered' ? 'La búsqueda se cerró como recuperada.' : 'La búsqueda se canceló.');
    }, document.getElementById('closeFeedback')).finally(schedule);
});
}
void refresh();

const editForm = document.getElementById('editReportForm') as HTMLFormElement;
editForm.addEventListener('input', () => { editDirty = true; });
editForm.addEventListener('submit', event => {
    event.preventDefault();
    void busy(editForm, async () => {
        const latitude = document.getElementById('editLatitude') as HTMLInputElement;
        const longitude = document.getElementById('editLongitude') as HTMLInputElement;
        if ((latitude.value === '') !== (longitude.value === '')) throw new Error('Completa ambas coordenadas o deja ambas vacías.');
        await api('report', 'PATCH', {
            lastSeenArea: (document.getElementById('editArea') as HTMLInputElement).value,
            details: (document.getElementById('editDetails') as HTMLTextAreaElement).value,
            lostAt: isoDate((document.getElementById('editLostAt') as HTMLInputElement).value),
            lastSeenLatitude: latitude.value === '' ? null : latitude.valueAsNumber,
            lastSeenLongitude: longitude.value === '' ? null : longitude.valueAsNumber,
        }, { reportId });
        editDirty = false; snapshot = ''; await afterMutation(); message(document.getElementById('editFeedback'), 'Información actualizada.');
    }, document.getElementById('editFeedback'));
});

document.getElementById('retryLoad')!.addEventListener('click', () => { void refresh(); });
