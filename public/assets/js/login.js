const loginForm = document.querySelector("#login-form");
const emailInput = document.querySelector("#email");
const passwordInput = document.querySelector("#password");
const emailError = document.querySelector("#email-error");
const passwordError = document.querySelector("#password-error");
const passwordToggle = document.querySelector("#toggle-password");
const passwordIcon = passwordToggle ? passwordToggle.querySelector(".material-symbols-outlined") : null;
const loginButton = document.querySelector(".btn-login");

if (new URLSearchParams(window.location.search).get("expired") === "1") {
    passwordError.textContent = "Sesi Anda berakhir. Silakan masuk kembali.";
}

const emailPattern =
    /^[a-z0-9._-]+@[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}$/;

const allowedDomains = [
    "@students.undip.ac.id",
    "@lectures.undip.ac.id",
    "@staff.undip.ac.id",
    "@officer.undip.ac.id",
    "@facility.undip.ac.id",
    "@admin.undip.ac.id"
];

if (passwordToggle && passwordInput) {
    passwordToggle.addEventListener("click", function () {
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            if (passwordIcon) passwordIcon.textContent = "visibility_off";
            passwordToggle.setAttribute("aria-label", "Sembunyikan password");
        } else {
            passwordInput.type = "password";
            if (passwordIcon) passwordIcon.textContent = "visibility";
            passwordToggle.setAttribute("aria-label", "Tampilkan password");
        }
    });
}

loginForm.addEventListener("submit", async function (event) {
    event.preventDefault();

    emailError.textContent = "";
    passwordError.textContent = "";

    const email = emailInput.value.trim().toLowerCase();
    const password = passwordInput.value;

    if (email === "") {
        emailError.textContent = "Email wajib diisi.";
        emailInput.focus();
        return;
    }

    if (!emailPattern.test(email)) {
        emailError.textContent = "Format email tidak valid.";
        emailInput.focus();
        return;
    }

    const isOfficialEmail = allowedDomains.some(function (domain) {
        return email.endsWith(domain);
    });

    if (!isOfficialEmail) {
        emailError.textContent = "Gunakan email resmi UNDIP.";
        emailInput.focus();
        return;
    }

    if (password === "") {
        passwordError.textContent = "Password wajib diisi.";
        passwordInput.focus();
        return;
    }

    loginButton.disabled = true;
    loginButton.textContent = "Memproses...";

    const API_BASE = (window.location.protocol === "file:" || (window.location.port && window.location.port !== "8000")) ? "http://127.0.0.1:8000" : "";

    try {
        const response = await fetch(`${API_BASE}/api/auth/login`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({
                email: email,
                password: password
            })
        });

        const data = await response.json();

        if (!response.ok) {
            if (response.status === 422 && data.errors) {
                if (data.errors.email) {
                    emailError.textContent = data.errors.email[0];
                }

                if (data.errors.password) {
                    passwordError.textContent = data.errors.password[0];
                }

                return;
            }

            if (response.status === 401) {
                passwordError.textContent =
                    data.message || "Email atau password salah.";
                return;
            }

            if (response.status === 403) {
                emailError.textContent =
                    data.message || "Akun belum aktif.";
                return;
            }

            passwordError.textContent =
                data.message || "Login gagal. Silakan coba lagi.";

            return;
        }

        localStorage.setItem("auth_token", data.token);
        localStorage.setItem("user", JSON.stringify(data.user));

        switch (data.user.role) {
            case "user":
            case "pengguna":
                window.location.href = "dashboard.html";
                break;

            case "petugas":
                window.location.href = "petugas.html";
                break;

            case "admin":
                window.location.href = "admin.html";
                break;

            default:
                localStorage.removeItem("auth_token");
                localStorage.removeItem("user");

                passwordError.textContent =
                    "Role akun tidak dikenali.";
        }
    } catch (error) {
        console.error("Login error:", error);

        passwordError.textContent =
            "Tidak dapat terhubung ke server. Silakan coba lagi.";
    } finally {
        loginButton.disabled = false;
        loginButton.textContent = "Masuk";
    }
});