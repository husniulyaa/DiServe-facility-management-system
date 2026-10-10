document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("reset-form");
  const error = document.getElementById("reset-error");
  const success = document.getElementById("reset-success");
  const password = document.getElementById("new-password");
  const confirmation = document.getElementById("new-password-confirmation");
  const query = new URLSearchParams(window.location.search);
  const token = query.get("token") || "";
  const email = query.get("email") || "";
  const API_BASE = (window.location.protocol === "file:" || (window.location.port && window.location.port !== "8000"))
    ? "http://127.0.0.1:8000"
    : "";

  function setError(message) {
    error.textContent = message;
  }

  [
    [password, "toggle-new-password"],
    [confirmation, "toggle-new-password-confirmation"],
  ].forEach(([input, buttonId]) => {
    const button = document.getElementById(buttonId);
    button?.addEventListener("click", () => {
      input.type = input.type === "password" ? "text" : "password";
      button.querySelector("span").textContent = input.type === "password" ? "visibility" : "visibility_off";
      button.setAttribute("aria-label", input.type === "password" ? "Tampilkan password" : "Sembunyikan password");
    });
  });

  if (!token || !email) {
    setError("Tautan reset tidak lengkap. Minta tautan baru melalui halaman lupa password.");
    form.querySelector("button[type=submit]").disabled = true;
    return;
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    setError("");

    const value = password.value;
    if (value.length < 8 || !/[a-z]/.test(value) || !/[A-Z]/.test(value) || !/[0-9]/.test(value) || !/[@$!%*?&#_]/.test(value)) {
      setError("Password harus memiliki minimal 8 karakter, huruf kecil, huruf besar, angka, dan karakter khusus.");
      return;
    }
    if (value !== confirmation.value) {
      setError("Konfirmasi password tidak sama.");
      return;
    }

    const button = form.querySelector("button[type=submit]");
    const restore = window.beginButtonAction(button, "Menyimpan...");
    if (!restore) return;

    try {
      const response = await fetch(`${API_BASE}/api/auth/reset-password`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
        },
        body: JSON.stringify({
          email,
          token,
          password: value,
          password_confirmation: confirmation.value,
        }),
      });
      const data = await response.json();
      if (!response.ok) {
        setError(data.message || "Reset password gagal. Minta tautan baru jika tautan telah kedaluwarsa.");
        return;
      }

      form.classList.add("hidden");
      success.textContent = data.message;
      success.classList.remove("hidden");
      window.setTimeout(() => {
        window.location.href = "login.html";
      }, 1800);
    } catch (requestError) {
      console.error("Reset password error:", requestError);
      setError("Tidak dapat terhubung ke server. Silakan coba lagi.");
    } finally {
      restore();
    }
  });
});
