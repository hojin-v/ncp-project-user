const state = {
  city: 'seoul',
  currentWeather: null,
};

const el = {
  cityTabs: document.querySelector('#cityTabs'),
  cityName: document.querySelector('#cityName'),
  weatherSummary: document.querySelector('#weatherSummary'),
  weatherSubline: document.querySelector('#weatherSubline'),
  weatherIcon: document.querySelector('#weatherIcon'),
  temperature: document.querySelector('#temperature'),
  humidity: document.querySelector('#humidity'),
  wind: document.querySelector('#wind'),
  rain: document.querySelector('#rain'),
  feelsLike: document.querySelector('#feelsLike'),
  forecastCount: document.querySelector('#forecastCount'),
  forecastList: document.querySelector('#forecastList'),
  weatherSnapshot: document.querySelector('#weatherSnapshot'),
  commentForm: document.querySelector('#commentForm'),
  formStatus: document.querySelector('#formStatus'),
  commentList: document.querySelector('#commentList'),
  commentCount: document.querySelector('#commentCount'),
  commentTemplate: document.querySelector('#commentTemplate'),
};

document.addEventListener('DOMContentLoaded', async () => {
  await loadWeather();
  await loadComments();
  el.commentForm.addEventListener('submit', submitComment);
});

async function loadWeather(city = state.city) {
  const data = await apiGet(`/api/weather.php?city=${encodeURIComponent(city)}`);
  state.city = data.selected_city;
  state.currentWeather = data.current;
  renderCityTabs(data.cities, data.selected_city);
  renderWeather(data.current, data.forecast);
}

function renderCityTabs(cities, selected) {
  el.cityTabs.replaceChildren(...cities.map((city) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = city.name;
    button.setAttribute('aria-selected', city.code === selected ? 'true' : 'false');
    button.addEventListener('click', () => loadWeather(city.code).catch(showFormError));
    return button;
  }));
}

function renderWeather(current, forecast) {
  const condition = current.precipitation_type_name === '없음' ? forecast[0]?.condition || '맑음' : current.precipitation_type_name;
  el.cityName.textContent = `${current.city_name}, 대한민국`;
  el.weatherSummary.textContent = condition;
  el.weatherSubline.textContent = `체감 ${valueOrDash(current.temperature_c)}°C · 기준 ${formatDateTime(current.observed_at)}`;
  el.weatherIcon.textContent = weatherIcon(condition);
  el.temperature.textContent = valueOrDash(current.temperature_c);
  el.humidity.textContent = current.humidity_percent === null ? '-' : `${current.humidity_percent}%`;
  el.wind.textContent = current.wind_speed_ms === null ? '-' : `${current.wind_speed_ms} m/s`;
  el.rain.textContent = current.precipitation_1h || current.precipitation_type_name || '-';
  el.feelsLike.textContent = current.temperature_c === null ? '-' : `${valueOrDash(current.temperature_c)}°`;
  el.weatherSnapshot.value = current.summary;
  el.forecastCount.textContent = '7일 전체 보기⌄';
  el.commentTitle.textContent = `${current.city_name} 댓글`;

  el.forecastList.replaceChildren(...forecast.map((day, index) => {
    const card = document.createElement('article');
    card.className = 'forecast-card';
    card.innerHTML = `
      <span>${escapeHtml(dayLabel(day.label, index))}</span>
      <em aria-hidden="true">${weatherIcon(day.condition || '')}</em>
      <strong>${valueOrDash(day.temp_max_c)}° / ${valueOrDash(day.temp_min_c)}°</strong>
      <span>강수 ${day.precipitation_probability_percent ?? '-'}%</span>
    `;
    return card;
  }));
}

async function loadComments() {
  const data = await apiGet('/api/comments.php?limit=30');
  el.commentCount.textContent = String(data.total ?? data.comments.length);
  if (data.comments.length === 0) {
    const empty = document.createElement('div');
    empty.className = 'empty';
    empty.textContent = '아직 댓글이 없습니다.';
    el.commentList.replaceChildren(empty);
    return;
  }
  el.commentList.replaceChildren(...data.comments.map(renderComment));
}

function renderComment(comment) {
  const node = el.commentTemplate.content.firstElementChild.cloneNode(true);
  node.querySelector('.comment-nickname').textContent = comment.nickname;
  node.querySelector('.comment-date').textContent = formatDateTime(comment.created_at);
  node.querySelector('.comment-content').textContent = comment.content;
  node.querySelector('.comment-weather').textContent = comment.weather_snapshot || '';

  const editForm = node.querySelector('.edit-form');
  const deleteForm = node.querySelector('.delete-form');
  editForm.nickname.value = comment.nickname;
  editForm.content.value = comment.content;

  node.querySelector('.edit-button').addEventListener('click', () => {
    editForm.hidden = false;
    deleteForm.hidden = true;
  });
  node.querySelector('.delete-button').addEventListener('click', () => {
    deleteForm.hidden = false;
    editForm.hidden = true;
  });
  node.querySelector('.cancel-edit').addEventListener('click', () => {
    editForm.hidden = true;
  });
  node.querySelector('.cancel-delete').addEventListener('click', () => {
    deleteForm.hidden = true;
  });

  editForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const body = formToObject(editForm);
    body.id = comment.id;
    body.weather_snapshot = comment.weather_snapshot || '';
    await apiSend('/api/comments.php', 'PUT', body);
    await loadComments();
  });

  deleteForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    await apiSend('/api/comments.php', 'DELETE', {
      id: comment.id,
      password: deleteForm.password.value,
    });
    await loadComments();
  });

  return node;
}

async function submitComment(event) {
  event.preventDefault();
  el.formStatus.textContent = '';
  const button = el.commentForm.querySelector('button[type="submit"]');
  button.disabled = true;
  try {
    await apiSend('/api/comments.php', 'POST', formToObject(el.commentForm));
    el.commentForm.reset();
    if (state.currentWeather) {
      el.weatherSnapshot.value = state.currentWeather.summary;
    }
    el.formStatus.textContent = '저장되었습니다.';
    await loadComments();
  } catch (error) {
    showFormError(error);
  } finally {
    button.disabled = false;
  }
}

function formToObject(form) {
  return Object.fromEntries(new FormData(form).entries());
}

async function apiGet(url) {
  const response = await fetch(url, {headers: {'Accept': 'application/json'}});
  return parseResponse(response);
}

async function apiSend(url, method, body) {
  const response = await fetch(url, {
    method,
    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
    body: JSON.stringify(body),
  });
  return parseResponse(response);
}

async function parseResponse(response) {
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data.error || `HTTP ${response.status}`);
  }
  return data;
}

function showFormError(error) {
  el.formStatus.textContent = error.message || String(error);
  el.formStatus.classList.add('error');
  window.setTimeout(() => el.formStatus.classList.remove('error'), 2200);
}

function valueOrDash(value) {
  if (value === null || value === undefined || value === '') {
    return '-';
  }
  return Number.isInteger(value) ? String(value) : String(Number(value).toFixed(1)).replace(/\.0$/, '');
}

function weatherIcon(condition) {
  if (condition.includes('눈')) return '🌨️';
  if (condition.includes('비')) return '🌧️';
  if (condition.includes('흐림')) return '☁️';
  if (condition.includes('구름')) return '⛅';
  return '☀️';
}

function dayLabel(label, index) {
  if (index === 0) return '오늘';
  if (index === 1) return '내일';
  return label.split(' ').pop() || label;
}

function formatDateTime(value) {
  if (!value) return '-';
  return value.slice(5, 16).replace('-', '.');
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char]));
}
