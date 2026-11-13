// Resume auto-fill: when a resume is chosen, the server reads it with Gemini and the form is
// pre-filled (name if empty, total experience, tech stack with years). Everything stays editable.
const form = document.querySelector('[data-resume-autofill]');
const input = form?.querySelector('input[type="file"][name="resume"]');
const status = form?.querySelector('[data-resume-ai-status]');
if (form && input && status) {
  const say = (text, tone = '') => { status.textContent = text; status.dataset.tone = tone; status.hidden = !text; };
  let request = null;

  input.addEventListener('change', async () => {
    const file = input.files?.[0];
    if (!file) return;
    if (file.size > Number(input.closest('[data-max-bytes]')?.dataset.maxBytes || 5242880)) return;
    request?.abort();
    request = new AbortController();
    say('Reading your resume to fill in your skills and experience…', 'busy');
    const body = new FormData();
    body.append('_token', form.querySelector('input[name="_token"]').value);
    body.append('resume', file);
    try {
      const response = await fetch(form.dataset.resumeAutofill, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: request.signal });
      const result = await response.json().catch(() => ({ ok: false }));
      if (!result.ok) { say(result.message || 'We could not read this resume automatically. Please fill in your details.', 'warn'); return; }

      const filled = [];
      const name = form.querySelector('#full_name');
      if (name && !name.value.trim() && result.full_name) { name.value = result.full_name; filled.push('name'); }
      const experience = form.querySelector('#experience_years');
      if (experience && result.experience_years !== null && result.experience_years !== undefined) { experience.value = result.experience_years; filled.push('total experience'); }
      const picker = form.querySelector('[data-tech-picker]');
      if (picker?.applySkills && result.skills?.length) { picker.applySkills(result.skills); filled.push(`${result.skills.length} skill${result.skills.length === 1 ? '' : 's'}`); }

      say(filled.length
        ? `Filled ${filled.join(', ')} from your resume. Please review and correct anything that's off.`
        : 'We read your resume but found nothing to fill in. Please add your details.', filled.length ? 'ok' : 'warn');
    } catch (error) {
      if (error.name !== 'AbortError') say('We could not read this resume automatically. Please fill in your details.', 'warn');
    }
  });
}
