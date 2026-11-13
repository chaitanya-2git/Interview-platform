// "Continue with Google": Firebase opens the Google popup, then the Firebase ID token is posted
// to the server, which verifies it and signs the candidate in (or asks for profile details).
import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js';
import { getAuth, GoogleAuthProvider, signInWithPopup, signOut } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js';

const root = document.querySelector('[data-google-signin]');
if (root) {
  const auth = getAuth(initializeApp({ apiKey: root.dataset.apiKey, authDomain: root.dataset.authDomain, projectId: root.dataset.projectId }));
  const provider = new GoogleAuthProvider();
  provider.setCustomParameters({ prompt: 'select_account' });
  const button = root.querySelector('[data-google-button]');
  const form = root.querySelector('[data-google-form]');
  const errorBox = root.querySelector('[data-google-error]');
  const label = button.querySelector('span');
  const idle = label.textContent;

  const messages = {
    'auth/popup-blocked': 'Your browser blocked the Google window. Allow pop-ups for this site and try again.',
    'auth/network-request-failed': 'Network problem. Check your connection and try again.',
    'auth/unauthorized-domain': 'Google sign-in is not enabled for this website address.',
    'auth/operation-not-allowed': 'Google sign-in is not available right now.',
  };
  const quiet = new Set(['auth/popup-closed-by-user', 'auth/cancelled-popup-request', 'auth/user-cancelled']);

  button.addEventListener('click', async () => {
    errorBox.hidden = true;
    button.disabled = true;
    label.textContent = 'Opening Google…';
    try {
      const result = await signInWithPopup(auth, provider);
      label.textContent = 'Signing you in…';
      form.elements.id_token.value = await result.user.getIdToken();
      await signOut(auth); // the app keeps its own session
      form.submit();
    } catch (error) {
      if (!quiet.has(error?.code)) {
        console.warn('Google sign-in:', error?.code || error);
        errorBox.textContent = messages[error?.code] || 'Google sign-in failed. Please try again.';
        errorBox.hidden = false;
      }
      button.disabled = false;
      label.textContent = idle;
    }
  });
}
