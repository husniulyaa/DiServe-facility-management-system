const token = localStorage.getItem("auth_token");
const API_BASE = (window.location.protocol === "file:" || (window.location.port && window.location.port !== "8000")) ? "http://127.0.0.1:8000" : "";

if (!token) {
    window.location.href = "login.html";
}

const reservationForm = document.querySelector("#reservation-form");

const facilityInput = document.querySelector("#facility");
const startDateInput = document.querySelector("#start-date");
const endDateInput = document.querySelector("#end-date");
const startTimeInput = document.querySelector("#start-time");
const endTimeInput = document.querySelector("#end-time");
const purposeInput = document.querySelector("#purpose");
const supportingFileInput = document.querySelector("#supporting-file");
const fileError = document.querySelector("#file-error");

const successOverlay = document.querySelector("#reservation-success-overlay");
const successClose = document.querySelector("#reservation-success-close");
const successCloseButton = document.querySelector("#reservation-success-close-button");

const successFacility = document.querySelector("#success-reservation-facility");
const successDate = document.querySelector("#success-reservation-date");
const successTime = document.querySelector("#success-reservation-time");
const successSubmitted = document.querySelector("#success-reservation-submitted");
const successPurpose = document.querySelector("#success-reservation-purpose");
const successFile = document.querySelector("#success-reservation-file");

const tomorrowDate = new Date();
tomorrowDate.setDate(tomorrowDate.getDate() + 1);
const tomorrow = window.DiServeBusinessRules.localDate(tomorrowDate);

function populateTimeOptions(select, startMinutes, endMinutes) {
    if (!select) return;

    const placeholder = select.options[0]?.textContent || "Pilih jam";
    select.replaceChildren(new Option(placeholder, ""));
    for (let minutes = startMinutes; minutes <= endMinutes; minutes += 30) {
        const hour = String(Math.floor(minutes / 60)).padStart(2, "0");
        const minute = String(minutes % 60).padStart(2, "0");
        const value = `${hour}:${minute}`;
        select.add(new Option(value, value));
    }
}

populateTimeOptions(startTimeInput, 6 * 60, 22 * 60 + 30);
populateTimeOptions(endTimeInput, 6 * 60, 23 * 60);

if (startDateInput && endDateInput) {
    startDateInput.min = tomorrow;
    endDateInput.min = tomorrow;

    startDateInput.addEventListener("change", function () {
        endDateInput.min = startDateInput.value;

        if (endDateInput.value && endDateInput.value < startDateInput.value) {
            endDateInput.value = startDateInput.value;
        }
    });
}

function formatDate(date) {
    return new Date(date + "T00:00:00").toLocaleDateString("id-ID", {
        day: "numeric",
        month: "long",
        year: "numeric"
    });
}

function showSuccessOverlay(reservation) {
    successFacility.textContent = reservation.facility;
    successDate.textContent = reservation.date;
    successTime.textContent = reservation.time;
    if (successSubmitted) successSubmitted.textContent = reservation.submitted;
    successPurpose.textContent = reservation.purpose;
    successFile.textContent = reservation.file;

    successOverlay.classList.add("active");
    document.body.style.overflow = "hidden";
}

function closeSuccessOverlay() {
    successOverlay.classList.remove("active");
    document.body.style.overflow = "";
    window.location.href = "reservations.html";
}

reservationForm.addEventListener("submit", async function (event) {
    event.preventDefault();

    if (!window.DiServeBusinessRules.isSubmissionWindowOpen()) {
        alert("Pengajuan reservasi hanya dapat dikirim pukul 07.00–20.00 WIB.");
        return;
    }
    
    const facilitySelect = facilityInput;
    const selectedOption = facilitySelect.options[facilitySelect.selectedIndex];
    const facilityName = selectedOption.text;
    const facilityValue = facilitySelect.value;

    const startDate = startDateInput.value;
    const endDate = endDateInput.value;
    const startTime = startTimeInput.value;
    const endTime = endTimeInput.value;
    const purpose = purposeInput.value.trim();
    const supportingFile = supportingFileInput ? supportingFileInput.files[0] : null;

    if (startDate < tomorrow || endDate < tomorrow) {
        alert("Tanggal reservasi minimal adalah besok.");
        return;
    }
    const toMinutes = value => Number(value.split(":")[0]) * 60 + Number(value.split(":")[1]);
    if (toMinutes(startTime) < 360 || toMinutes(startTime) >= 1380 || toMinutes(endTime) < 360 || toMinutes(endTime) > 1380
        || toMinutes(startTime) % 30 !== 0 || toMinutes(endTime) % 30 !== 0) {
        alert("Waktu pemakaian harus menggunakan slot 30 menit antara pukul 06.00 dan 23.00 WIB.");
        return;
    }
    if (endDate < startDate) {
        alert("Tanggal selesai tidak boleh lebih awal dari tanggal mulai.");
        return;
    }
    if (startDate === endDate && endTime <= startTime) {
        alert("Jam selesai harus lebih dari jam mulai.");
        return;
    }
    if (purpose.length < 5) {
        alert("Keperluan harus diisi dengan jelas.");
        return;
    }

    const submitBtn = reservationForm.querySelector("button[type='submit']");
    if (submitBtn && !submitBtn.disabled) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Mengirim...";
    } else if (submitBtn) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append("facility", facilityValue);
        formData.append("start_date", startDate);
        formData.append("end_date", endDate);
        formData.append("start_time", startTime);
        formData.append("end_time", endTime);
        formData.append("purpose", purpose);
        if (supportingFile) {
            formData.append("supporting_file", supportingFile);
        }

        const response = await fetch(`${API_BASE}/api/reservations`, {
            method: "POST",
            headers: {
                "Authorization": `Bearer ${token}`,
                "Accept": "application/json"
            },
            body: formData
        });

        const data = await response.json();

        if (!response.ok) {
            alert(data.message || "Gagal membuat reservasi.");
            return;
        }

        const reservation = {
            id: data.reservation.id,
            facility: facilityName,
            startDate: startDate,
            endDate: endDate,
            date: startDate === endDate ? formatDate(startDate) : formatDate(startDate) + " - " + formatDate(endDate),
            time: startTime + " - " + endTime,
            submitted: data.reservation.submitted || "-",
            purpose: purpose,
            file: supportingFile ? supportingFile.name : "Tidak ada berkas",
            status: "Menunggu",
            statusClass: "pending",
        };

        showSuccessOverlay(reservation);
    } catch (err) {
        console.error("Reservation submit error:", err);
        alert("Tidak dapat terhubung ke server. Silakan coba lagi.");
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = "Ajukan Reservasi";
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
            facilities.length ? "Pilih fasilitas" : "Belum ada fasilitas tersedia",
            ""
        ));
        facilities.forEach(f => {
            const opt = new Option(f.name, String(f.id));
            facilityInput.appendChild(opt);
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