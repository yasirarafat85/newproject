// fetch র‍্যাপার — CSRF টোকেন স্বয়ংক্রিয়ভাবে যোগ করে ও এরর একরকম করে ফেরায়।
const token = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function request(method, url, body) {
  const options = {
    method,
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
  };

  if (method !== 'GET') {
    options.headers['X-CSRF-Token'] = token();
  }

  if (body instanceof FormData) {
    options.body = body;                       // Content-Type ব্রাউজার নিজেই বসাবে (boundary সহ)
  } else if (body !== undefined) {
    options.headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(body);
  }

  const response = await fetch(url, options);
  const text = await response.text();

  let payload = null;
  try { payload = text ? JSON.parse(text) : null; } catch { /* HTML এরর পাতা */ }

  if (!response.ok) {
    const message = payload?.error?.message ?? `অনুরোধটি ব্যর্থ হয়েছে (${response.status})`;
    const error = new Error(message);
    error.status = response.status;
    error.details = payload?.error?.details ?? {};
    throw error;
  }

  return payload?.data ?? payload;
}

export const http = {
  get:   (url)        => request('GET', url),
  post:  (url, body)  => request('POST', url, body),
  patch: (url, body)  => request('PATCH', url, body),
  del:   (url)        => request('DELETE', url),
};
