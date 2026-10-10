const token = localStorage.getItem("auth_token");
const API_BASE = (window.location.protocol === "file:" || (window.location.port && window.location.port !== "8000")) ? "http://127.0.0.1:8000" : "";

if (!token) {
    window.location.href = "login.html";
}

const reservationDetailOverlay = document.querySelector("#reservation-detail-overlay");
const reservationDetailClose = document.querySelector("#reservation-detail-close");
const reservationDetailCancel = document.querySelector("#reservation-detail-cancel");
const reservationCancelButton = document.querySelector("#reservation-cancel-button");

const detailFacility = document.querySelector("#detail-facility");
const detailFacilityName = document.querySelector("#detail-facility-name");
const detailDate = document.querySelector("#detail-date");
const detailTime = document.querySelector("#detail-time");
const detailPurpose = document.querySelector("#detail-purpose");
const detailStatus = document.querySelector("#detail-status");
const detailFile = document.querySelector("#detail-file");
const detailFileDownload = document.querySelector("#detail-file-download");
const detailSubmitted = document.querySelector("#detail-submitted");
const detailCancellationDeadline = document.querySelector("#detail-cancellation-deadline");

const tableBody = document.querySelector(".reservations-table tbody");
let selectedReservationButton = null;
let currentReservationId = null;

function showReservationMessage(message, allowRetry = false) {
    if (!tableBody) return;
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = 6;
    cell.className = "reservations-table-message";
    cell.textContent = message;
    if (allowRetry) {
        const retry = document.createElement("button");
        retry.type = "button";
        retry.className = "reservation-table-action";
        retry.textContent = "Coba lagi";
        retry.addEventListener("click", loadReservations);
        cell.append(" ", retry);
    }
    row.appendChild(cell);
    tableBody.replaceChildren(row);
    tableBody.hidden = false;
}

function openReservationDetail(button) {
    selectedReservationButton = button;
    currentReservationId = button.dataset.id;

    const facility = button.dataset.facility;
    const date = button.dataset.date;
    const time = button.dataset.time;
    const purpose = button.dataset.purpose;
    const status = button.dataset.status;
    const statusClass = button.dataset.statusClass;
    const file = button.dataset.file;
    const fileUrl = button.dataset.fileUrl;
    const submitted = button.dataset.submitted;
    const cancellationDeadline = button.dataset.cancellationDeadline;

    detailFacility.textContent = facility;
    detailFacilityName.textContent = facility;
    detailDate.textContent = date;
    detailTime.textContent = time;
    detailPurpose.textContent = purpose;
    detailStatus.textContent = status;
    detailFile.textContent = file;
    if (detailFileDownload) {
        detailFileDownload.href = fileUrl ? `${API_BASE}${fileUrl}` : "#";
        detailFileDownload.classList.toggle("hidden", !fileUrl);
    }
    detailSubmitted.textContent = submitted;
    detailCancellationDeadline.textContent = cancellationDeadline ? formatDateTime(cancellationDeadline) : "-";

    detailStatus.className = "status-" + statusClass;

    updateCancellationButton(statusClass, cancellationDeadline);

    reservationDetailOverlay.classList.add("active");
    document.body.style.overflow = "hidden";
}

function updateCancellationButton(statusClass, cancellationDeadline) {
    const allowedStatuses = ["pending", "approved"];
    if (!allowedStatuses.includes(statusClass)) {
        reservationCancelButton.style.display = "none";
        return;
    }

    if (!cancellationDeadline) {
        reservationCancelButton.style.display = "inline-flex";
        return;
    }

    const deadline = new Date(cancellationDeadline);
    const now = new Date();
    if (now <= deadline) {
        reservationCancelButton.style.display = "inline-flex";
    } else {
        reservationCancelButton.style.display = "none";
    }
}

