import type { Page, Sighting } from './shared/models.js';
import { api, confirmAction, element, formatDate, initNavigation, link, message, pageUrl, requireLogin, showError, statusLabel } from './shared/ui.js';
let offset = 0; let loading = false;
const feedback = document.getElementById('feedback');
const previous = document.getElementById('previous') as HTMLButtonElement;
const next = document.getElementById('next') as HTMLButtonElement;
void initNavigation();
async function load(): Promise<void> {
    if (loading) return; loading = true; previous.disabled = true; next.disabled = true;
    try {
        const data = await api<Page<Sighting>>('sightings', 'GET', undefined, { limit: '20', offset: String(offset) });
        const list = document.getElementById('timeline')!; list.replaceChildren();
        for (const sighting of data.items) {
            const row = element('li');
            row.append(element('h2', sighting.petName ?? 'Mascota'), element('p', `Observación: ${formatDate(sighting.observedAt)}`),
                element('p', `${sighting.latitude.toFixed(6)}, ${sighting.longitude.toFixed(6)}`, 'small'), element('p', sighting.notes, 'preserve-lines'),
                element('p', sighting.isRetracted ? 'Avistamiento retirado' : statusLabel(sighting.reportStatus ?? 'open'), 'small muted'),
                link('Consultar reporte', pageUrl('report.html', { reportId: sighting.reportId }), 'text-link'));
            if (!sighting.isRetracted) {
                const remove = element('button', 'Retirar avistamiento', 'btn btn--outline');
                remove.addEventListener('click', async () => {
                    if (!await confirmAction('¿Retirar este avistamiento del mapa del dueño?', 'Retirar avistamiento')) return;
                    remove.disabled = true;
                    try { await api('sighting', 'DELETE', undefined, { sightingId: sighting.sightingId }); await load(); }
                    catch (error) { if (!requireLogin(error)) showError(feedback, error); }
                    finally { remove.disabled = false; }
                });
                row.append(remove);
            }
            list.append(row);
        }
        if (!data.items.length) list.append(element('li', 'Todavía no has enviado avistamientos. Puedes ayudar desde la comunidad.'));
        document.getElementById('pageSummary')!.textContent = data.total ? `${offset + 1}–${Math.min(offset + 20, data.total)} de ${data.total}` : '0 avistamientos';
        previous.disabled = offset === 0; next.disabled = offset + 20 >= data.total; message(feedback, '');
    } catch (error) { if (!requireLogin(error)) showError(feedback, error); }
    finally { loading = false; }
}
previous.addEventListener('click', () => { offset = Math.max(0, offset - 20); void load(); });
next.addEventListener('click', () => { offset += 20; void load(); });
void load();
