const registerForm = document.querySelector("#register-form");

const nameInput = document.querySelector("#name");
const identityInput = document.querySelector("#identity-number") || document.querySelector("#identity_number");
const emailInput = document.querySelector("#email");
const passwordInput = document.querySelector("#password");
const confirmationInput = document.querySelector("#password-confirmation") || document.querySelector("#password_confirmation");

const nameError = document.querySelector("#name-error");
const identityError = document.querySelector("#identity-error");
const emailError = document.querySelector("#email-error");
const passwordError = document.querySelector("#password-error");
const confirmationError = document.querySelector("#confirmation-error");

const togglePassword = document.querySelector("#toggle-password");
const toggleConfirmation = document.querySelector("#toggle-confirmation");

const passwordIcon = togglePassword ? togglePassword.querySelector(".material-symbols-outlined") : null;
const confirmationIcon = toggleConfirmation ? toggleConfirmation.querySelector(
  ".material-symbols-outlined",
) : null;

const requirementLength = document.querySelector("#requirement-length");
const requirementLowercase = document.querySelector("#requirement-lowercase");
const requirementUppercase = document.querySelector("#requirement-uppercase");
const requirementNumber = document.querySelector("#requirement-number");
const requirementSpecial = document.querySelector("#requirement-special");

const emailPattern = /^[a-z0-9._-]+@[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}$/;
const allowedDomains = [
  "@students.undip.ac.id",
  "@lectures.undip.ac.id",
  "@staff.undip.ac.id",
  "@officer.undip.ac.id",
  "@facility.undip.ac.id",
  "@facillity.undip.ac.id",
];

function togglePasswordVisibility(input, icon, button) {
  if (!input) return;
  if (input.type === "password") {
    input.type = "text";
    if (icon) icon.textContent = "visibility_off";
    if (button) button.setAttribute("aria-label", "Sembunyikan password");
  } else {
    input.type = "password";
    if (icon) icon.textContent = "visibility";
    if (button) button.setAttribute("aria-label", "Tampilkan password");
  }
}

if (togglePassword) {
  togglePassword.addEventListener("click", function (e) {
    e.preventDefault();
    togglePasswordVisibility(passwordInput, passwordIcon, togglePassword);
  });
}

if (toggleConfirmation) {
  toggleConfirmation.addEventListener("click", function (e) {
    e.preventDefault();
    togglePasswordVisibility(
      confirmationInput,
      confirmationIcon,
      toggleConfirmation,
    );
  });
}

