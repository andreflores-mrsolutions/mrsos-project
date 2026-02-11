// api.js
(function () {
  const csrf = () => (window.MRS_CSRF || "");

  // fetch JSON
  window.apiJson = async function apiJson(url, payload = {}, opts = {}) {
    const res = await fetch(url, {
      method: opts.method || "POST",
      cache: "no-store",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrf(),
        ...(opts.headers || {})
      },
      body: JSON.stringify({ ...payload, csrf_token: csrf() }) // doble: header + body
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.success) throw new Error(data.error || "Error");
    return data;
  };

  // fetch FormData (uploads)
  window.apiForm = async function apiForm(url, formData, opts = {}) {
    formData = formData || new FormData();
    if (!formData.has("csrf_token")) formData.append("csrf_token", csrf());

    const res = await fetch(url, {
      method: opts.method || "POST",
      cache: "no-store",
      headers: { "X-CSRF-Token": csrf(), ...(opts.headers || {}) },
      body: formData
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.success) throw new Error(data.error || "Error");
    return data;
  };
})();
