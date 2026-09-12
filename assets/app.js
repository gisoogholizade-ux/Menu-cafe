const cards = Array.from(document.querySelectorAll('.menu-card'));
const chips = Array.from(document.querySelectorAll('.category-chip'));
const searchInput = document.getElementById('searchInput');
const clearSearch = document.getElementById('clearSearch');
const emptyState = document.getElementById('emptyState');
const resultCount = document.getElementById('resultCount');
const sheet = document.getElementById('productSheet');
const backdrop = document.getElementById('sheetBackdrop');
const closeButton = document.getElementById('sheetClose');
let category = 'all';

function faNumber(value) {
  return String(value).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
}

function normal(value) {
  return String(value || '').toLowerCase().replace(/ي/g, 'ی').replace(/ك/g, 'ک').trim();
}

function applyFilters() {
  const query = normal(searchInput.value);
  let visible = 0;
  cards.forEach(card => {
    const categoryMatch = category === 'all' || card.dataset.category === category;
    const queryMatch = !query || normal(card.dataset.name).includes(query);
    card.hidden = !(categoryMatch && queryMatch);
    if (!card.hidden) visible += 1;
  });
  emptyState.hidden = visible > 0;
  resultCount.textContent = faNumber(visible) + ' آیتم';
}

chips.forEach(chip => {
  chip.addEventListener('click', () => {
    category = chip.dataset.category;
    chips.forEach(item => item.classList.toggle('active', item === chip));
    chip.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    applyFilters();
  });
});

searchInput.addEventListener('input', applyFilters);
clearSearch.addEventListener('click', () => {
  searchInput.value = '';
  searchInput.focus();
  applyFilters();
});

function closeSheet() {
  sheet.hidden = true;
  backdrop.hidden = true;
  document.body.style.overflow = '';
}

function openSheet(card) {
  const data = JSON.parse(card.dataset.json);
  document.getElementById('sheetCategory').textContent = data.category || '';
  document.getElementById('sheetTitle').textContent = data.nameFa || '';
  document.getElementById('sheetEn').textContent = data.nameEn || '';
  document.getElementById('sheetDescription').textContent = data.description || '';
  document.getElementById('sheetPrice').textContent = data.price || '';

  const media = document.getElementById('sheetMedia');
  media.replaceChildren();
  if (data.image) {
    const image = document.createElement('img');
    image.src = data.image;
    image.alt = data.nameFa || '';
    media.appendChild(image);
  } else {
    const fallback = document.createElement('div');
    fallback.className = 'image-fallback';
    const letter = document.createElement('b');
    letter.textContent = '赤';
    fallback.appendChild(letter);
    media.appendChild(fallback);
  }

  const tags = document.getElementById('sheetTags');
  tags.replaceChildren();
  (data.tags || []).forEach(tag => {
    const span = document.createElement('span');
    span.textContent = tag;
    tags.appendChild(span);
  });

  const availability = document.getElementById('sheetAvailability');
  availability.textContent = data.available ? 'موجود' : 'فعلاً موجود نیست';
  availability.classList.toggle('no', !data.available);
  backdrop.hidden = false;
  sheet.hidden = false;
  document.body.style.overflow = 'hidden';
}

cards.forEach(card => {
  card.addEventListener('click', () => openSheet(card));
  card.addEventListener('keydown', event => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      openSheet(card);
    }
  });
});

closeButton.addEventListener('click', closeSheet);
backdrop.addEventListener('click', closeSheet);
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && !sheet.hidden) closeSheet();
});
