import { ApiRequest, type ApiResponse } from './api/ApiRequest.js';
import { logout } from './logout.js';

import type { Pet } from './shared/models.js';
import { confirmAction, initNavigation, link, pageUrl } from './shared/ui.js';
void initNavigation();

type Profile = { userId: string; firstName: string; surname: string; username: string; email: string; phone: string | null; profilePhoto: string | null; pets: Pet[]; };
const message = document.getElementById('message');
const content = document.getElementById('content');
const userForm = document.getElementById('userForm') as HTMLFormElement;
const petForm = document.getElementById('petForm') as HTMLFormElement;
const petsContainer = document.getElementById('pets');

function show(result: ApiResponse<unknown>): void {
    if (!message) return;
    message.textContent = [result.message, ...(result.status === 'error' && Array.isArray(result.data) ? result.data : [])].join(' ');
    message.classList.toggle('error', result.status === 'error');
}
function field(form: HTMLFormElement, name: string): HTMLInputElement | HTMLTextAreaElement {
    return form.elements.namedItem(name) as HTMLInputElement | HTMLTextAreaElement;
}
function photo(path: string | null, image: HTMLImageElement): void {
    const valid = path !== null && /^assets\/img\/uploads\/[a-f0-9]{32}\.(jpg|jpeg|png|gif|webp)$/.test(path);
    image.hidden = !valid;
    if (valid) image.src = path;
}
async function send<T>(request: ApiRequest<ApiResponse<T>, unknown>): Promise<ApiResponse<T>> {
    const result = await request.send();
    if (request.statusCode === 401) window.location.replace('login.html');
    return result;
}
async function load(): Promise<void> {
    const result = await send(new ApiRequest<ApiResponse<Profile>>('session', 'GET'));
    if (result.status !== 'success') { show(result); return; }
    const user = result.data;
    const identity = document.getElementById('accountIdentity');
    if (identity) identity.textContent = `${user.firstName} ${user.surname} · @${user.username} · ID: ${user.userId}`;
    for (const key of ['firstName', 'surname', 'username', 'email', 'phone'] as const) field(userForm, key).value = user[key] ?? '';
    field(userForm, 'password').value = '';
    photo(user.profilePhoto, document.getElementById('profilePhoto') as HTMLImageElement);
    petsContainer?.replaceChildren();
    if (user.pets.length === 0 && petsContainer) petsContainer.textContent = 'Todavía no tienes mascotas registradas.';
    user.pets.forEach(renderPet);
    if (content) content.hidden = false;
}
function button(label: string, action: () => Promise<void> | void): HTMLButtonElement {
    const element = document.createElement('button');
    element.type = 'button'; element.className = 'btn btn--outline'; element.textContent = label;
    element.addEventListener('click', async () => {
        element.disabled = true;
        try { await action(); } finally { element.disabled = false; }
    });
    return element;
}
function renderPet(pet: Pet): void {
    const card = document.createElement('article'); card.className = 'pet-card';
    const image = document.createElement('img'); image.className = 'photo'; image.alt = pet.petName; photo(pet.petPhoto, image);
    const title = document.createElement('h3'); title.textContent = pet.petName;
    const description = document.createElement('p'); description.textContent = `${pet.petSpecies} · ${pet.petColour} · ${Number(pet.isLost) ? 'Perdida' : 'En casa'}`;
    const detail = document.createElement('p'); detail.textContent = pet.petDescription ?? '';
    const id = document.createElement('p'); id.textContent = `ID: ${pet.petId}`;
    const actions = document.createElement('div'); actions.className = 'actions';
    actions.append(
        button('Editar', () => {
            for (const [key, value] of Object.entries(pet)) {
                if (petForm.elements.namedItem(key)) field(petForm, key).value = value === null ? '' : String(value);
            }
            const title = document.getElementById('petEditorTitle'); if (title) title.textContent = 'Editar mascota';
            petForm.scrollIntoView({ behavior: 'smooth' });
        }),
        button('Transferir', async () => {
            const newOwnerId = window.prompt('UUID del nuevo dueño:');
            if (newOwnerId?.trim()) await mutate(new ApiRequest('changeOwnership', 'PATCH', { petId: pet.petId, newOwnerId: newOwnerId.trim() }));
        }),
        button('Dar de baja', async () => {
            if (await confirmAction(`¿Dar de baja a ${pet.petName}?`, 'Dar de baja')) await mutate(new ApiRequest('pet', 'DELETE', undefined, { petId: pet.petId }));
        }),
    );
    if (pet.reportId) {
        actions.append(link('Ver mapa de búsqueda', pageUrl('tracking.html', { reportId: pet.reportId })), link('Reporte público', pageUrl('report.html', { reportId: pet.reportId })));
    } else {
        actions.append(link('Reportar desaparición', pageUrl('new-report.html', { petId: pet.petId })));
    }
    const upload = document.createElement('form'); upload.className = 'stack';
    const label = document.createElement('label'); label.textContent = `Foto de ${pet.petName}`;
    const file = document.createElement('input'); file.type = 'file'; file.accept = 'image/jpeg,image/png,image/gif,image/webp'; file.required = true;
    label.append(file);
    const submit = document.createElement('button'); submit.textContent = 'Subir foto'; submit.className = 'btn btn--outline';
    upload.append(label, submit);
    upload.addEventListener('submit', async (event) => {
        event.preventDefault(); submit.disabled = true;
        try { await uploadPhoto(file, { target: 'pet', petId: pet.petId }); } finally { submit.disabled = false; }
    });
    card.append(image, title, description, detail, id, actions, upload); petsContainer?.append(card);
}
async function mutate(request: ApiRequest<ApiResponse<unknown>, unknown>): Promise<void> {
    const result = await send(request); show(result);
    if (result.status === 'success') { resetPet(); await load(); }
}
function resetPet(): void {
    petForm.reset(); field(petForm, 'petId').value = '';
    const title = document.getElementById('petEditorTitle'); if (title) title.textContent = 'Agregar mascota';
}
async function submitForm(form: HTMLFormElement, action: () => Promise<void>): Promise<void> {
    const button = form.querySelector('button[type=submit], button:not([type])') as HTMLButtonElement | null;
    if (button) button.disabled = true;
    try { await action(); } finally { if (button) button.disabled = false; }
}
userForm.addEventListener('submit', (event) => {
    event.preventDefault();
    void submitForm(userForm, async () => {
        const data = Object.fromEntries(new FormData(userForm).entries());
        if (!data.password) delete data.password;
        await mutate(new ApiRequest('user', 'PATCH', data));
    });
});
petForm.addEventListener('submit', (event) => {
    event.preventDefault();
    void submitForm(petForm, async () => {
        const data: Record<string, string | number> = {};
        new FormData(petForm).forEach((value, key) => { if (typeof value === 'string') data[key] = value; });
        for (const key of ['petWeight', 'petHeight', 'petLenght']) {
            if (data[key] === '') delete data[key]; else data[key] = Number(data[key]);
        }
        const editing = Boolean(data.petId); if (!editing) delete data.petId;
        await mutate(new ApiRequest('pet', editing ? 'PATCH' : 'POST', data));
    });
});
async function uploadPhoto(input: HTMLInputElement, params: Record<string, string>): Promise<void> {
    const file = input.files?.[0]; if (!file) return;
    const data = new FormData(); data.append('fileToUpload', file);
    await mutate(new ApiRequest('upload', 'POST', data, params));
}
const userPhotoForm = document.getElementById('userPhotoForm') as HTMLFormElement;
userPhotoForm.addEventListener('submit', (event) => {
    event.preventDefault();
    void submitForm(userPhotoForm, () => uploadPhoto(document.getElementById('userPhoto') as HTMLInputElement, { target: 'user' }));
});
document.getElementById('cancelPet')?.addEventListener('click', resetPet);
document.getElementById('logout')?.addEventListener('click', async () => { show(await logout()); });
document.getElementById('deleteAccount')?.addEventListener('click', async () => {
    if (!await confirmAction('¿Dar de baja tu cuenta? Se cerrará tu sesión.', 'Dar de baja mi cuenta')) return;
    const result = await send(new ApiRequest<ApiResponse<unknown>>('user', 'DELETE')); show(result);
    if (result.status === 'success') window.location.replace('login.html');
});
void load();
