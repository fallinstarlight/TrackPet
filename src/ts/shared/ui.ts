import { ApiRequest, type ApiResponse } from '../api/ApiRequest.js';
import type { LostReport, Pet, PublicUser, Viewer } from './models.js';
export class ApiFailure extends Error {
    constructor(message: string, public statusCode: number, public data: unknown) { super(message); }
}
export async function api<T>(resource: string, method: 'GET' | 'POST' | 'PATCH' | 'DELETE' = 'GET', body?: unknown,
    params: Record<string, string> = {}, signal?: AbortSignal): Promise<T> {
    const request = new ApiRequest<ApiResponse<T>, unknown>(resource, method, body, params);
    const result = await request.send(signal);
    if (result.status !== 'success') throw new ApiFailure(result.message, request.statusCode, result.data);
    return result.data;
}
export function element<K extends keyof HTMLElementTagNameMap>(tag: K, text = '', className = ''): HTMLElementTagNameMap[K] {
    const node = document.createElement(tag); node.textContent = text; node.className = className; return node;
}
export function link(text: string, href: string, className = 'btn btn--outline'): HTMLAnchorElement {
    const node = element('a', text, className); node.href = href; return node;
}
export function pageUrl(page: string, params: Record<string, string>): string { return `${page}?${new URLSearchParams(params)}`; }
export function message(node: HTMLElement | null, text: string, error = false): void {
    if (node) { node.textContent = text; node.classList.toggle('error', error); }
}
export function showError(node: HTMLElement | null, error: unknown): void {
    const details = error instanceof ApiFailure && Array.isArray(error.data) ? error.data.filter(value => typeof value === 'string').join(' ') : '';
    message(node, `${error instanceof Error ? error.message : 'No pudimos completar la solicitud.'} ${details}`.trim(), true);
}
export async function busy(form: HTMLFormElement, action: () => Promise<void>, output: HTMLElement | null): Promise<void> {
    const buttons = Array.from(form.querySelectorAll<HTMLButtonElement>('button'));
    buttons.forEach(button => { button.disabled = true; });
    try { await action(); } catch (error) { showError(output, error); }
    finally { buttons.forEach(button => { button.disabled = false; }); }
}
export function image(path: string | null | undefined, alt: string, className = 'pet-image'): HTMLImageElement | HTMLElement {
    if (path && /^assets\/img\/uploads\/[a-f0-9]{32}\.(jpg|jpeg|png|gif|webp)$/.test(path)) {
        const node = element('img', '', className); node.src = path; node.alt = alt; node.loading = 'lazy'; return node;
    }
    const node = element('div', alt.slice(0, 1).toLocaleUpperCase() || '•', `${className} image-placeholder`);
    node.setAttribute('aria-label', `Sin foto de ${alt}`); return node;
}
export function formatDate(value: string | null | undefined, seconds = false): string {
    if (!value) return 'Sin fecha';
    return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: seconds ? 'medium' : 'short' }).format(new Date(value));
}
export function localDate(value = new Date()): string {
    const local = new Date(value.getTime() - value.getTimezoneOffset() * 60000); return local.toISOString().slice(0, 19);
}
export function isoDate(value: string): string {
    const date = new Date(value);
    if (!Number.isFinite(date.getTime())) throw new Error('Selecciona una fecha y hora válidas.');
    return date.toISOString();
}
export function requestId(): string {
    const bytes = crypto.getRandomValues(new Uint8Array(16)); bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
    const hex = Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
export function statusLabel(status: LostReport['status']): string { return { open: 'En búsqueda', recovered: 'Recuperada', cancelled: 'Búsqueda cancelada' }[status]; }
export function badge(status: LostReport['status']): HTMLElement { return element('span', statusLabel(status), `badge badge--${status}`); }
export function reportCard(report: LostReport): HTMLElement {
    const card = element('article', '', 'result-card');
    const body = element('div', '', 'result-card__body');
    const title = link(report.petName, pageUrl('report.html', { reportId: report.reportId }), 'card-title');
    body.append(badge(report.status), title, element('p', `${report.petSpecies} · ${report.petColour}`, 'muted'), element('p', report.lastSeenArea),
        element('p', `Desde ${formatDate(report.lostAt)}`, 'small muted'), element('p', `${report.sightingsCount} avistamientos recibidos`, 'small'),
        link(report.canTrack ? 'Seguir mi búsqueda' : 'Ver reporte y ayudar', pageUrl(report.canTrack ? 'tracking.html' : 'report.html', { reportId: report.reportId })));
    card.append(image(report.petPhoto, report.petName), body); return card;
}
export function petCard(pet: Pet): HTMLElement {
    const card = element('article', '', 'result-card'); const body = element('div', '', 'result-card__body');
    body.append(element('h3', pet.petName), element('p', `${pet.petSpecies} · ${pet.petColour}`, 'muted'), Number(pet.isLost) ? badge('open') : element('span', 'En casa', 'badge badge--recovered'));
    if (pet.petDescription) body.append(element('p', pet.petDescription, 'preserve-lines'));
    if (pet.ownerUsername) body.append(link(`@${pet.ownerUsername}`, pageUrl('user.html', { userId: pet.ownerId }), 'text-link'));
    if (pet.reportId) body.append(link('Ver búsqueda', pageUrl('report.html', { reportId: pet.reportId })));
    card.append(image(pet.petPhoto, pet.petName), body); return card;
}
export function userCard(user: PublicUser): HTMLElement {
    const card = element('article', '', 'result-card'); const body = element('div', '', 'result-card__body');
    body.append(link(`@${user.username}`, pageUrl('user.html', { userId: user.userId }), 'card-title'), element('p', `${user.firstName} ${user.surname}`),
        element('p', `${user.petCount ?? 0} mascotas · ${user.lostCount ?? 0} en búsqueda`, 'muted'));
    card.append(image(user.profilePhoto, user.username), body); return card;
}
export async function initNavigation(): Promise<Viewer> {
    try {
        const viewer = await api<Viewer>('viewer');
        const account = document.getElementById('accountLink') as HTMLAnchorElement | null;
        if (account && viewer.authenticated) { account.href = 'profile.html'; account.textContent = 'Mi cuenta'; }
        document.querySelectorAll<HTMLAnchorElement>('.site-nav nav a').forEach(anchor => { if (new URL(anchor.href).pathname === location.pathname) anchor.setAttribute('aria-current', 'page'); else anchor.removeAttribute('aria-current'); });
        return viewer;
    } catch { return { authenticated: false }; }
}
export function loginUrl(): string { return pageUrl('login.html', { next: location.pathname.split('/').pop() + location.search }); }
export function safeNext(): string {
    const candidate = new URLSearchParams(location.search).get('next');
    if (!candidate) return 'profile.html';
    try {
        const url = new URL(candidate, location.href);
        const allowed = ['profile.html', 'index.html', 'report.html', 'tracking.html', 'new-report.html', 'sightings.html', 'user.html'];
        if (url.origin === location.origin && allowed.includes(url.pathname.slice(1))) return url.pathname + url.search;
    } catch { /* Invalid destinations fall back to the current account. */ }
    return 'profile.html';
}
export function requireLogin(error: unknown): boolean {
    if (error instanceof ApiFailure && error.statusCode === 401) { location.replace(loginUrl()); return true; }
    return false;
}
export function renderReportHeader(node: HTMLElement, report: LostReport): void {
    const heading = element('div', '', 'report-heading'); const copy = element('div');
    copy.append(badge(report.status), element('h1', report.petName), element('p', `${report.petSpecies} · ${report.petColour}`, 'muted'),
        element('p', report.lastSeenArea), element('p', `Desaparición: ${formatDate(report.lostAt)}`, 'small muted'),
        link(`Publicado por @${report.ownerUsername}`, pageUrl('user.html', { userId: report.ownerId }), 'text-link'));
    heading.append(image(report.petPhoto, report.petName), copy); node.replaceChildren(heading);
    if (report.details) node.append(element('p', report.details, 'preserve-lines'));
    if (report.closedAt) node.append(element('p', `Cierre: ${formatDate(report.closedAt)}`, 'small muted'));
    node.hidden = false;
}

/** In-page confirmation: keeps keyboard focus and avoids blocking the event loop. */
export function confirmAction(text: string, confirmLabel = 'Confirmar'): Promise<boolean> {
    return new Promise(resolve => {
        const previous = document.activeElement;
        const dialog = element('dialog', '', 'confirmation');
        const title = element('h2', 'Confirmar acción'); title.id = `confirmation-${requestId()}`;
        const description = element('p', text); description.id = `${title.id}-description`;
        dialog.setAttribute('aria-labelledby', title.id); dialog.setAttribute('aria-describedby', description.id);
        const actions = element('div', '', 'actions');
        const cancel = element('button', 'Volver', 'btn btn--outline'); cancel.type = 'button';
        const accept = element('button', confirmLabel, 'btn btn--primary'); accept.type = 'button';
        actions.append(cancel, accept); dialog.append(title, description, actions); document.body.append(dialog);
        let settled = false;
        const finish = (value: boolean) => {
            if (settled) return; settled = true;
            if (typeof dialog.close === 'function' && dialog.open) dialog.close();
            dialog.remove();
            if (previous instanceof HTMLElement && previous.isConnected) previous.focus();
            resolve(value);
        };
        cancel.addEventListener('click', () => finish(false)); accept.addEventListener('click', () => finish(true));
        dialog.addEventListener('cancel', event => { event.preventDefault(); finish(false); });
        dialog.addEventListener('close', () => finish(false));
        if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
        cancel.focus();
    });
}
