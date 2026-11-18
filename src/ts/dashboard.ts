import type { Page, LostReport, Pet, PublicUser } from './shared/models.js';
import { api, element, initNavigation, message, petCard, reportCard, showError, userCard } from './shared/ui.js';
const results = document.getElementById('results')!;
const feedback = document.getElementById('feedback');
const search = document.getElementById('search') as HTMLInputElement;
const mine = document.getElementById('mine') as HTMLInputElement;
const previous = document.getElementById('previous') as HTMLButtonElement;
const next = document.getElementById('next') as HTMLButtonElement;
const params = new URLSearchParams(location.search);
let kind = ['reports', 'pets', 'users'].includes(params.get('kind') ?? '') ? params.get('kind')! : 'reports';
let offset = 0; let total = 0; let sequence = 0; let pending: AbortController | undefined;
search.value = params.get('q')?.slice(0, 100) ?? '';
mine.checked = params.get('mine') === 'true';
const viewerPromise = initNavigation();
async function load(): Promise<void> {
    const current = ++sequence; pending?.abort(); pending = new AbortController();
    const viewer = await viewerPromise;
    if (current !== sequence) return;
    const mineLabel = document.getElementById('mineLabel')!; mineLabel.hidden = !viewer.authenticated || kind !== 'reports';
    if (!viewer.authenticated) mine.checked = false;
    document.querySelectorAll<HTMLButtonElement>('[data-kind]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.kind === kind)));
    results.setAttribute('aria-busy', 'true'); previous.disabled = true; next.disabled = true;
    results.replaceChildren(...Array.from({ length: 3 }, () => { const skeleton = element('div', '', 'skeleton'); skeleton.setAttribute('aria-hidden', 'true'); return skeleton; }));
    message(feedback, 'Cargando resultados…');
    const query = { q: search.value.trim(), limit: '12', offset: String(offset), ...(kind === 'reports' ? { mine: String(mine.checked) } : { kind }) };
    const address = new URL(location.href); address.search = new URLSearchParams({ kind, q: query.q, ...(mine.checked && kind === 'reports' ? { mine: 'true' } : {}) }).toString();
    history.replaceState(null, '', address);
    try {
        const data = await api<Page<LostReport | Pet | PublicUser>>(kind === 'reports' ? 'reports' : 'directory', 'GET', undefined, query, pending.signal);
        if (current !== sequence) return;
        total = data.total;
        if (offset >= total && offset > 0) { offset = Math.max(0, Math.floor((total - 1) / 12) * 12); void load(); return; }
        results.replaceChildren(...data.items.map(item => kind === 'reports' ? reportCard(item as LostReport) : kind === 'pets' ? petCard(item as Pet) : userCard(item as PublicUser)));
        if (!data.items.length) results.append(element('p', mine.checked ? 'Todavía no has abierto búsquedas. Puedes crear una desde tu cuenta.' : 'No encontramos resultados. Prueba con otro nombre o zona.', 'empty'));
        document.getElementById('pageSummary')!.textContent = total ? `${offset + 1}–${Math.min(offset + 12, total)} de ${total}` : '0 resultados';
        previous.disabled = offset === 0; next.disabled = offset + 12 >= total;
        message(feedback, '');
    } catch (error) { if (current === sequence) { results.replaceChildren(); showError(feedback, error); } }
    finally { if (current === sequence) results.setAttribute('aria-busy', 'false'); }
}
document.querySelectorAll<HTMLButtonElement>('[data-kind]').forEach(button => button.addEventListener('click', () => { kind = button.dataset.kind!; offset = 0; void load(); }));
document.getElementById('searchForm')!.addEventListener('submit', event => { event.preventDefault(); offset = 0; void load(); });
document.getElementById('clearSearch')!.addEventListener('click', () => { search.value = ''; offset = 0; void load(); });
mine.addEventListener('change', () => { offset = 0; void load(); });
previous.addEventListener('click', () => { offset = Math.max(0, offset - 12); void load(); });
next.addEventListener('click', () => { if (offset + 12 < total) { offset += 12; void load(); } });
void api<Record<string, number>>('stats').then(stats => { for (const [key, value] of Object.entries(stats)) { const node = document.getElementById(key); if (node) node.textContent = String(value); } }).catch(() => { document.getElementById('stats')!.setAttribute('aria-label', 'El resumen no está disponible'); }).finally(() => document.getElementById('stats')!.setAttribute('aria-busy', 'false'));
void load();
