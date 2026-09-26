document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('loginForm');
  const identifier = document.getElementById('identifier');
  const password = document.getElementById('password');
  const identifierError = document.getElementById('identifierError');
  const passwordError = document.getElementById('passwordError');
  const formMessage = document.getElementById('formMessage');
  const forgotPassword = document.getElementById('forgotPassword');
  const signUp = document.getElementById('signUp');

  // Simple email regex for validation
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function clearErrors() {
    identifierError.textContent = '';
    passwordError.textContent = '';
    identifier.classList.remove('input--error');
    password.classList.remove('input--error');
    formMessage.textContent = '';
    formMessage.classList.remove('error');
  }

  function validate() {
    let valid = true;

    if (!identifier.value.trim()) {
      identifierError.textContent = 'Please enter your username or email.';
      identifier.classList.add('input--error');
      valid = false;
    } else if (identifier.value.includes('@') && !emailRegex.test(identifier.value.trim())) {
      identifierError.textContent = 'Please enter a valid email address.';
      identifier.classList.add('input--error');
      valid = false;
    }

    if (!password.value) {
      passwordError.textContent = 'Please enter your password.';
      password.classList.add('input--error');
      valid = false;
    } else if (password.value.length < 6) {
      passwordError.textContent = 'Password must be at least 6 characters.';
      password.classList.add('input--error');
      valid = false;
    }

    return valid;
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    clearErrors();

    if (!validate()) {
      formMessage.textContent = 'Please fix the errors above.';
      formMessage.classList.add('error');
      return;
    }

    // Simulated success — replace with actual login logic
    formMessage.textContent = 'Logging in...';
    setTimeout(() => {
      formMessage.textContent = 'Login successful! Redirecting...';
      formMessage.classList.remove('error');
    }, 700);
  });

  forgotPassword.addEventListener('click', () => {
    const value = identifier.value.trim();
    if (!value) {
      formMessage.textContent = 'Enter your email above, then click "Forgot password".';
      formMessage.classList.add('error');
      return;
    }
    formMessage.textContent = `Password reset link sent to ${value} (demo).`;
    formMessage.classList.remove('error');
  });

  signUp.addEventListener('click', () => {
    formMessage.textContent = 'Redirecting to sign up page... (demo)';
    formMessage.classList.remove('error');
  });

  // Clear individual field error on input
  identifier.addEventListener('input', () => {
    identifierError.textContent = '';
    identifier.classList.remove('input--error');
  });

  password.addEventListener('input', () => {
    passwordError.textContent = '';
    password.classList.remove('input--error');
  });
});