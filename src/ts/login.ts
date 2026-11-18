import { safeNext, initNavigation } from './shared/ui.js';
import { ApiRequest } from './api/ApiRequest.js';

void initNavigation();
interface LoginBody { username: string, password: string };
interface LoginResponse { 
    status: string, 
    message: string,
    data: { 
        userId: string,
        firstName: string, 
        surname: string, 
        username: string,
        pets: unknown[] } 
    };

const form = document.getElementById('loginForm');
const identifier = document.getElementById('identifier');
const passwordInput = document.getElementById('password');
const identifierError = document.getElementById('identifierError');
const passwordError = document.getElementById('passwordError');
const formMessage = document.getElementById('formMessage');

form?.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!(identifier instanceof HTMLInputElement) || !(passwordInput instanceof HTMLInputElement)) {
        return;
    }

    if (identifier.value.trim() && passwordInput.value) {
        const login = new ApiRequest<LoginResponse, LoginBody>(
            'login', 'POST', { username: identifier.value, password: passwordInput.value }
        );
        const button = form?.querySelector('button[type=submit]');
        if (button instanceof HTMLButtonElement) button.disabled = true;
        const result = await login.send();
        if (button instanceof HTMLButtonElement) button.disabled = false;
        if (result.status === 'success') {
            setFormMessage("Sesión iniciada. Abriendo tu cuenta…");
            setTimeout(() => {
                window.location.replace(safeNext());
            }, 700);
        }
        else{
            if(result.message)
                setFormMessage(result.message, true);
            else
                setFormMessage("No pudimos iniciar sesión.", true);
        }
    }
    else {
        setFormMessage("Proporciona tu usuario y contraseña.", true);
    }

});

function setFormMessage(message: string, error: boolean = false): void {
    if (formMessage) {
        if (error)
            formMessage.classList.add('error');
        else
            formMessage.classList.remove('error');
        formMessage.textContent = message;
    }
}
