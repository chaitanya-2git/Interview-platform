'use strict';
// Filters the student list on the consultancy "Book an interview" page.
document.querySelectorAll('[data-student-filter]').forEach(input => {
  const options = [...document.querySelectorAll('[data-student-option]')];
  input.addEventListener('input', () => {
    const term = input.value.trim().toLowerCase();
    options.forEach(option => { option.hidden = term !== '' && !option.dataset.search.includes(term); });
  });
});
