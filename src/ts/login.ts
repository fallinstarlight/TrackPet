import { ApiRequest } from './api/ApiRequest.js';

interface LoginBody { username: string, password: string };
interface LoginResponse { 
    status: string, 
    message: string,
    data: { 
        firstName: string, 
        surname: string, 
        username: string,
        pets: {} } 
    };

const form = document.getElementById('loginForm');
const identifier = document.getElementById('identifier');
const passwordInput = document.getElementById('password');
const identifierError = document.getElementById('identifierError');
const passwordError = document.getElementById('passwordError');
const formMessage = document.getElementById('formMessage');
const forgotPassword = document.getElementById('forgotPassword');
const signUp = document.getElementById('signUp');

form?.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!(identifier instanceof HTMLInputElement) || !(passwordInput instanceof HTMLInputElement)) {
        return;
    }

    if (identifier.value.trim() && passwordInput.value) {
        const login = new ApiRequest<LoginResponse, LoginBody>(
            'login', 'POST', { username: identifier.value, password: passwordInput.value }
        );
        const result = await login.send();
        if (result.status === 'success') {
            setFormMessage("login succesful, redirecting...");
            setTimeout(() => {
                window.location.replace("profile.html");
            }, 700);
        }
        else{
            if(result.message)
                setFormMessage(result.message, true);
            else
                setFormMessage("there was an unexpected error", true);
        }
    }
    else {
        setFormMessage("Please provide an username and password", true);
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
