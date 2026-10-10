window.beginButtonAction = function (button, label) {
  if (!button || button.disabled || button.dataset.actionBusy === "true") {
    return null;
  }

  const originalContent = Array.from(button.childNodes, (node) => node.cloneNode(true));
  button.disabled = true;
  button.dataset.actionBusy = "true";
  button.setAttribute("aria-busy", "true");
  button.replaceChildren(document.createTextNode(label));

  return function restore() {
    button.disabled = false;
    delete button.dataset.actionBusy;
    button.removeAttribute("aria-busy");
    button.replaceChildren(...originalContent);
  };
};

window.escapeHTML = function (value) {
  return String(value ?? "").replace(/[&<>"']/g, (character) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  })[character]);
};

window.resolveApiUrl = function (url) {
  if (!url) return "";
  const apiOrigin =
    window.location.protocol === "file:" ||
    (window.location.port && window.location.port !== "8000")
      ? "http://127.0.0.1:8000"
      : window.location.origin;
  return new URL(url, apiOrigin).href;
};

window.downloadProtectedFile = async function (url, filename) {
  if (!url) throw new Error("Berkas tidak tersedia.");
  const token = localStorage.getItem("auth_token");
  if (!token) throw new Error("Sesi Anda berakhir. Silakan masuk kembali.");

  const response = await fetch(url, {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/octet-stream, application/json",
    },
  });
  if (!response.ok) {
    let message = "Berkas tidak dapat diunduh.";
    if (response.headers.get("content-type")?.includes("application/json")) {
      const data = await response.json();
      message = data.message || message;
    }
    throw new Error(message);
  }

  const objectUrl = URL.createObjectURL(await response.blob());
  const download = document.createElement("a");
  download.href = objectUrl;
  download.download = filename || "berkas";
  document.body.appendChild(download);
  download.click();
  download.remove();
  window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
};

document.querySelectorAll("#logout-button").forEach((button) => {
  button.addEventListener("click", async () => {
    if (button.disabled || button.dataset.actionBusy === "true") return;

    const token = localStorage.getItem("auth_token");
    if (!token) {
      localStorage.removeItem("user");
      window.location.replace("index.html");
      return;
    }

    const apiBase =
      window.location.protocol === "file:" ||
      (window.location.port && window.location.port !== "8000")
        ? "http://127.0.0.1:8000"
        : "";
    button.disabled = true;
    button.dataset.actionBusy = "true";
    button.setAttribute("aria-busy", "true");

    try {
      const response = await fetch(`${apiBase}/api/auth/logout`, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: "application/json",
        },
      });

      if (!response.ok && response.status !== 401) {
        const data = response.headers.get("content-type")?.includes("application/json")
          ? await response.json()
          : null;
        throw new Error(data?.message || "Logout gagal. Silakan coba lagi.");
      }

      localStorage.removeItem("auth_token");
      localStorage.removeItem("user");
      window.location.replace("index.html");
    } catch (error) {
      console.error("Logout error:", error);
      button.disabled = false;
      delete button.dataset.actionBusy;
      button.removeAttribute("aria-busy");
      alert(error instanceof Error ? error.message : "Logout gagal. Periksa koneksi lalu coba lagi.");
    }
  });
});

if (localStorage.getItem("auth_token")) {
  const originalFetch = window.fetch.bind(window);
  let redirectingAfterExpiration = false;
  window.fetch = async function (...args) {
    const response = await originalFetch(...args);
    const requestUrl = args[0] instanceof Request ? args[0].url : String(args[0]);
    const isLogoutRequest = new URL(requestUrl, window.location.href).pathname.endsWith("/api/auth/logout");
    if (response.status === 401 && !isLogoutRequest && !redirectingAfterExpiration) {
      redirectingAfterExpiration = true;
      localStorage.removeItem("auth_token");
      localStorage.removeItem("user");
      window.location.replace("login.html?expired=1");
    }
    return response;
  };
}

window.wrapButtonAction = function (name, label) {
  const action = window[name];
  if (typeof action !== "function") return;

  window[name] = async function (...args) {
    const active = document.activeElement;
    const button = active instanceof HTMLButtonElement ? active : null;
    const restore = window.beginButtonAction(button, label);
    if (!restore) return;

    try {
      return await action.apply(this, args);
    } finally {
      restore();
    }
  };
};
