const token = localStorage.getItem("auth_token");
const API_BASE = (window.location.protocol === "file:" || (window.location.port && window.location.port !== "8000")) ? "http://127.0.0.1:8000" : "";

if (!token) {
    window.location.href = "login.html";
}

const reportForm = document.querySelector("#report-form");

const facilityInput = document.querySelector("#facility");
const categoryInput = document.querySelector("#category");
const locationInput = document.querySelector("#location-detail");
const descriptionInput = document.querySelector("#description");

const cameraInput = document.querySelector("#camera-input");
const galleryInput = document.querySelector("#gallery-input");
const takePhotoButton = document.querySelector("#take-photo-button");
const choosePhotoButton = document.querySelector("#choose-photo-button");
const selectedPhoto = document.querySelector("#selected-photo");
const fileError = document.querySelector("#file-error");

const successOverlay = document.querySelector("#report-success-overlay");
const successClose = document.querySelector("#report-success-close");
const successCloseButton = document.querySelector("#report-success-close-button");

const successFacility = document.querySelector("#success-report-facility");
const successCategory = document.querySelector("#success-report-category");
const successLocation = document.querySelector("#success-report-location");
const successDate = document.querySelector("#success-report-date");
const successSubmitted = document.querySelector("#success-report-submitted");
const successDescription = document.querySelector("#success-report-description");
const successPhoto = document.querySelector("#success-report-photo");

let selectedFile = null;

if (takePhotoButton && cameraInput) {
    takePhotoButton.addEventListener("click", function () {
        cameraInput.click();
    });
}

if (choosePhotoButton && galleryInput) {
    choosePhotoButton.addEventListener("click", function () {
        galleryInput.click();
    });
}

function handlePhoto(file) {
    if (!file) {
        return;
    }

    const allowedTypes = ["image/jpeg", "image/png", "image/webp"];
    const maxFileSize = 5 * 1024 * 1024;

    if (!allowedTypes.includes(file.type)) {
        if (fileError) fileError.textContent = "Format foto harus JPG, PNG, atau WEBP.";
        if (selectedPhoto) selectedPhoto.textContent = "";
        selectedFile = null;
        return;
    }

    if (file.size > maxFileSize) {
        if (fileError) fileError.textContent = "Ukuran foto maksimal 5 MB.";
        if (selectedPhoto) selectedPhoto.textContent = "";
        selectedFile = null;
        return;
    }

    selectedFile = file;
    if (fileError) fileError.textContent = "";
    if (selectedPhoto) selectedPhoto.textContent = `Foto dipilih: ${file.name}`;
}

if (cameraInput) {
    cameraInput.addEventListener("change", function () {
        handlePhoto(cameraInput.files[0]);
    });
}

if (galleryInput) {
    galleryInput.addEventListener("change", function () {
        handlePhoto(galleryInput.files[0]);
    });
}

function formatDate(date) {
    return new Date(date).toLocaleDateString("id-ID", {
        day: "numeric",
        month: "long",
        year: "numeric"
    });
}

function showSuccessOverlay(report) {
    successFacility.textContent = report.facility;
    successCategory.textContent = report.category;
    successLocation.textContent = report.location;
    successDate.textContent = report.date;
    if (successSubmitted) successSubmitted.textContent = report.submitted;
    successDescription.textContent = report.description;
    successPhoto.textContent = report.photo;

    successOverlay.classList.add("active");
    document.body.style.overflow = "hidden";
}

function closeSuccessOverlay() {
    successOverlay.classList.remove("active");
    document.body.style.overflow = "";
    window.location.href = "dashboard.html";
}

reportForm.addEventListener("submit", async function (event) {
    event.preventDefault();

    if (!window.DiServeBusinessRules.isSubmissionWindowOpen()) {
        alert("Pengajuan laporan hanya dapat dikirim pukul 07.00–20.00 WIB.");
        return;
    }

    const facility = facilityInput.options[facilityInput.selectedIndex].text;
    const facilityVal = facilityInput.value;
    const category = categoryInput.options[categoryInput.selectedIndex].text;
    const categoryVal = categoryInput.value;
    const location = locationInput.value.trim();
    const description = descriptionInput.value.trim();

    if (description.length < 10) {
        alert("Deskripsi masalah harus dijelaskan dengan lebih detail (minimal 10 karakter).");
        return;
    }

    const submitBtn = reportForm.querySelector("button[type='submit']");
    if (submitBtn && !submitBtn.disabled) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Mengirim...";
    } else if (submitBtn) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append("facility", facilityVal);
        formData.append("category", categoryVal);
        formData.append("location_detail", location);
        formData.append("description", description);
        if (selectedFile) {
            formData.append("photo", selectedFile);
        }

        const response = await fetch(`${API_BASE}/api/reports`, {
            method: "POST",
            headers: {
                "Authorization": `Bearer ${token}`,
                "Accept": "application/json"
            },
            body: formData
        });

        const data = await response.json();

        if (!response.ok) {
            alert(data.message || "Gagal mengirim laporan kerusakan.");
            return;
        }

        const report = {
            id: data.report.id,
            facility: facility,
            category: category,
            location: location,
            date: data.report.date || formatDate(new Date()),
            submitted: data.report.submitted || "-",
            status: "Baru",
            statusClass: "new",
            description: description,
            photo: selectedFile ? selectedFile.name : "Tidak ada foto",
            note: "Laporan telah diterima dan menunggu pemeriksaan petugas."
        };

        showSuccessOverlay(report);
    } catch (err) {
        console.error("Report submit error:", err);
        alert("Tidak dapat terhubung ke server. Silakan coba lagi.");
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = "Kirim Laporan";
        }
    }
});

successClose.addEventListener("click", closeSuccessOverlay);
successCloseButton.addEventListener("click", closeSuccessOverlay);

successOverlay.addEventListener("click", function (event) {
    if (event.target === successOverlay) {
        closeSuccessOverlay();
    }
});

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
        closeSuccessOverlay();
    }
});

async function loadFacilityOptions() {
    if (!facilityInput) return;
    const placeholder = new Option("Memuat fasilitas...", "");
    placeholder.disabled = true;
    facilityInput.replaceChildren(placeholder);
    try {
        const res = await fetch(`${API_BASE}/api/facilities`);
        if (!res.ok) throw new Error(`Facility request failed (${res.status})`);

        const data = await res.json();
        const facilities = data.data || [];
        facilityInput.replaceChildren(new Option(
            facilities.length ? "Pilih fasilitas yang bermasalah" : "Belum ada fasilitas tersedia",
            ""
        ));
        facilities.forEach(f => {
            facilityInput.appendChild(new Option(f.name, String(f.id)));
        });
        if (facilities.length === 0) {
            facilityInput.options[0].disabled = true;
        }
    } catch (error) {
        console.error("Load facility options error:", error);
        const failedOption = new Option("Gagal memuat fasilitas. Muat ulang halaman.", "");
        failedOption.disabled = true;
        facilityInput.replaceChildren(failedOption);
    }
}

loadFacilityOptions();