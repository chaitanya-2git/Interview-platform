// Candidate password reset: Firebase Phone Auth sends and checks the SMS code;
// the server then verifies the Firebase ID token before allowing a new password.
import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js';
import { getAuth, RecaptchaVerifier, signInWithPhoneNumber, signOut } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js';

const phoneForm = document.querySelector('[data-reset-phone]');
const codeForm = document.querySelector('[data-reset-code]');
const errorBox = document.querySelector('[data-reset-error]');
if (phoneForm && codeForm) {
  const auth = getAuth(initializeApp({
    apiKey: phoneForm.dataset.apiKey,
    authDomain: phoneForm.dataset.authDomain,
    projectId: phoneForm.dataset.projectId,
  }));
  auth.useDeviceLanguage();

  const sendButton = phoneForm.querySelector('[data-reset-send]');
  const verifyButton = codeForm.querySelector('[data-reset-verify]');
  const resendButton = codeForm.querySelector('[data-reset-resend]');
  const timer = codeForm.querySelector('[data-reset-timer]');
  const otpInput = codeForm.querySelector('#reset-otp');
  let confirmation = null;
  let phone = '';
  let verifier = null;
  let countdown = null;

  const showError = message => { errorBox.textContent = message; errorBox.hidden = !message; };
  const busy = (button, on, label) => {
    button.disabled = on;
    button.querySelector('span').textContent = label;
  };
  const firebaseMessage = error => (console.warn('Password reset:', error?.code || error), {
    'auth/invalid-phone-number': 'That phone number is not valid. Check it and try again.',
    'auth/too-many-requests': 'Too many attempts from this device. Please try again later.',
    'auth/quota-exceeded': 'We cannot send codes right now. Please try again later or contact your administrator.',
    'auth/billing-not-enabled': 'SMS codes are not available right now. Please contact your BluJay administrator to reset your password.',
    'auth/operation-not-allowed': 'SMS codes are not available for this number. Please contact your BluJay administrator.',
    'auth/invalid-verification-code': 'That code is incorrect. Check the SMS and try again.',
    'auth/code-expired': 'That code has expired. Request a new code.',
    'auth/captcha-check-failed': 'Security check failed. Refresh the page and try again.',
    'auth/network-request-failed': 'Network problem. Check your connection and try again.',
  })[error?.code] || 'Something went wrong. Please try again.';

  const recaptcha = () => {
    verifier?.clear();
    document.getElementById('reset-recaptcha').innerHTML = '';
    verifier = new RecaptchaVerifier(auth, 'reset-recaptcha', { size: 'invisible' });
    return verifier;
  };

  const startCountdown = () => {
    let seconds = 60;
    resendButton.disabled = true;
    clearInterval(countdown);
    const tick = () => {
      timer.textContent = seconds > 0 ? `in ${seconds}s` : '';
      if (seconds-- <= 0) { clearInterval(countdown); resendButton.disabled = false; }
    };
    tick();
    countdown = setInterval(tick, 1000);
  };

  const sendCode = async () => {
    confirmation = await signInWithPhoneNumber(auth, phone, recaptcha());
    codeForm.querySelector('[data-reset-phone-label]').textContent = phone;
    phoneForm.hidden = true;
    codeForm.hidden = false;
    otpInput.value = '';
    otpInput.focus();
    startCountdown();
  };

  phoneForm.addEventListener('submit', async event => {
    event.preventDefault();
    showError('');
    busy(sendButton, true, 'Checking…');
    try {
      const body = new FormData(phoneForm);
      const response = await fetch(phoneForm.dataset.lookupUrl, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const result = await response.json().catch(() => ({ ok: false, message: 'Something went wrong. Please try again.' }));
      if (!result.ok) { showError(result.message); return; }
      phone = result.phone;
      busy(sendButton, true, 'Sending code…');
      await sendCode();
    } catch (error) {
      showError(firebaseMessage(error));
    } finally {
      busy(sendButton, false, 'Send verification code');
    }
  });

  codeForm.addEventListener('submit', async event => {
    if (codeForm.elements.id_token.value) return; // token ready: let the browser post the form
    event.preventDefault();
    showError('');
    const code = otpInput.value.replace(/\D/g, '');
    if (code.length !== 6) { showError('Enter the 6-digit code from the SMS.'); otpInput.focus(); return; }
    busy(verifyButton, true, 'Verifying…');
    try {
      const credential = await confirmation.confirm(code);
      codeForm.elements.id_token.value = await credential.user.getIdToken();
      await signOut(auth); // the app keeps its own session; Firebase is only used to prove the phone number
      codeForm.submit();
    } catch (error) {
      showError(firebaseMessage(error));
      busy(verifyButton, false, 'Verify code');
    }
  });

  resendButton.addEventListener('click', async () => {
    showError('');
    resendButton.disabled = true;
    try { await sendCode(); } catch (error) { showError(firebaseMessage(error)); resendButton.disabled = false; }
  });

  codeForm.querySelector('[data-reset-change]').addEventListener('click', () => {
    clearInterval(countdown);
    confirmation = null;
    codeForm.hidden = true;
    phoneForm.hidden = false;
    showError('');
    phoneForm.querySelector('#reset-phone').focus();
  });
}