async function cancelReservation() {
    if (!selectedReservationButton) {
        return;
    }

    const confirmed = confirm(
        "Apakah kamu yakin ingin membatalkan reservasi ini?"
    );
    if (!confirmed) {
        return;
    }

    const reservationId = currentReservationId || selectedReservationButton.dataset.id;

    try {
        const response = await fetch(`${API_BASE}/api/reservations/${reservationId}/cancel`, {
            method: "POST",
            headers: {
                "Authorization": `Bearer ${token}`,
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({
                reason: "Dibatalkan oleh pemohon."
            })
        });

        const data = await response.json();

        if (!response.ok) {
            alert(data.message || "Gagal membatalkan reservasi.");
            return;
        }

        selectedReservationButton.dataset.status = "Dibatalkan";
        selectedReservationButton.dataset.statusClass = "cancelled";

        const row = selectedReservationButton.closest("tr");
        if (row) {
            const statusElement = row.querySelector(".reservation-status");
            if (statusElement) {
                statusElement.textContent = "Dibatalkan";
                statusElement.className = "reservation-status reservation-status-cancelled";
            }
        }

        detailStatus.textContent = "Dibatalkan";
        detailStatus.className = "status-cancelled";

        reservationCancelButton.style.display = "none";
        alert("Reservasi berhasil dibatalkan.");
        loadReservations();
    } catch (err) {
        console.error("Cancel error:", err);
        alert("Terjadi kesalahan saat menghubungi server.");
    }
}

function closeReservationDetail() {
    reservationDetailOverlay.classList.remove("active");
    document.body.style.overflow = "";
    selectedReservationButton = null;
    currentReservationId = null;
}

function formatDateTime(dateTime) {
    if (!dateTime) return "-";
    const date = new Date(dateTime);
    if (isNaN(date.getTime())) return dateTime;

    return date.toLocaleDateString("id-ID", {
        day: "numeric",
        month: "long",
        year: "numeric"
    }) + ", " + date.toLocaleTimeString("id-ID", {
        hour: "2-digit",
        minute: "2-digit",
        hour12: false
    });
}

async function loadReservations() {
    if (!tableBody) return;
    showReservationMessage("Memuat reservasi...");
    try {
        const response = await fetch(`${API_BASE}/api/user/reservations`, {
            headers: {
                "Authorization": `Bearer ${token}`,
                "Accept": "application/json"
            }
        });

        const result = await response.json();
        if (!response.ok) {
            showReservationMessage(result.message || "Reservasi gagal dimuat.", true);
            return;
        }
        const reservations = Array.isArray(result.data) ? result.data : [];

        if (reservations.length === 0) {
            showReservationMessage("Belum ada pengajuan reservasi.");
            return;
        }

        const rows = reservations.map((res) => {
            const row = document.createElement("tr");
            const facilityCell = document.createElement("td");
            const facilityInfo = document.createElement("div");
            facilityInfo.className = "reservation-facility";
            const facilityName = document.createElement("strong");
            facilityName.textContent = res.facility || "Fasilitas Tidak Diketahui";
            const facilityCategory = document.createElement("span");
            facilityCategory.textContent = res.facility_category || "Fasilitas Kampus";
            facilityInfo.append(facilityName, facilityCategory);
            facilityCell.appendChild(facilityInfo);

            const dateCell = document.createElement("td");
            dateCell.textContent = res.date || "-";
            const timeCell = document.createElement("td");
            timeCell.textContent = res.time || "-";
            const purposeCell = document.createElement("td");
            purposeCell.textContent = res.purpose || "-";
            const statusCell = document.createElement("td");
            const status = document.createElement("span");
            const statusClass = ["pending", "approved", "rejected", "cancelled"].includes(res.status_class)
                ? res.status_class
                : "pending";
            status.className = `reservation-status reservation-status-${statusClass}`;
            status.textContent = res.status || "Status tidak diketahui";
            statusCell.appendChild(status);

            const actionCell = document.createElement("td");
            const button = document.createElement("button");
            button.type = "button";
            button.className = "reservation-table-action reservation-detail-button";
            button.textContent = "Lihat";
            button.dataset.id = res.id ?? "";
            button.dataset.facility = res.facility || "Fasilitas Tidak Diketahui";
            button.dataset.date = res.date || "-";
            button.dataset.time = res.time || "-";
            button.dataset.purpose = res.purpose || "-";
            button.dataset.status = res.status || "Status tidak diketahui";
            button.dataset.statusClass = statusClass;
            button.dataset.file = res.file || "Tidak ada berkas";
            button.dataset.fileUrl = res.file_url || "";
            button.dataset.submitted = res.submitted || "-";
            button.dataset.cancellationDeadline = res.cancellation_deadline || "";
            button.addEventListener("click", () => openReservationDetail(button));
            actionCell.appendChild(button);
            row.append(facilityCell, dateCell, timeCell, purposeCell, statusCell, actionCell);
            return row;
        });
        tableBody.replaceChildren(...rows);
    } catch (e) {
        console.error("Load reservations error:", e);
        showReservationMessage("Tidak dapat memuat reservasi. Periksa koneksi lalu coba lagi.", true);
    }
}

reservationCancelButton?.addEventListener("click", async (event) => {
    const restore = window.beginButtonAction(event.currentTarget, "Membatalkan...");
    if (!restore) return;
    try {
        await cancelReservation();
    } finally {
        restore();
    }
});
detailFileDownload?.addEventListener("click", async (event) => {
    event.preventDefault();
    if (detailFileDownload.getAttribute("aria-busy") === "true") return;
    detailFileDownload.setAttribute("aria-busy", "true");
    try {
        await window.downloadProtectedFile(detailFileDownload.href, detailFile.textContent);
    } catch (error) {
        console.error("Download reservation file error:", error);
        alert(error instanceof Error ? error.message : "Berkas tidak dapat diunduh.");
    } finally {
        detailFileDownload.removeAttribute("aria-busy");
    }
});
reservationDetailClose?.addEventListener("click", closeReservationDetail);
reservationDetailCancel?.addEventListener("click", closeReservationDetail);

reservationDetailOverlay?.addEventListener("click", function (event) {
    if (event.target === reservationDetailOverlay) {
        closeReservationDetail();
    }
});

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
        closeReservationDetail();
    }
});

loadReservations();