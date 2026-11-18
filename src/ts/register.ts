import { initNavigation } from './shared/ui.js';
void initNavigation();
import { ApiRequest, type ApiResponse } from './api/ApiRequest.js';
const form = document.getElementById('registerForm');
const message = document.getElementById('formMessage');
form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!(form instanceof HTMLFormElement)) return;
    const data = Object.fromEntries(new FormData(form).entries());
    const button = form.querySelector('button');
    if (button) button.disabled = true;
    const result = await new ApiRequest<ApiResponse<unknown>, typeof data>('user', 'POST', data).send();
    if (button) button.disabled = false;
    if (message) {
        message.textContent = [result.message, ...(result.status === 'error' && Array.isArray(result.data) ? result.data : [])].join(' ');
        message.classList.toggle('error', result.status === 'error');
    }
    if (result.status === 'success') window.location.replace(`login.html${location.search}`);
});
