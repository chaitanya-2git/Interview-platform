// BluJay Help: floating assistant with ready answers and free-text questions (answered by the server).
(() => {
  const root = document.querySelector('[data-helpbot]');
  const dataNode = document.getElementById('helpbot-data');
  if (!root || !dataNode) return;
  let data;
  try { data = JSON.parse(dataNode.textContent); } catch { return; }

  const panel = root.querySelector('#helpbot-panel');
  const launcher = root.querySelector('.helpbot-launcher');
  const list = root.querySelector('[data-helpbot-messages]');
  const chips = root.querySelector('[data-helpbot-chips]');
  const form = root.querySelector('[data-helpbot-form]');
  const input = root.querySelector('[data-helpbot-input]');
  const nudge = root.querySelector('[data-helpbot-nudge]');
  const storeKey = 'blujay-help-' + data.audience;
  const store = {
    get() { try { return JSON.parse(sessionStorage.getItem(storeKey) || 'null'); } catch { return null; } },
    set(value) { try { sessionStorage.setItem(storeKey, JSON.stringify(value)); } catch { /* storage unavailable */ } },
    flag(name, value) { try { if (value === undefined) return localStorage.getItem(name); localStorage.setItem(name, value); } catch { return null; } },
  };
  const saved = store.get();
  let messages = Array.isArray(saved?.messages) ? saved.messages.slice(-30) : [];
  let busy = false;

  const save = () => store.set({ messages: messages.slice(-30), open: !panel.hidden });
  const href = path => data.base.replace(/\/$/, '') + '/' + String(path).replace(/^\//, '');

  function bubble(message) {
    const item = document.createElement('div');
    item.className = 'helpbot-msg ' + (message.role === 'user' ? 'from-user' : 'from-bot');
    const text = document.createElement('div');
    text.className = 'helpbot-text';
    String(message.text).split('\n').forEach((line, index) => {
      if (index) text.appendChild(document.createElement('br'));
      text.appendChild(document.createTextNode(line));
    });
    item.appendChild(text);
    if (message.links?.length) {
      const links = document.createElement('div');
      links.className = 'helpbot-links';
      message.links.forEach(link => {
        const anchor = document.createElement('a');
        anchor.href = href(link.path);
        anchor.textContent = link.label + ' →';
        anchor.addEventListener('click', () => { panel.hidden = false; save(); });
        links.appendChild(anchor);
      });
      item.appendChild(links);
    }
    return item;
  }

  function render() {
    list.replaceChildren(...messages.map(bubble));
    root.classList.toggle('has-chat', messages.length > 1);
    list.scrollTop = list.scrollHeight;
  }

  function greet() {
    const hello = data.name ? `Hi ${data.name}! ` : 'Hi! ';
    const intro = data.audience === 'guest'
      ? 'I\'m BluJay Help. I can guide you to register, sign in, reset your password and book interviews.'
      : 'I\'m BluJay Help. Ask me how to do anything in this app.';
    messages = [{ role: 'bot', text: hello + intro + '\nTap a question below, or type your own in English or Telugu.' }];
    save();
    render();
  }

  // Show the start of a new answer (long answers would otherwise open at their last line).
  function add(message) {
    messages.push(message); save();
    const node = bubble(message);
    list.appendChild(node);
    root.classList.toggle('has-chat', messages.length > 1);
    list.scrollTop = message.role === 'bot' ? Math.max(0, node.offsetTop - 12) : list.scrollHeight;
  }

  function setOpen(open) {
    panel.hidden = !open;
    launcher.setAttribute('aria-expanded', String(open));
    root.classList.toggle('is-open', open);
    if (nudge) nudge.hidden = true;
    if (open) {
      store.flag('blujay-help-seen', '1');
      if (!messages.length) greet(); else render();
      if (window.matchMedia('(min-width: 761px)').matches) input.focus();
    }
    save();
  }

  data.faqs.forEach(faq => {
    const chip = document.createElement('button');
    chip.type = 'button';
    chip.className = 'helpbot-chip';
    chip.textContent = faq.q;
    chip.addEventListener('click', () => {
      if (busy) return;
      add({ role: 'user', text: faq.q });
      add({ role: 'bot', text: faq.a, links: faq.links });
    });
    chips.appendChild(chip);
  });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    const question = input.value.trim();
    if (!question || busy) return;
    const history = messages.filter(message => message.role === 'user' || message.role === 'bot').slice(-6).map(({ role, text }) => ({ role, text }));
    input.value = '';
    add({ role: 'user', text: question });
    busy = true;
    form.classList.add('is-busy');
    const typing = document.createElement('div');
    typing.className = 'helpbot-msg from-bot helpbot-typing';
    typing.innerHTML = '<div class="helpbot-text"><span></span><span></span><span></span></div>';
    list.appendChild(typing);
    list.scrollTop = list.scrollHeight;
    const slow = setTimeout(() => { const note = document.createElement('small'); note.className = 'helpbot-slow'; note.textContent = 'Still looking, one moment…'; typing.appendChild(note); }, 6000);
    try {
      const body = new FormData();
      body.append('_token', root.dataset.token);
      body.append('question', question);
      body.append('history', JSON.stringify(history));
      const response = await fetch(data.endpoint, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const result = await response.json().catch(() => ({}));
      typing.remove();
      if (result.ok) add({ role: 'bot', text: result.answer, links: result.links || [] });
      else add({ role: 'bot', text: result.message || (response.status === 419 ? 'This page has expired. Please refresh the page and ask again.' : 'Sorry, something went wrong. Please try one of the questions below.') });
    } catch {
      typing.remove();
      add({ role: 'bot', text: 'I could not connect. Please check your internet and try again, or tap one of the questions below.' });
    } finally {
      clearTimeout(slow);
      busy = false;
      form.classList.remove('is-busy');
    }
  });

  root.querySelectorAll('[data-helpbot-toggle]').forEach(button => button.addEventListener('click', () => setOpen(panel.hidden)));
  root.querySelector('[data-helpbot-reset]')?.addEventListener('click', () => { greet(); input.focus(); });
  root.querySelector('[data-helpbot-nudge-close]')?.addEventListener('click', () => { nudge.hidden = true; store.flag('blujay-help-seen', '1'); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) { setOpen(false); launcher.focus(); } });

  // Re-open after following a link from the bot; first-time visitors on sign-in pages get a small tip.
  if (saved?.open && messages.length) setOpen(true);
  else if (nudge && data.audience === 'guest' && !store.flag('blujay-help-seen')) {
    setTimeout(() => { if (panel.hidden) nudge.hidden = false; }, 1200);
    setTimeout(() => { nudge.hidden = true; }, 12000);
  }
})();
