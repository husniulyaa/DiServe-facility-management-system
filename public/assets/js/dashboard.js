const token = localStorage.getItem("auth_token");
const storedUser = JSON.parse(localStorage.getItem("user") || "null");
const API_BASE = (window.location.protocol === "file:" || (window.location.port && window.location.port !== "8000")) ? "http://127.0.0.1:8000" : "";

if (!token || !storedUser || !["user", "pengguna"].includes(storedUser.role)) {
    window.location.replace("login.html");
}

const logoutButton = document.querySelector("#logout-button");
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

const reservationTableBody = document.querySelector("#reservation-table-body");
let selectedReservationButton = null;
let currentReservationId = null;

// User info rendering
const userNameElements = document.querySelectorAll(".dashboard-user-info strong, .dashboard-welcome h2");
if (storedUser && storedUser.name) {
    userNameElements.forEach(el => {
        if (el.tagName === "H2") {
            el.textContent = `Selamat datang kembali, ${storedUser.name.split(" ")[0]}`;
        } else {
            el.textContent = storedUser.name;
        }
    });
}

logoutButton?.addEventListener("click", async function (event) {
    window.beginButtonAction(event.currentTarget, "Keluar...");
    try {
        await fetch(`${API_BASE}/api/auth/logout`, {
            method: "POST",
            headers: {
                "Authorization": `Bearer ${token}`,
                "Accept": "application/json"
            }
        });
    } catch (e) {
        console.error("Logout error:", e);
    } finally {
        const sessionExpired = !localStorage.getItem("auth_token");
        localStorage.removeItem("auth_token");
        localStorage.removeItem("user");
        window.location.href = sessionExpired ? "login.html" : "index.html";
    }
});

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

    detailStatus.className = "dashboard-status";

    if (statusClass === "pending") {
        detailStatus.classList.add("pending");
    }
    if (statusClass === "approved") {
        detailStatus.classList.add("approved");
    }
    if (statusClass === "rejected") {
        detailStatus.classList.add("rejected");
    }
    if (statusClass === "cancelled") {
        detailStatus.classList.add("cancelled");
    }

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
            const statusElement = row.querySelector(".dashboard-status");
            if (statusElement) {
                statusElement.textContent = "Dibatalkan";
                statusElement.className = "dashboard-status cancelled";
            }
        }

        detailStatus.textContent = "Dibatalkan";
        detailStatus.className = "dashboard-status cancelled";

        reservationCancelButton.style.display = "none";
        alert("Reservasi berhasil dibatalkan.");
        loadDashboardData();
    } catch (err) {
        console.error("Cancel reservation error:", err);
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

reservationCancelButton?.addEventListener("click", async (event) => {
    const restore = window.beginButtonAction(event.currentTarget, "Membatalkan...");
    if (!restore) return;
    try {
        await cancelReservation();
    } finally {
        restore();
    }
});
reservationDetailClose?.addEventListener("click", closeReservationDetail);
reservationDetailCancel?.addEventListener("click", closeReservationDetail);

reservationDetailOverlay?.addEventListener("click", function (event) {
    if (event.target === reservationDetailOverlay) {
        closeReservationDetail();
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

// Damage Reports in Dashboard
const reportDetailOverlay = document.querySelector("#report-detail-overlay");
const reportDetailClose = document.querySelector("#report-detail-close");
const reportDetailCancel = document.querySelector("#report-detail-cancel");

const reportDetailFacility = document.querySelector("#report-detail-facility");
const reportDetailFacilityName = document.querySelector("#report-detail-facility-name");
const reportDetailCategory = document.querySelector("#report-detail-category");
const reportDetailLocation = document.querySelector("#report-detail-location");
const reportDetailDate = document.querySelector("#report-detail-date");
const reportDetailStatus = document.querySelector("#report-detail-status");
const reportDetailDescription = document.querySelector("#report-detail-description");
const reportDetailPhoto = document.querySelector("#report-detail-photo");
const reportDetailPhotoDownload = document.querySelector("#report-detail-photo-download");
const reportDetailNote = document.querySelector("#report-detail-note");

const reportTableBody = document.querySelector("#report-table-body");
const reportTotal = document.querySelector("#report-total");
const reportNew = document.querySelector("#report-new");
const reportProcessing = document.querySelector("#report-processing");
const reportCompleted = document.querySelector("#report-completed");
const reportRejected = document.querySelector("#report-rejected");

function openReportDetail(button) {
    const facility = button.dataset.facility;
    const category = button.dataset.category;
    const location = button.dataset.location;
    const date = button.dataset.date;
    const submitted = button.dataset.submitted;
    const status = button.dataset.status;
    const statusClass = button.dataset.statusClass;
    const description = button.dataset.description;
    const photo = button.dataset.photo;
    const photoUrl = button.dataset.photoUrl;
    const note = button.dataset.note;

    reportDetailFacility.textContent = facility;
    reportDetailFacilityName.textContent = facility;
    reportDetailCategory.textContent = category;
    reportDetailLocation.textContent = location;
    reportDetailDate.textContent = submitted || date;
    reportDetailStatus.textContent = status;
    reportDetailDescription.textContent = description;
    reportDetailPhoto.textContent = photo;
    if (reportDetailPhotoDownload) {
        reportDetailPhotoDownload.href = photoUrl ? `${API_BASE}${photoUrl}` : "#";
        reportDetailPhotoDownload.classList.toggle("hidden", !photoUrl);
    }
    reportDetailNote.textContent = note;

    reportDetailStatus.className = "report-status " + statusClass;

    reportDetailOverlay.classList.add("active");
    document.body.style.overflow = "hidden";
}

function closeReportDetail() {
    reportDetailOverlay.classList.remove("active");
    document.body.style.overflow = "";
}

document.addEventListener("click", function (event) {
    const button = event.target.closest(".report-detail-button");
    if (!button) {
        return;
    }
    openReportDetail(button);
});

reportDetailClose.addEventListener("click", closeReportDetail);
reportDetailCancel.addEventListener("click", closeReportDetail);
reportDetailPhotoDownload?.addEventListener("click", async (event) => {
    event.preventDefault();
    if (reportDetailPhotoDownload.getAttribute("aria-busy") === "true") return;
    reportDetailPhotoDownload.setAttribute("aria-busy", "true");
    try {
        await window.downloadProtectedFile(reportDetailPhotoDownload.href, reportDetailPhoto.textContent);
    } catch (error) {
        console.error("Download report photo error:", error);
        alert(error instanceof Error ? error.message : "Foto tidak dapat diunduh.");
    } finally {
        reportDetailPhotoDownload.removeAttribute("aria-busy");
    }
});

reportDetailOverlay.addEventListener("click", function (event) {
    if (event.target === reportDetailOverlay) {
        closeReportDetail();
    }
});

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
        closeReservationDetail();
        closeReportDetail();
    }
});

let dashboardLoadId = 0;
let dashboardController = null;

function setTableState(tableBody, message, colspan, retry = false) {
    if (!tableBody) return;

    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = colspan;
    cell.textContent = message;
    row.appendChild(cell);

    if (retry) {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "dashboard-table-action";
        button.textContent = "Coba lagi";
        button.addEventListener("click", loadDashboardData);
        cell.append(" ", button);
    }

    tableBody.replaceChildren(row);
}

function makeCell(value, strong = false) {
    const cell = document.createElement("td");
    const content = strong ? document.createElement("strong") : cell;
    content.textContent = value ?? "-";
    if (strong) cell.appendChild(content);
    return cell;
}

async function fetchDashboardList(path, signal) {
    const response = await fetch(`${API_BASE}${path}`, {
        headers: {
            "Authorization": `Bearer ${token}`,
            "Accept": "application/json"
        },
        signal
    });
    if (!response.ok) {
        const error = new Error(`Dashboard request failed (${response.status})`);
        error.status = response.status;
        throw error;
    }

    const data = await response.json();
    return Array.isArray(data.data) ? data.data : [];
}

function updateCount(selector, value) {
    const element = document.querySelector(selector);
    if (element) element.textContent = String(value);
}

function renderReservations(reservations) {
    if (!reservationTableBody) return;
    if (reservations.length === 0) {
        setTableState(reservationTableBody, "Belum ada pengajuan reservasi.", 6);
        return;
    }

    const rows = reservations.slice(0, 5).map((reservation) => {
        const row = document.createElement("tr");
        row.append(
            makeCell(reservation.facility, true),
            makeCell(reservation.date),
            makeCell(reservation.time),
            makeCell(reservation.purpose)
        );

        const statusCell = document.createElement("td");
        const status = document.createElement("span");
        const allowedStatus = ["pending", "approved", "rejected", "cancelled"];
        const statusClass = allowedStatus.includes(reservation.status_class) ? reservation.status_class : "cancelled";
        status.className = `dashboard-status ${statusClass}`;
        status.textContent = reservation.status || "Status tidak diketahui";
        statusCell.appendChild(status);
        row.appendChild(statusCell);

        const actionCell = document.createElement("td");
        const button = document.createElement("button");
        button.type = "button";
        button.className = "dashboard-table-action reservation-detail-button";
        Object.assign(button.dataset, {
            id: reservation.id,
            facility: reservation.facility || "-",
            date: reservation.date || "-",
            time: reservation.time || "-",
            purpose: reservation.purpose || "-",
            status: reservation.status || "-",
            statusClass,
            file: reservation.file || "-",
            fileUrl: reservation.file_url || "",
            submitted: reservation.submitted || "-",
            cancellationDeadline: reservation.cancellation_deadline || ""
        });
        button.textContent = "Lihat";
        button.addEventListener("click", () => openReservationDetail(button));
        actionCell.appendChild(button);
        row.appendChild(actionCell);
        return row;
    });

    reservationTableBody.replaceChildren(...rows);
}

function renderReports(reports) {
    if (!reportTableBody) return;
    if (reports.length === 0) {
        setTableState(reportTableBody, "Belum ada laporan fasilitas.", 5);
        return;
    }

    const rows = reports.map((report) => {
        const row = document.createElement("tr");
        row.append(
            makeCell(report.facility),
            makeCell(report.category),
            makeCell(report.date)
        );

        const statusCell = document.createElement("td");
        const status = document.createElement("span");
        const allowedStatus = ["new", "processing", "completed", "rejected"];
        const statusClass = allowedStatus.includes(report.statusClass) ? report.statusClass : "rejected";
        status.className = `report-status ${statusClass}`;
        status.textContent = report.status || "Status tidak diketahui";
        statusCell.appendChild(status);
        row.appendChild(statusCell);

        const actionCell = document.createElement("td");
        const button = document.createElement("button");
        button.type = "button";
        button.className = "dashboard-table-action report-detail-button";
        Object.assign(button.dataset, {
            facility: report.facility || "-",
            category: report.category || "-",
            location: report.location || "-",
            date: report.date || "-",
            status: report.status || "-",
            statusClass,
            description: report.description || "-",
            photo: report.photo || "-",
            photoUrl: report.photo_url || "",
            note: report.note || "-",
            submitted: report.submitted || "-"
        });
        button.textContent = "Lihat";
        actionCell.appendChild(button);
        row.appendChild(actionCell);
        return row;
    });

    reportTableBody.replaceChildren(...rows);
}

async function loadDashboardData() {
    const loadId = ++dashboardLoadId;
    dashboardController?.abort();
    dashboardController = new AbortController();
    const { signal } = dashboardController;

    setTableState(reservationTableBody, "Memuat reservasi...", 6);
    setTableState(reportTableBody, "Memuat laporan...", 5);
    document.querySelectorAll("[data-dashboard-stat], [data-report-stat]").forEach((element) => {
        element.textContent = "—";
    });

    const results = await Promise.allSettled([
        fetchDashboardList("/api/user/reservations", signal),
        fetchDashboardList("/api/user/reports", signal)
    ]);
    if (loadId !== dashboardLoadId) return;

    const [reservationResult, reportResult] = results;
    if (reservationResult.status === "fulfilled") {
        const reservations = reservationResult.value;
        renderReservations(reservations);
        updateCount('[data-dashboard-stat="reservations"]', reservations.length);
        updateCount('[data-dashboard-stat="pending"]', reservations.filter((item) => item.status_class === "pending").length);
        updateCount('[data-dashboard-stat="approved"]', reservations.filter((item) => item.status_class === "approved").length);
    } else if (reservationResult.reason?.name !== "AbortError") {
        console.error("Dashboard reservations load error:", reservationResult.reason);
        const message = reservationResult.reason?.status === 401
            ? "Sesi login berakhir. Silakan masuk kembali."
            : "Reservasi gagal dimuat.";
        setTableState(reservationTableBody, message, 6, true);
        ["reservations", "pending", "approved"].forEach((name) => updateCount(`[data-dashboard-stat="${name}"]`, "—"));
    }

    if (reportResult.status === "fulfilled") {
        const reports = reportResult.value;
        renderReports(reports);
        const counts = {
            total: reports.length,
            new: reports.filter((item) => item.statusClass === "new").length,
            processing: reports.filter((item) => item.statusClass === "processing").length,
            completed: reports.filter((item) => item.statusClass === "completed").length,
            rejected: reports.filter((item) => item.statusClass === "rejected").length
        };
        Object.entries(counts).forEach(([status, count]) => updateCount(`[data-report-stat="${status}"]`, count));
        updateCount('[data-dashboard-stat="active-reports"]', counts.new + counts.processing);
    } else if (reportResult.reason?.name !== "AbortError") {
        console.error("Dashboard reports load error:", reportResult.reason);
        const message = reportResult.reason?.status === 401
            ? "Sesi login berakhir. Silakan masuk kembali."
            : "Laporan gagal dimuat.";
        setTableState(reportTableBody, message, 5, true);
        ["total", "new", "processing", "completed", "rejected"].forEach((name) => updateCount(`[data-report-stat="${name}"]`, "—"));
        updateCount('[data-dashboard-stat="active-reports"]', "—");
    }
}

loadDashboardData();