import type { Pet, PublicUser } from './shared/models.js';
import { api, element, image, initNavigation, message, petCard, showError } from './shared/ui.js';
void initNavigation();
const feedback = document.getElementById('feedback');
async function load(): Promise<void> {
    try {
        const userId = new URLSearchParams(location.search).get('userId') ?? '';
        const user = await api<PublicUser>('user', 'GET', undefined, { userId });
        const pets = await api<Pet[]>('pet', 'GET', undefined, { ownerId: user.userId });
        const header = document.getElementById('userHeader')!; const heading = element('div', '', 'report-heading'); const copy = element('div');
        copy.append(element('h1', `@${user.username}`), element('p', `${user.firstName} ${user.surname}`), element('p', `${pets.length} mascotas registradas`, 'muted'));
        heading.append(image(user.profilePhoto, user.username), copy); header.replaceChildren(heading); header.hidden = false;
        const list = document.getElementById('pets')!; list.replaceChildren(...pets.map(pet => petCard(pet)));
        if (!pets.length) list.append(element('p', 'Este usuario aún no tiene mascotas registradas.', 'empty'));
        document.getElementById('petSection')!.hidden = false; message(feedback, '');
    } catch (error) { showError(feedback, error); }
}
void load();