function updatePasswordRequirements() {
  const password = passwordInput.value;
  const hasLength = password.length >= 8;
  const hasLowercase = /[a-z]/.test(password);
  const hasUppercase = /[A-Z]/.test(password);
  const hasNumber = /[0-9]/.test(password);
  const hasSpecial = /[@$!%*?&#_]/.test(password);

  requirementLength.classList.toggle("valid", hasLength);
  requirementLowercase.classList.toggle("valid", hasLowercase);
  requirementUppercase.classList.toggle("valid", hasUppercase);
  requirementNumber.classList.toggle("valid", hasNumber);
  requirementSpecial.classList.toggle("valid", hasSpecial);
}

passwordInput.addEventListener("input", function () {
  updatePasswordRequirements();
});

registerForm.addEventListener("submit", async function (event) {
  event.preventDefault();

  nameError.textContent = "";
  identityError.textContent = "";
  emailError.textContent = "";
  passwordError.textContent = "";
  confirmationError.textContent = "";

  const name = nameInput.value.trim();
  const identityNumber = identityInput.value.trim();
  const email = emailInput.value.trim().toLowerCase();
  const password = passwordInput.value;
  const confirmation = confirmationInput.value;
  if (name === "") {
    nameError.textContent = "Nama lengkap wajib diisi";
    nameInput.focus();
    return;
  }

  if (identityNumber === "") {
    identityError.textContent = "NIM/NIP wajib diisi";
    identityInput.focus();
    return;
  }

  if (email === "") {
    emailError.textContent = "Email wajib diisi";
    emailInput.focus();
    return;
  }

  if (!emailPattern.test(email)) {
    emailError.textContent = "Format email tidak valid";
    emailInput.focus();
    return;
  }

    if (email.endsWith("@admin.undip.ac.id")) {
        emailError.textContent = "Admin tidak dapat melakukan pendaftaran akun.";
        emailInput.focus();
        return;
    }

    const isAllowedDomain = allowedDomains.some(function (domain) {
    return email.endsWith(domain);
  });

  if (!isAllowedDomain) {
    emailError.textContent = "Gunakan email resmi UNDIP untuk pengguna";
    emailInput.focus();
    return;
  }

  if (password === "") {
    passwordError.textContent = "Password wajib diisi";
    passwordInput.focus();
    return;
  }

  const hasLength = password.length >= 8;
  const hasLowercase = /[a-z]/.test(password);
  const hasUppercase = /[A-Z]/.test(password);
  const hasNumber = /[0-9]/.test(password);
  const hasSpecial = /[@$!%*?&#_]/.test(password);
  if (!hasLength) {
    passwordError.textContent = "Password minimal 8 karakter";
    passwordInput.focus();
    return;
  }

  if (!hasLowercase) {
    passwordError.textContent = "Password harus mengandung huruf kecil";
    passwordInput.focus();
    return;
  }

  if (!hasUppercase) {
    passwordError.textContent = "Password harus mengandung huruf besar";
    passwordInput.focus();
    return;
  }

  if (!hasNumber) {
    passwordError.textContent = "Password harus mengandung angka";
    passwordInput.focus();
    return;
  }

  if (!hasSpecial) {
    passwordError.textContent = "Password harus mengandung karakter khusus";
    passwordInput.focus();
    return;
  }

  if (confirmation === "") {
    confirmationError.textContent = "Konfirmasi password wajib diisi";
    confirmationInput.focus();
    return;
  }

  if (password !== confirmation) {
    confirmationError.textContent = "Konfirmasi password tidak sama";
    confirmationInput.focus();
    return;
  }

  const submitButton = registerForm.querySelector("button[type='submit']");
  if (submitButton) {
    submitButton.disabled = true;
    submitButton.textContent = "Memproses...";
  }

  const API_BASE = (window.location.protocol === "file:" || (window.location.port && window.location.port !== "8000")) ? "http://127.0.0.1:8000" : "";

  try {
    const response = await fetch(`${API_BASE}/api/auth/register`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json"
      },
      body: JSON.stringify({
        name: name,
        identity_number: identityNumber,
        email: email,
        password: password,
        password_confirmation: confirmation
      })
    });

    const data = await response.json();

    if (!response.ok) {
      if (response.status === 422 && data.errors) {
        if (data.errors.email) emailError.textContent = data.errors.email[0];
        if (data.errors.identity_number) identityError.textContent = data.errors.identity_number[0];
        if (data.errors.password) passwordError.textContent = data.errors.password[0];
        if (data.errors.name) nameError.textContent = data.errors.name[0];
        return;
      }
      alert(data.message || "Pendaftaran gagal. Silakan coba lagi.");
      return;
    }

    const notice = document.querySelector("#register-notice");
    const noticeText = document.querySelector("#register-notice-text");
    if (notice && noticeText) {
        noticeText.textContent = data.message || "Pendaftaran akun berhasil. Akun Anda menunggu persetujuan administrator.";
        notice.classList.remove("hidden");
    } else {
        alert(data.message || "Pendaftaran akun berhasil. Akun menunggu persetujuan administrator.");
    }
    registerForm.reset();
    updatePasswordRequirements();
    window.setTimeout(() => {
      window.location.href = "login.html";
    }, 1800);
  } catch (err) {
    console.error("Register error:", err);
    alert("Tidak dapat terhubung ke server. Silakan coba lagi.");
  } finally {
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = "Daftar";
    }
  }
});
